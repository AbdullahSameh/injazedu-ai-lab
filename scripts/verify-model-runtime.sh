#!/bin/bash
# scripts/verify-model-runtime.sh — prove the local Ollama runtime is safe and
# both standardised model tags can be resident together.
#
# Implements FR-009 through FR-013 (US3, المرحلة 4). Independent of the source
# database and Laravel application.
#
# Base assertions:
#   1  liveness        Ollama answers /api/tags on 127.0.0.1:11434
#   2  embed tag       embeddinggemma:300m-qat-q4_0 is present exactly
#   3  chat tag        gemma4:e2b-it-qat is present exactly
#   4  bind address    every listener on 11434 is a loopback address
#   5  loopback only   a connection through the host's LAN address is refused
#   6  context length  OLLAMA_CONTEXT_LENGTH is absent from the running process
#
# With --with-memory, four more assertions load both models with empty requests
# (no generation or embedding), read their resident allocation from /api/ps,
# and record Ollama process RSS. Memory figures are REPORTED, never gated:
# constitution v2.1.0 (2026-08-23) removed every memory gate and acceptance
# criterion. The historical §12.3 model line (3.3 GiB combined) stays a warning;
# the old 13 GiB whole-machine ceiling survives ONLY as the trigger for a
# warning — it can never fail this script. Manual review lives in
# docs/runbooks/memory-check.md. macOS total memory is context only because
# compressed/cache memory makes it unsuitable as a component-level figure.
#
# Exit codes: 0 = all assertions pass · 1 = at least one blocking failure ·
#             2 = cannot run.
#
# SHELL CONSTRAINT — bash 3.2 ONLY. No associative arrays, no mapfile, no
# ${var,,}. See scripts/lib/output.sh for the full forbidden list.

set -u

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/output.sh
. "$SCRIPT_DIR/lib/output.sh"

OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_PORT=11434
EMBED_MODEL=embeddinggemma:300m-qat-q4_0
CHAT_MODEL=gemma4:e2b-it-qat
PLAN_MODELS_MIB=3379
# The retired ceiling (constitution v2.1.0). WARNING trigger only — never blocks.
MEMORY_WARN_MIB=13312
WITH_MEMORY=0

# --- argument parsing -------------------------------------------------------
for arg in "$@"; do
    case "$arg" in
        --quiet) OUTPUT_QUIET=1 ;;
        --with-memory) WITH_MEMORY=1 ;;
        *) echo "usage: $0 [--quiet] [--with-memory]" >&2; exit 2 ;;
    esac
done

ASSERTIONS=6
[ "$WITH_MEMORY" -eq 1 ] && ASSERTIONS=10

# --- preconditions (exit 2 = cannot run) -----------------------------------
command -v curl >/dev/null 2>&1 || die "curl is required to probe the Ollama API"
command -v jq >/dev/null 2>&1 || die "jq is required to verify exact model tags"
command -v lsof >/dev/null 2>&1 || die "lsof is required to inspect the listening socket"
command -v ollama >/dev/null 2>&1 || die "Ollama is not installed — run the official installer from https://ollama.com/download"

# Convert a top(1) memory value such as 12G, 941M, or 512K to MiB.
to_mib() {
    printf '%s\n' "$1" | awk '
        /^[0-9.]+[Kk]$/ { value=$0; sub(/[Kk]$/, "", value); printf "%.0f\n", value / 1024; next }
        /^[0-9.]+[Mm]$/ { value=$0; sub(/[Mm]$/, "", value); printf "%.0f\n", value; next }
        /^[0-9.]+[Gg]$/ { value=$0; sub(/[Gg]$/, "", value); printf "%.0f\n", value * 1024; next }
        /^[0-9.]+[Tt]$/ { value=$0; sub(/[Tt]$/, "", value); printf "%.0f\n", value * 1048576; next }
        /^[0-9]+$/      { printf "%.0f\n", $0 / 1048576; next }
    '
}

bytes_to_mib() {
    awk -v bytes="$1" 'BEGIN { printf "%.0f\n", bytes / 1048576 }'
}

# --- assertion 1: liveness --------------------------------------------------
tags_json=$(curl --noproxy '*' -fsS --connect-timeout 3 "$OLLAMA_URL/api/tags" 2>/dev/null) ||
    die "Ollama not reachable on 127.0.0.1:11434 — start the Ollama macOS app and re-run"

if printf '%s' "$tags_json" | jq -e '.models | type == "array"' >/dev/null 2>&1; then
    ok "liveness" "Ollama answering on 127.0.0.1:11434"
else
    fail "liveness" "/api/tags returned malformed JSON"
fi

# --- assertions 2–3: exact model tags --------------------------------------
models_ready=1
if printf '%s' "$tags_json" | jq -e --arg tag "$EMBED_MODEL" '.models | any(.name == $tag)' >/dev/null 2>&1; then
    ok "embed tag" "$EMBED_MODEL present"
else
    fail "embed tag" "$EMBED_MODEL missing"
    note "remediation: ollama pull $EMBED_MODEL — do not substitute another tag"
    models_ready=0
fi

if printf '%s' "$tags_json" | jq -e --arg tag "$CHAT_MODEL" '.models | any(.name == $tag)' >/dev/null 2>&1; then
    ok "chat tag" "$CHAT_MODEL present"
else
    fail "chat tag" "$CHAT_MODEL missing"
    note "remediation: ollama pull $CHAT_MODEL — do not substitute another tag"
    models_ready=0
fi

# --- assertion 4: listening socket is loopback-only -------------------------
listeners=$(lsof -nP -iTCP:"$OLLAMA_PORT" -sTCP:LISTEN 2>/dev/null | awk 'NR > 1 {print $9}')
if [ -z "$listeners" ]; then
    fail "bind address" "no listening socket found on port $OLLAMA_PORT"
else
    unsafe_listener=""
    for listener in $listeners; do
        case "$listener" in
            127.0.0.1:"$OLLAMA_PORT"|\[::1\]:"$OLLAMA_PORT") ;;
            *) unsafe_listener="$listener"; break ;;
        esac
    done
    if [ -z "$unsafe_listener" ]; then
        ok "bind address" "$(printf '%s' "$listeners" | tr '\n' ' ' | sed 's/[[:space:]]$//')"
    else
        fail "bind address" "$unsafe_listener is not loopback"
        note "remediation: restore Ollama's loopback default; do not expose port 11434"
    fi
fi

# --- assertion 5: non-loopback connection refused (inverted) ----------------
lan_ip=$(ipconfig getifaddr en0 2>/dev/null)
[ -z "$lan_ip" ] && lan_ip=$(ipconfig getifaddr en1 2>/dev/null)
if [ -z "$lan_ip" ]; then
    fail "loopback only" "no non-loopback address found — cannot prove refusal"
    note "remediation: connect a network interface and re-run; silence is not safety"
elif curl --noproxy '*' -fsS --connect-timeout 2 "$lan_ip:$OLLAMA_PORT/api/tags" >/dev/null 2>&1; then
    fail "loopback only" "connection through $lan_ip:$OLLAMA_PORT was accepted"
    note "remediation: Ollama must listen on 127.0.0.1 only (FR-009)"
else
    ok "loopback only" "connection through $lan_ip:$OLLAMA_PORT refused"
fi

# --- assertion 6: no global context length ----------------------------------
ollama_pid=$(lsof -nP -iTCP:"$OLLAMA_PORT" -sTCP:LISTEN -t 2>/dev/null | head -n 1)
if [ -z "$ollama_pid" ]; then
    fail "context length" "cannot identify the running Ollama process"
elif ps eww -p "$ollama_pid" -o command= 2>/dev/null | grep -q 'OLLAMA_CONTEXT_LENGTH='; then
    fail "context length" "OLLAMA_CONTEXT_LENGTH is set globally on pid $ollama_pid"
    note "remediation: remove it; num_ctx belongs to individual calls in المرحلة 6"
else
    ok "context length" "no global OLLAMA_CONTEXT_LENGTH on pid $ollama_pid"
fi

# --- optional memory measurement --------------------------------------------
if [ "$WITH_MEMORY" -eq 1 ]; then
    if [ "$models_ready" -ne 1 ]; then
        fail "models resident" "cannot load both exact tags while one or more are missing"
        fail "resident memory" "not measured"
        fail "ollama RSS" "not measured"
        fail "memory report" "not measured"
    else
        embed_payload=$(jq -cn --arg model "$EMBED_MODEL" '{model:$model, input:[], keep_alive:-1}')
        chat_payload=$(jq -cn --arg model "$CHAT_MODEL" '{model:$model, keep_alive:-1}')

        # Load the larger chat model first. On a memory-constrained unified-memory
        # Mac, loading it second can make Ollama evict the already-resident embed
        # runner even though the smaller runner fits alongside the chat runner.
        curl --noproxy '*' -fsS --max-time 300 -H 'Content-Type: application/json' \
            -d "$chat_payload" "$OLLAMA_URL/api/generate" >/dev/null 2>&1 ||
            die "could not preload $CHAT_MODEL with an empty request"
        curl --noproxy '*' -fsS --max-time 300 -H 'Content-Type: application/json' \
            -d "$embed_payload" "$OLLAMA_URL/api/embed" >/dev/null 2>&1 ||
            die "could not preload $EMBED_MODEL with an empty request"

        ps_json=$(curl --noproxy '*' -fsS --connect-timeout 3 "$OLLAMA_URL/api/ps" 2>/dev/null) ||
            die "Ollama /api/ps did not return the resident model inventory"
        resident_count=$(printf '%s' "$ps_json" | jq --arg embed "$EMBED_MODEL" --arg chat "$CHAT_MODEL" \
            '[.models[] | select(.name == $embed or .name == $chat)] | length')

        if [ "$resident_count" -eq 2 ]; then
            ok "models resident" "both exact tags loaded together"
        else
            fail "models resident" "$resident_count of 2 exact tags reported by /api/ps"
        fi

        resident_bytes=$(printf '%s' "$ps_json" | jq --arg embed "$EMBED_MODEL" --arg chat "$CHAT_MODEL" \
            '[.models[] | select(.name == $embed or .name == $chat) | .size] | add // 0')
        resident_mib=$(bytes_to_mib "$resident_bytes")
        if [ "$resident_bytes" -gt 0 ]; then
            ok "resident memory" "${resident_mib} MiB for both models"
            if [ "$resident_mib" -gt "$PLAN_MODELS_MIB" ]; then
                warn "§12.3 model line" "${resident_mib} MiB measured > ${PLAN_MODELS_MIB} MiB estimated"
            else
                note "§12.3 model line: ${resident_mib} MiB measured <= ${PLAN_MODELS_MIB} MiB estimated"
            fi
        else
            fail "resident memory" "/api/ps returned no model allocation"
        fi

        ollama_rss_kib=$(ps -axo rss=,command= 2>/dev/null | awk '
            $2 == "/Applications/Ollama.app/Contents/MacOS/Ollama" ||
            $2 == "/Applications/Ollama.app/Contents/Resources/ollama" ||
            $2 == "/Applications/Ollama.app/Contents/Resources/llama-server" {
                total += $1
            }
            END { print total + 0 }
        ')
        ollama_rss_mib=$((ollama_rss_kib / 1024))
        if [ "$ollama_rss_kib" -gt 0 ]; then
            ok "ollama RSS" "${ollama_rss_mib} MiB across Ollama processes"
        else
            fail "ollama RSS" "could not read resident process memory"
        fi

        if [ "$resident_mib" -le 0 ]; then
            fail "memory report" "cannot report without a resident model allocation"
        elif [ "$resident_mib" -gt "$MEMORY_WARN_MIB" ] || [ "$ollama_rss_mib" -gt "$MEMORY_WARN_MIB" ]; then
            warn "memory report" "models=${resident_mib} MiB rss=${ollama_rss_mib} MiB — above the retired ${MEMORY_WARN_MIB} MiB figure. INFORMATIONAL: there is no memory gate and no acceptance criterion (constitution v2.1.0); see docs/runbooks/memory-check.md"
        else
            ok "memory report" "models=${resident_mib} MiB rss=${ollama_rss_mib} MiB — reported only; there is no memory gate (constitution v2.1.0)"
        fi

        system_used_raw=$(top -l 1 -s 0 -n 0 2>/dev/null | awk '/^PhysMem:/ {print $2; exit}')
        system_used_mib=$(to_mib "$system_used_raw")
        if [ -n "$system_used_mib" ]; then
            warn "system memory context" "${system_used_mib} MiB shown by top; context only — manual review lives in docs/runbooks/memory-check.md, which has no threshold"
        else
            warn "system memory context" "unavailable from top; context only, never a condition"
        fi
    fi
fi

# --- verdict ----------------------------------------------------------------
verdict "MODEL RUNTIME VERIFIED — $ASSERTIONS assertions, 0 failures" \
        "MODEL RUNTIME BROKEN" "$ASSERTIONS"
exit $?
