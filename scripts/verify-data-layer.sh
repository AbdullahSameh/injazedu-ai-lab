#!/bin/bash
# scripts/verify-data-layer.sh — prove the Lab database runs under its ceiling.
#
# Implements FR-023 (proving FR-016…FR-022). Gates SC-005…SC-010.
# Contract: specs/001-lab-foundation-bootstrap/contracts/verify-data-layer.md
#
# Assertions:
#   1  readiness      pg_isready in-container, THEN a trivial SELECT 1 (research R5)
#   2  capabilities   vector + pg_trgm present in the LIVE database, never read
#                     from init.sql (research R3, R6)
#   3  bind address   published port is 127.0.0.1:5433 (FR-017, ADR-018)
#   4  loopback only  INVERTED — connection to the host's LAN address on 5433
#                     must be REFUSED (FR-018, SC-010)
#   5  neighbour      postgresql@14 still listening on 5432 (SC-008)
#   6  memory ceiling read BACK FROM THE RUNNING CONTAINER, never from the
#                     compose file; no limit set = FAIL, not silent pass
#                     (research R4, FR-021)
#   7  persistence    marker row upserted, service restarted, row re-read and
#                     compared — only with --with-restart (FR-020, SC-007)
#
# All SQL runs INSIDE the container via docker compose exec -T postgres psql;
# the host client is psql 14 against a server 17 (research R3).
#
# Exit codes: 0 = all assertions pass · 1 = at least one failure ·
#             2 = cannot run (engine not responding, LAB_DB_PASSWORD unset).
#
# SHELL CONSTRAINT — bash 3.2 ONLY. No associative arrays, no mapfile, no
# ${var,,}. See scripts/lib/output.sh header for the full forbidden list.

set -u

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/output.sh
. "$SCRIPT_DIR/lib/output.sh"

REPO_ROOT=$(cd "$SCRIPT_DIR/.." && pwd)
cd "$REPO_ROOT" || die "cannot enter repository root $REPO_ROOT"

CONTAINER="injazedu_lab_postgres"
READINESS_BOUND=60
MEM_CEILING_MIB=1536

WITH_RESTART=0
for arg in "$@"; do
    case "$arg" in
        --quiet) OUTPUT_QUIET=1 ;;
        --with-restart) WITH_RESTART=1 ;;
        *) echo "usage: $0 [--quiet] [--with-restart]" >&2; exit 2 ;;
    esac
done

ASSERTIONS=6
[ "$WITH_RESTART" -eq 1 ] && ASSERTIONS=7

# --- preconditions (exit 2 = cannot run) ------------------------------------
docker info >/dev/null 2>&1 \
    || die "container engine not responding — start OrbStack (open -a OrbStack) and re-run"

LAB_DB_PASSWORD=${LAB_DB_PASSWORD:-}
if [ -z "$LAB_DB_PASSWORD" ] && [ -f "$REPO_ROOT/.env" ]; then
    LAB_DB_PASSWORD=$(sed -n 's/^LAB_DB_PASSWORD=//p' "$REPO_ROOT/.env" | head -n 1)
fi
[ -n "$LAB_DB_PASSWORD" ] \
    || die "LAB_DB_PASSWORD is not set — copy .env.example to .env and generate a local password (FR-022). No default or empty-password fallback exists."

# Helper: run SQL inside the container (client and server the same build, R3).
psql_c() {
    docker compose exec -T postgres psql -U lab -d injazedu_lab -tA "$@"
}

# Helper: wait until pg_isready AND SELECT 1 both succeed; echoes seconds used,
# returns 1 if the readiness bound is exceeded (R5: both halves, in order).
wait_ready() {
    elapsed=0
    while [ "$elapsed" -lt "$READINESS_BOUND" ]; do
        if docker compose exec -T postgres pg_isready -U lab -d injazedu_lab >/dev/null 2>&1 \
           && [ "$(psql_c -c 'SELECT 1' 2>/dev/null)" = "1" ]; then
            echo "$elapsed"
            return 0
        fi
        sleep 2
        elapsed=$((elapsed + 2))
    done
    return 1
}

# --- assertion 1: readiness --------------------------------------------------
if secs=$(wait_ready); then
    ok "readiness" "ready in ${secs}s (threshold ${READINESS_BOUND}s)"
else
    fail "readiness" "not ready within ${READINESS_BOUND}s"
    note "remediation: docker compose logs postgres — check init and health"
fi

# --- assertion 2: capabilities (live catalog, never init.sql) ----------------
ext_rows=$(psql_c -F' ' -c "SELECT extname, extversion FROM pg_extension WHERE extname IN ('vector','pg_trgm') ORDER BY extname" 2>/dev/null)
ext_count=$(printf '%s\n' "$ext_rows" | grep -c .)
ext_list=$(printf '%s\n' "$ext_rows" | tr '\n' ',' | sed 's/,$//; s/,/, /g')
if [ "$ext_count" -eq 2 ]; then
    ok "capabilities" "$ext_list  (2 of 2)"
else
    fail "capabilities" "$ext_list  ($ext_count of 2)"
    note "remediation: init.sql runs only at volume creation (research R6) —"
    note "create the missing extension in the live database or recreate the volume"
fi

# --- assertion 3: bind address -----------------------------------------------
published=$(docker compose port postgres 5432 2>/dev/null | tr -d '[:space:]')
if [ "$published" = "127.0.0.1:5433" ]; then
    ok "bind address" "$published"
else
    fail "bind address" "${published:-<none published>}"
    note "remediation: ports must publish 127.0.0.1:5433:5432 (ADR-018)"
fi

# --- assertion 4: loopback only (INVERTED — refusal is success) --------------
lan_ip=$(ipconfig getifaddr en0 2>/dev/null)
[ -z "$lan_ip" ] && lan_ip=$(ipconfig getifaddr en1 2>/dev/null)
if [ -z "$lan_ip" ]; then
    fail "loopback only" "no non-loopback address found — cannot prove refusal"
    note "remediation: connect a network interface and re-run; silence here is not safety"
elif (exec 3<>"/dev/tcp/$lan_ip/5433") 2>/dev/null; then
    exec 3>&- 3<&-
    fail "loopback only" "connection from $lan_ip ACCEPTED — port 5433 is exposed"
    note "remediation: the ports mapping must bind 127.0.0.1, not 0.0.0.0 (FR-018)"
else
    ok "loopback only" "connection from $lan_ip refused"
fi

# --- assertion 5: neighbour on 5432 intact -----------------------------------
neighbour=$(lsof -nP -iTCP:5432 -sTCP:LISTEN 2>/dev/null | awk 'NR>1 {print $1, $2; exit}')
if [ -n "$neighbour" ]; then
    n_name=$(printf '%s' "$neighbour" | cut -d' ' -f1)
    n_pid=$(printf '%s' "$neighbour" | cut -d' ' -f2)
    ok "port 5432 intact" "$n_name still listening (pid $n_pid)"
else
    fail "port 5432 intact" "nothing listening on 5432 — postgresql@14 was disturbed"
    note "remediation: the Lab must never touch the host's postgresql@14 (SC-008);"
    note "restart it with: brew services start postgresql@14"
fi

# --- assertion 6: memory ceiling, measured from the running container --------
mem_bytes=$(docker inspect -f '{{.HostConfig.Memory}}' "$CONTAINER" 2>/dev/null)
mem_in_use=$(docker stats --no-stream --format '{{.MemUsage}}' "$CONTAINER" 2>/dev/null | cut -d/ -f1 | tr -d ' ')
if [ -z "$mem_bytes" ] || [ "$mem_bytes" = "0" ]; then
    fail "memory ceiling" "no limit set on the running container — ceiling absent, not assumed"
    note "remediation: mem_limit was silently ignored (research R4); measure and"
    note "record an ADR before switching to deploy.resources.limits.memory"
else
    mem_mib=$((mem_bytes / 1048576))
    if [ "$mem_mib" -le "$MEM_CEILING_MIB" ]; then
        ok "memory ceiling" "limit ${mem_mib} MiB, in use ${mem_in_use:-unknown}"
    else
        fail "memory ceiling" "limit ${mem_mib} MiB exceeds the ${MEM_CEILING_MIB} MiB budget"
        note "remediation: P0 §12.3 budget — lower the limit or record an ADR"
    fi
fi

# --- assertion 7: persistence across restart (--with-restart only) -----------
if [ "$WITH_RESTART" -eq 1 ]; then
    psql_c -c "CREATE TABLE IF NOT EXISTS lab_persistence_marker (id integer PRIMARY KEY, written_at timestamptz NOT NULL, note text NOT NULL)" >/dev/null 2>&1
    psql_c -c "INSERT INTO lab_persistence_marker (id, written_at, note) VALUES (1, now(), 'persistence probe') ON CONFLICT (id) DO NOTHING" >/dev/null 2>&1
    before=$(psql_c -F'|' -c "SELECT id, written_at, note FROM lab_persistence_marker WHERE id = 1" 2>/dev/null)

    docker compose restart postgres >/dev/null 2>&1

    if secs=$(wait_ready); then
        after=$(psql_c -F'|' -c "SELECT id, written_at, note FROM lab_persistence_marker WHERE id = 1" 2>/dev/null)
        written_at=$(printf '%s' "$after" | cut -d'|' -f2)
        if [ -n "$before" ] && [ "$before" = "$after" ]; then
            ok "persistence" "marker row survived restart (written $written_at)"
        else
            fail "persistence" "marker row changed or lost across restart"
            note "before: ${before:-<missing>}"
            note "after:  ${after:-<missing>}"
            note "remediation: check the lab_pgdata named volume is mounted (FR-020)"
        fi
    else
        fail "persistence" "service did not come back within ${READINESS_BOUND}s after restart"
        note "remediation: docker compose logs postgres"
    fi
fi

# --- verdict -----------------------------------------------------------------
verdict "DATA LAYER VERIFIED — $ASSERTIONS assertions, 0 failures" \
        "DATA LAYER BROKEN" "$ASSERTIONS"
exit $?
