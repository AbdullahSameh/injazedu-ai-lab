#!/bin/bash
# scripts/lab-stack.sh — one command starts a work session (P0 004, المرحلة 11;
# specs/004-handover-and-p1-readiness, contracts/source-access-and-stack.md §2).
#
#   lab-stack.sh up | down | status [--quiet]
#
# Components, in the order they are handled:
#   postgres       the Lab's own PostgreSQL 17 + pgvector, via docker compose
#   ai-service     FastAPI embedding service, apps/ai-service/.venv/bin/uvicorn
#   queue worker   Laravel queue:work through PHP 8.4 (absolute path, never linked)
#   model runtime  Ollama — CHECKED, NEVER STARTED (FR-009): it is the official
#                  macOS app; no login item, no launchd agent, no supervisor
#
# Behavioural guarantees (FR-007..FR-010):
#   - Idempotent (FR-008): ownership is a pid file PLUS a liveness check on the
#     recorded pid AND its command signature. A stale pid file neither blocks a
#     start nor adopts an unrelated process; a port held by an unmanaged process
#     is reported, never killed.
#   - Reports, does not verify (FR-010): every path's final line points at
#     `php artisan lab:health`, which is the verdict.
#   - down is symmetric: it stops what up started and leaves Ollama alone.
#   - status has no side effects.
#
# Exit codes: 0 = every component in the requested state · 1 = at least one is
# not · 2 = cannot run (missing prerequisite; the die() contract).
#
# SHELL CONSTRAINT — bash 3.2 ONLY (/bin/bash 3.2.57 is the machine's bash).
# Forbidden constructs: associative arrays, mapfile/readarray, ${var,,},
# combined redirection &>>, ${!prefix@}. See scripts/lib/output.sh header.

set -u

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/output.sh
. "$SCRIPT_DIR/lib/output.sh"

REPO_ROOT=$(cd "$SCRIPT_DIR/.." && pwd)
PHP84=/opt/homebrew/opt/php@8.4/bin/php
SERVICE_DIR="$REPO_ROOT/apps/ai-service"
LAB_DIR="$REPO_ROOT/apps/lab"
RUN_DIR="$REPO_ROOT/logs/lab-stack"
COMPOSE_FILE="$REPO_ROOT/docker-compose.yml"
LAB_PORT=5433
OLLAMA_URL=${OLLAMA_URL:-http://127.0.0.1:11434}

SERVICE_PID_FILE="$RUN_DIR/ai-service.pid"
WORKER_PID_FILE="$RUN_DIR/queue-worker.pid"
SERVICE_LOG="$RUN_DIR/ai-service.log"
WORKER_LOG="$RUN_DIR/queue-worker.log"
SERVICE_SIG='uvicorn app\.main:app'
WORKER_SIG='queue:work'

# --- argument parsing --------------------------------------------------------
ACTION=""
for arg in "$@"; do
    case "$arg" in
        up|down|status) ACTION="$arg" ;;
        --quiet) OUTPUT_QUIET=1 ;;
        *) printf 'usage: %s up|down|status [--quiet]\n' "$0" >&2; exit 2 ;;
    esac
done
[ -n "$ACTION" ] || { printf 'usage: %s up|down|status [--quiet]\n' "$0" >&2; exit 2; }

# --- shared helpers ----------------------------------------------------------

# read_owned_pid <pid-file> <signature-regex>
# Sets OWNED_PID and returns 0 iff the file names a live process whose command
# carries the signature. Any other outcome (missing file, garbage, dead pid,
# recycled pid running something unrelated) returns 1; the caller decides
# whether that means "stale" (start fresh) or "unrelated" (report, never touch).
read_owned_pid() {
    OWNED_PID=""
    [ -f "$1" ] || return 1
    OWNED_PID=$(tr -d '[:space:]' < "$1")
    case "$OWNED_PID" in
        ''|*[!0-9]*) return 1 ;;
    esac
    ps -p "$OWNED_PID" -o command= 2>/dev/null | grep -qE "$2" || { OWNED_PID=""; return 1; }
    return 0
}

# stop_recorded <pid-file> <signature-regex> <label> — TERM, wait up to 5 s,
# then KILL. A recorded pid whose command no longer carries the signature is
# treated as a recycled pid: the FILE goes, the PROCESS is never touched.
# Returns 0 iff nothing of ours is left running.
stop_recorded() {
    local pid_file=$1 sig=$2 label=$3 i
    if ! read_owned_pid "$pid_file" "$sig"; then
        rm -f "$pid_file"
        return 0
    fi
    kill -TERM "$OWNED_PID" >/dev/null 2>&1
    for i in 1 2 3 4 5; do
        ps -p "$OWNED_PID" >/dev/null 2>&1 || break
        sleep 1
    done
    if ps -p "$OWNED_PID" >/dev/null 2>&1; then
        kill -KILL "$OWNED_PID" >/dev/null 2>&1
        sleep 1
    fi
    if ps -p "$OWNED_PID" >/dev/null 2>&1; then
        fail "$label" "pid $OWNED_PID refused to stop"
        return 1
    fi
    rm -f "$pid_file"
    ok "$label" "stopped (was pid $OWNED_PID)"
    return 0
}

# --- preconditions (exit 2 = cannot run) -------------------------------------
check_preconditions() {
    command -v docker >/dev/null 2>&1 \
        || die "docker CLI not found — start Docker/OrbStack (ADR-019)"
    docker compose version >/dev/null 2>&1 \
        || die "docker compose not available — the Compose plugin is required"
    [ -f "$COMPOSE_FILE" ] || die "no docker-compose.yml at the repository root"
    if [ "$ACTION" != "status" ]; then
        [ -f "$REPO_ROOT/.env" ] && grep -q '^LAB_DB_PASSWORD=' "$REPO_ROOT/.env" 2>/dev/null \
            || die "no LAB_DB_PASSWORD in root .env — copy .env.example and generate a local password"
        [ -x "$PHP84" ] || die "PHP 8.4 not at $PHP84 (never brew link it; notes.md N2)"
        [ -x "$SERVICE_DIR/.venv/bin/uvicorn" ] \
            || die "apps/ai-service/.venv/bin/uvicorn missing — run: cd apps/ai-service && uv sync"
        [ -f "$LAB_DIR/artisan" ] || die "no apps/lab/artisan — the Laravel application is missing"
    fi
    mkdir -p "$RUN_DIR" || die "cannot create runtime directory $RUN_DIR"
}

# --- components --------------------------------------------------------------

# The postgres container belongs to THIS compose project: resolved from the
# compose file, never hardcoded, so an isolated clone with its own
# container_name and volume (the FR-012 clean-folder rehearsal) manages its own
# container and can never touch the live one.
pg_container_id() {
    docker compose -f "$COMPOSE_FILE" ps -q postgres 2>/dev/null
}

pg_container_label() {
    local id
    id=$(pg_container_id)
    if [ -n "$id" ]; then
        docker inspect -f '{{.Name}}' "$id" 2>/dev/null | sed 's#^/##'
    else
        printf '(not created)'
    fi
}

component_postgres() {
    # $1 = desired state: up | down | status. Ensures and REPORTS for up/down;
    # the status mode is a pure read (the contract forbids status side effects).
    local want=$1 state health cid label
    cid=$(pg_container_id)
    label=$(pg_container_label)
    if [ "$want" = "status" ]; then
        health=$(docker inspect -f '{{.State.Health.Status}}' "$cid" 2>/dev/null)
        if [ "$health" = "healthy" ]; then
            ok "postgres" "container $label healthy on 127.0.0.1:$LAB_PORT"
            return 0
        fi
        fail "postgres" "not healthy (state: ${health:-absent})"
        return 1
    fi
    if [ "$want" = "up" ]; then
        state=$(docker inspect -f '{{.State.Status}}/{{.State.Health.Status}}' "$cid" 2>/dev/null)
        case "$state" in
            running/healthy)
                published=$(docker compose -f "$COMPOSE_FILE" port postgres 5432 2>/dev/null | tr -d '[:space:]')
                ok "postgres" "container $label healthy (${published:-127.0.0.1:$LAB_PORT})"
                return 0 ;;
            running/starting|starting*) ;;
            "")
                docker compose -f "$COMPOSE_FILE" up -d postgres >/dev/null 2>&1 \
                    || { fail "postgres" "docker compose up failed"; note "remediation: docker compose logs postgres"; return 1; } ;;
            *)
                docker compose -f "$COMPOSE_FILE" restart postgres >/dev/null 2>&1 \
                    || { fail "postgres" "container $state — restart failed"; return 1; } ;;
        esac
        local i
        for i in $(seq 1 30); do
            cid=$(pg_container_id)
            health=$(docker inspect -f '{{.State.Health.Status}}' "$cid" 2>/dev/null)
            [ "$health" = "healthy" ] && break
            [ "$health" = "unhealthy" ] && break
            sleep 1
        done
        label=$(pg_container_label)
        if [ "$health" = "healthy" ]; then
            ok "postgres" "container $label healthy on 127.0.0.1:$LAB_PORT"
            return 0
        fi
        fail "postgres" "container did not turn healthy (state: ${health:-gone})"
        note "remediation: docker compose logs postgres"
        return 1
    else
        state=$(docker inspect -f '{{.State.Status}}' "$cid" 2>/dev/null)
        if [ "$state" = "running" ]; then
            docker compose -f "$COMPOSE_FILE" stop postgres >/dev/null 2>&1 \
                || { fail "postgres" "docker compose stop failed"; return 1; }
        fi
        cid=$(pg_container_id)
        state=$(docker inspect -f '{{.State.Status}}' "$cid" 2>/dev/null)
        if [ "$state" = "exited" ] || [ "$state" = "created" ] || [ -z "$state" ]; then
            ok "postgres" "container stopped (volume preserved)"
            return 0
        fi
        fail "postgres" "container still $state"
        return 1
    fi
}

component_service() {
    # $1 = up | down | status. Manages the FastAPI service process only.
    local want=$1 pid host port holders
    host=$(sed -n 's/^SERVICE_HOST=//p' "$SERVICE_DIR/.env" 2>/dev/null | head -n 1)
    port=$(sed -n 's/^SERVICE_PORT=//p' "$SERVICE_DIR/.env" 2>/dev/null | head -n 1)
    if [ "$want" != "status" ]; then
        [ -n "$host" ] || { fail "ai-service" "SERVICE_HOST unset in apps/ai-service/.env"; return 1; }
        [ -n "$port" ] || { fail "ai-service" "SERVICE_PORT unset in apps/ai-service/.env"; return 1; }
    fi
    host=${host:-127.0.0.1}
    port=${port:-8001}
    if [ "$want" = "down" ]; then
        stop_recorded "$SERVICE_PID_FILE" "$SERVICE_SIG" "ai-service"
        return $?
    fi
    if read_owned_pid "$SERVICE_PID_FILE" "$SERVICE_SIG"; then
        ok "ai-service" "running, pid $OWNED_PID, $host:$port"
        return 0
    fi
    rm -f "$SERVICE_PID_FILE"
    if [ "$want" = "status" ]; then
        holders=$(lsof -nP -iTCP:"$port" -sTCP:LISTEN -t 2>/dev/null | sort -u | tr '\n' ' ')
        if [ -n "$holders" ]; then
            warn "ai-service" "not managed here; port $port held by pid(s): $holders"
        else
            fail "ai-service" "not running"
        fi
        return 1
    fi
    # up: refuse to fight an unmanaged listener — report it, never adopt or kill.
    holders=$(lsof -nP -iTCP:"$port" -sTCP:LISTEN 2>/dev/null | awk 'NR>1 {print $1" pid "$2}' | sort -u | tr '\n' '; ')
    if [ -n "$holders" ]; then
        fail "ai-service" "port $port is held by an unmanaged process ($holders)"
        note "remediation: stop it yourself (it was not started by lab-stack.sh), then re-run up"
        return 1
    fi
    : > "$SERVICE_LOG"
    (cd "$SERVICE_DIR" && exec .venv/bin/uvicorn app.main:app --host "$host" --port "$port") >> "$SERVICE_LOG" 2>&1 &
    pid=$!
    echo "$pid" > "$SERVICE_PID_FILE"
    local i
    for i in $(seq 1 20); do
        nc -z -w 1 "$host" "$port" >/dev/null 2>&1 && break
        ps -p "$pid" >/dev/null 2>&1 || break
        sleep 1
    done
    if nc -z -w 1 "$host" "$port" >/dev/null 2>&1 && ps -p "$pid" -o command= 2>/dev/null | grep -qE "$SERVICE_SIG"; then
        ok "ai-service" "started, pid $pid, $host:$port"
        return 0
    fi
    fail "ai-service" "did not come up on $host:$port"
    note "last log line: $(tail -n 1 "$SERVICE_LOG" 2>/dev/null)"
    rm -f "$SERVICE_PID_FILE"
    return 1
}

component_worker() {
    # $1 = up | down | status. Manages the Laravel queue worker process only.
    local want=$1 pid
    if [ "$want" = "down" ]; then
        stop_recorded "$WORKER_PID_FILE" "$WORKER_SIG" "queue worker"
        return $?
    fi
    if read_owned_pid "$WORKER_PID_FILE" "$WORKER_SIG"; then
        ok "queue worker" "running, pid $OWNED_PID"
        return 0
    fi
    rm -f "$WORKER_PID_FILE"
    if [ "$want" = "status" ]; then
        fail "queue worker" "not running"
        return 1
    fi
    : > "$WORKER_LOG"
    (cd "$LAB_DIR" && exec "$PHP84" artisan queue:work) >> "$WORKER_LOG" 2>&1 &
    pid=$!
    echo "$pid" > "$WORKER_PID_FILE"
    sleep 1
    if ps -p "$pid" -o command= 2>/dev/null | grep -qE "$WORKER_SIG"; then
        ok "queue worker" "started, pid $pid"
        return 0
    fi
    fail "queue worker" "exited immediately (pid $pid)"
    note "last log line: $(tail -n 1 "$WORKER_LOG" 2>/dev/null)"
    rm -f "$WORKER_PID_FILE"
    return 1
}

component_ollama() {
    # Checked, never started (FR-009). Read-only probe; OLLAMA_URL is overridable
    # for testing so the real macOS app never has to be touched.
    local body version
    body=$(curl -s --max-time 3 "$OLLAMA_URL/api/version" 2>/dev/null)
    version=$(printf '%s' "$body" | sed -n 's/.*"version":"\([^"]*\)".*/\1/p')
    if [ -n "$version" ]; then
        ok "model runtime" "Ollama $version reachable (not started by this script)"
        return 0
    fi
    fail "model runtime" "nothing answering on $OLLAMA_URL"
    note "remediation: start the Ollama macOS app (this script never starts it — FR-009)"
    return 1
}

# --- actions -----------------------------------------------------------------

final_line() {
    # $1 = summary. Every path's last line points at lab:health (FR-010).
    printf '%s verdict tool: php artisan lab:health\n' "$1"
}

case "$ACTION" in
    up)
        check_preconditions
        # The model runtime gates the session: checked first, so an absent
        # Ollama starts nothing else (T008) and the message is plain.
        component_ollama || { final_line "STACK NOT UP —"; exit 1; }
        component_postgres up
        component_service up
        component_worker up
        if [ "$FAILURES" -eq 0 ]; then
            printf 'STACK UP - run: php artisan lab:health\n'
            exit 0
        fi
        final_line "STACK NOT UP ($FAILURES blocking) — fix the [FAIL] lines above;"
        exit 1
        ;;
    down)
        check_preconditions
        component_worker down
        component_service down
        component_postgres down
        if [ "$FAILURES" -eq 0 ]; then
            printf 'STACK DOWN - model runtime left alone; restart with: scripts/lab-stack.sh up\n'
            exit 0
        fi
        final_line "STACK PARTIALLY DOWN ($FAILURES blocking) —"
        exit 1
        ;;
    status)
        check_preconditions
        component_postgres status
        component_service status
        component_worker status
        component_ollama
        if [ "$FAILURES" -eq 0 ]; then
            printf 'STACK READY - run: php artisan lab:health\n'
            exit 0
        fi
        final_line "STACK NOT READY ($FAILURES down) —"
        exit 1
        ;;
esac
