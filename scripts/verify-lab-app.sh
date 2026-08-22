#!/bin/bash
# scripts/verify-lab-app.sh — prove the Lab application holds its guarantees.
#
# Implements FR-017, FR-020, FR-021 (US2, المرحلة 5). Its header carries the
# assertion list — there is no separate contract document.
#
# Assertions:
#   1  php versions   the app runs on the 8.4 binary AND the machine's linked
#                     php (PATH) is still 8.2 — the other 31 projects undisturbed
#   2  migrations     every migration is applied to the Lab database
#   3  queue probe    LabQueueProbe is dispatched, a worker runs it and EXITS;
#                     the row exists with worker_pid ≠ the dispatching process's
#                     pid (notes.md N4 — a row, not a log line)
#   4  env agreement  the root .env's LAB_DB_PASSWORD equals apps/lab/.env's
#                     DB_PASSWORD — the one value the two files share
#   5  panel auth     GET /admin redirects to login — the panel requires
#                     authentication
#
# Then delegates to `php artisan test` for the three guardrail tests
# (SC-002, SC-004, SC-005); a failing suite fails this script.
#
# Exit codes: 0 = all assertions pass and the suite is green ·
#             1 = at least one failure · 2 = cannot run.
#
# SHELL CONSTRAINT — bash 3.2 ONLY. No associative arrays, no mapfile, no
# ${var,,}. See scripts/lib/output.sh header for the full forbidden list.

set -u

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/output.sh
. "$SCRIPT_DIR/lib/output.sh"

REPO_ROOT=$(cd "$SCRIPT_DIR/.." && pwd)
APP_DIR="$REPO_ROOT/apps/lab"
PHP84=/opt/homebrew/opt/php@8.4/bin/php
SERVE_PORT=8899

# --- argument parsing -------------------------------------------------------
for arg in "$@"; do
    case "$arg" in
        --quiet) OUTPUT_QUIET=1 ;;
        *) echo "usage: $0 [--quiet]" >&2; exit 2 ;;
    esac
done

ASSERTIONS=5

# --- preconditions (exit 2 = cannot run) ------------------------------------
[ -x "$PHP84" ] || die "PHP 8.4 not at $PHP84 — install php@8.4 (never brew link it; notes.md N2)"
[ -f "$APP_DIR/artisan" ] || die "no Laravel application at $APP_DIR"
[ -f "$REPO_ROOT/.env" ] || die "no root .env — copy .env.example and set LAB_DB_PASSWORD"
[ -f "$APP_DIR/.env" ] || die "no apps/lab/.env — copy apps/lab/.env.example and fill it in"

cd "$APP_DIR" || die "cannot enter $APP_DIR"

# --- assertion 1: the app runs 8.4; the machine still links 8.2 --------------
app_php=$("$PHP84" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)
linked_php=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)
if [ "$app_php" = "8.4" ] && [ "$linked_php" = "8.2" ]; then
    ok "php versions" "app on $app_php ($PHP84), machine links $linked_php"
else
    fail "php versions" "app=${app_php:-<none>} linked=${linked_php:-<none>} — expected 8.4 and 8.2"
    note "remediation: run artisan through $PHP84; never brew link php@8.4 (notes.md N2)"
fi

# --- assertion 2: migrations applied -----------------------------------------
pending=$("$PHP84" artisan migrate:status 2>/dev/null | grep -c 'Pending')
if [ "$pending" -eq 0 ]; then
    ok "migrations" "all applied to the Lab database"
else
    fail "migrations" "$pending pending — run: $PHP84 artisan migrate"
fi

# --- assertion 3: queue probe leaves a row, worker pid ≠ dispatcher's --------
"$PHP84" artisan queue:clear >/dev/null 2>&1
dispatcher_pid=$("$PHP84" artisan tinker --execute='App\Jobs\LabQueueProbe::dispatch(); echo getmypid();' 2>/dev/null | tr -d '[:space:]')
if [ -z "$dispatcher_pid" ]; then
    fail "queue probe" "could not dispatch LabQueueProbe"
    note "remediation: QUEUE_CONNECTION must be database; check apps/lab/.env"
else
    "$PHP84" artisan queue:work --once --sleep=0 >/dev/null 2>&1
    # The worker has exited by this point — the assertion is made after, not during.
    probe=$("$PHP84" artisan tinker --execute='
        $row = DB::table("lab_job_probes")->where("id", App\Jobs\LabQueueProbe::PROBE_ID)->first();
        echo $row ? $row->worker_pid : "";' 2>/dev/null | tr -d '[:space:]')
    if [ -z "$probe" ]; then
        fail "queue probe" "no probe row — the worker never ran the job"
        note "remediation: run $PHP84 artisan migrate, then retry"
    elif [ "$probe" = "$dispatcher_pid" ]; then
        fail "queue probe" "worker_pid $probe IS the dispatcher's pid — the job ran inline, not on a worker"
    else
        ok "queue probe" "row present; worker pid $probe ≠ dispatcher pid $dispatcher_pid (worker exited)"
    fi
fi

# --- assertion 4: the two env files agree on the shared password -------------
root_pw=$(sed -n 's/^LAB_DB_PASSWORD=//p' "$REPO_ROOT/.env" | head -n 1)
app_pw=$(sed -n 's/^DB_PASSWORD=//p' "$APP_DIR/.env" | head -n 1)
if [ -n "$root_pw" ] && [ "$root_pw" = "$app_pw" ]; then
    ok "env agreement" "LAB_DB_PASSWORD = DB_PASSWORD"
else
    fail "env agreement" "root .env and apps/lab/.env disagree on the Lab database password"
    note "remediation: the two files share exactly this one value; set them equal"
fi

# --- assertion 5: the panel requires authentication --------------------------
"$PHP84" artisan serve --port="$SERVE_PORT" >/dev/null 2>&1 &
serve_pid=$!
sleep 3
panel_code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$SERVE_PORT/admin" 2>/dev/null)
panel_location=$(curl -s -D - -o /dev/null "http://127.0.0.1:$SERVE_PORT/admin" 2>/dev/null | grep -i '^location:' | tr -d '\r')
kill "$serve_pid" >/dev/null 2>&1
wait "$serve_pid" 2>/dev/null

if [ "$panel_code" = "302" ] && printf '%s' "$panel_location" | grep -qi 'login'; then
    ok "panel auth" "/admin redirects to login (HTTP $panel_code)"
else
    fail "panel auth" "GET /admin returned HTTP ${panel_code:-<none>} ${panel_location:+($panel_location)} — expected a redirect to login"
    note "remediation: the panel must require authentication; check AdminPanelProvider"
fi

# --- verdict for the shell-provable half -------------------------------------
if ! verdict "LAB APP VERIFIED — $ASSERTIONS assertions, 0 failures" \
             "LAB APP BROKEN" "$ASSERTIONS"; then
    exit 1
fi

# --- delegation: the application-behaviour half ------------------------------
# The guardrail tests (SC-002 read-only, SC-004 allowlist, SC-005 no-PII) are
# application behaviour and are proven by the test runner, not by shell.
printf '%s\n' "--- delegating to artisan test for the guardrail tests ---"
"$PHP84" artisan test || {
    printf '%s\n' "LAB APP BROKEN — guardrail tests failing" >&2
    exit 1
}
