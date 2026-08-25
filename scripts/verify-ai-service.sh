#!/bin/bash
# scripts/verify-ai-service.sh — prove the AI service holds its guarantees
# (FR-001, FR-003, FR-005, FR-009; contracts/ai-service.md). Laravel is not
# involved; this script must pass with the Laravel stack entirely absent.
#
# Assertions:
#   1  loopback bind    the listening socket is inspected DIRECTLY (lsof) for
#                       its bind address — never inferred from configuration.
#                       It must be 127.0.0.1 and nothing else.
#   2  non-loopback     a connection attempt to the machine's primary
#                       non-loopback address on the service port is refused
#   3  endpoints        /health, /health/db, /health/ollama, /health/full all
#                       answer 200 on the loopback address
#   4  no MySQL keys    no MySQL/INJAZEDU credential key appears in the
#                       service's environment file (ADR-013, FR-003)
#   5  env agreement    the service's EMBEDDING_CONFIG_VERSION matches
#                       apps/lab/.env's — the contract has exactly one value,
#                       the way verify-lab-app.sh asserts the password pair
#
# Exit codes: 0 = all assertions pass · 1 = at least one failure ·
#             2 = cannot run.
#
# SHELL CONSTRAINT — bash 3.2 ONLY. No associative arrays, no mapfile, no
# ${var,,}. See scripts/lib/output.sh header for the full forbidden list.

set -u

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/output.sh
. "$SCRIPT_DIR/lib/output.sh"

REPO_ROOT=$(cd "$SCRIPT_DIR/.." && pwd)
SERVICE_DIR="$REPO_ROOT/apps/ai-service"
LAB_ENV="$REPO_ROOT/apps/lab/.env"

# --- argument parsing -------------------------------------------------------
for arg in "$@"; do
    case "$arg" in
        --quiet) OUTPUT_QUIET=1 ;;
        *) echo "usage: $0 [--quiet]" >&2; exit 2 ;;
    esac
done

ASSERTIONS=5

# --- preconditions (exit 2 = cannot run) ------------------------------------
[ -f "$SERVICE_DIR/.env" ] || die "no apps/ai-service/.env — copy .env.example and fill it in"
[ -f "$LAB_ENV" ] || die "no apps/lab/.env — Phase 1 (T002) sets the shared contract string there"

SERVICE_PORT=$(sed -n 's/^SERVICE_PORT=//p' "$SERVICE_DIR/.env" | head -n 1)
SERVICE_HOST=$(sed -n 's/^SERVICE_HOST=//p' "$SERVICE_DIR/.env" | head -n 1)
[ -n "$SERVICE_PORT" ] || die "SERVICE_PORT is not set in apps/ai-service/.env"
[ -n "$SERVICE_HOST" ] || die "SERVICE_HOST is not set in apps/ai-service/.env"

# --- assertion 1: the socket itself is loopback, not the configuration ------
# The LISTEN socket is inspected directly. Configuration could say anything;
# only the socket tells the truth (FR-001).
binds=$(lsof -nP -iTCP:"$SERVICE_PORT" -sTCP:LISTEN 2>/dev/null | awk 'NR>1 {print $9}' | sed 's/:[0-9]*$//' | sort -u)
if [ -z "$binds" ]; then
    fail "loopback bind" "nothing is listening on port $SERVICE_PORT — start the service"
    note "remediation: cd apps/ai-service && uv run uvicorn app.main:app --host 127.0.0.1 --port $SERVICE_PORT"
else
    non_loopback=$(printf '%s\n' "$binds" | grep -v '^127\.0\.0\.1$' | grep -v '^::1$' || true)
    if [ -n "$non_loopback" ]; then
        fail "loopback bind" "listening on: $(printf '%s ' $binds)— must be 127.0.0.1 only (FR-001)"
    else
        ok "loopback bind" "port $SERVICE_PORT listens on 127.0.0.1 only (inspected via lsof)"
    fi
fi

# --- assertion 2: a non-loopback connection is refused -----------------------
lan_ip=$(ipconfig getifaddr en0 2>/dev/null || true)
if [ -z "$lan_ip" ]; then
    warn "non-loopback" "no en0 address — cannot test refusal from another address"
else
    if nc -z -w 2 "$lan_ip" "$SERVICE_PORT" >/dev/null 2>&1; then
        fail "non-loopback" "port $SERVICE_PORT ACCEPTS connections on $lan_ip — must refuse (FR-001)"
    else
        ok "non-loopback" "connection to $lan_ip:$SERVICE_PORT refused"
    fi
fi

# --- assertion 3: all four health endpoints answer 200 -----------------------
base="http://127.0.0.1:$SERVICE_PORT"
ep_fail=0
for path in /health /health/db /health/ollama /health/full; do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 40 "$base$path" 2>/dev/null)
    if [ "$code" != "200" ]; then
        fail "endpoints" "GET $path returned HTTP ${code:-<none>} — expected 200"
        ep_fail=1
    fi
done
[ "$ep_fail" -eq 0 ] && ok "endpoints" "/health /health/db /health/ollama /health/full all 200"

# --- assertion 4: no MySQL credential anywhere near the service --------------
if grep -E '^(INJAZEDU_DB_|MYSQL)' "$SERVICE_DIR/.env" >/dev/null 2>&1; then
    fail "no MySQL keys" "apps/ai-service/.env holds a MySQL key — the service must have none (FR-003)"
    note "remediation: remove every INJAZEDU_DB_* / MYSQL* key; source reads go through Laravel only"
else
    ok "no MySQL keys" "apps/ai-service/.env holds no MySQL credential"
fi

# --- assertion 5: the contract string matches apps/lab/.env ------------------
svc_contract=$(sed -n 's/^EMBEDDING_CONFIG_VERSION=//p' "$SERVICE_DIR/.env" | head -n 1)
lab_contract=$(sed -n 's/^EMBEDDING_CONFIG_VERSION=//p' "$LAB_ENV" | head -n 1)
if [ -n "$svc_contract" ] && [ "$svc_contract" = "$lab_contract" ]; then
    ok "env agreement" "EMBEDDING_CONFIG_VERSION = $svc_contract"
else
    fail "env agreement" "service='${svc_contract:-<unset>}' lab='${lab_contract:-<unset>}' — the contract must have exactly one value (FR-005)"
    note "remediation: set both files to the same contract string; changing it invalidates stored vectors (§12.2)"
fi

# --- verdict -----------------------------------------------------------------
if ! verdict "AI SERVICE VERIFIED — $ASSERTIONS assertions, 0 failures" \
             "AI SERVICE BROKEN" "$ASSERTIONS"; then
    exit 1
fi
