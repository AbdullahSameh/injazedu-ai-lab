#!/bin/bash
# scripts/verify-injazedu-access.sh — prove the source is reachable and the
# eleven allowlisted tables are present and readable.
#
# Implements FR-007 (US1, المرحلة 3). Independent test of Phase 2: passes with
# no application present.
#
# Assertions:
#   1  reachable      MySQL answers a trivial SELECT on 127.0.0.1:3306
#   2  tables         each of the eleven allowlisted tables is readable,
#                     reported individually by name (config/lab.php's
#                     source_tables is generated from this same list)
#   3  bank size      SELECT COUNT(*) FROM questions = 29142 (FR-007)
#
# There is deliberately NO inverted write check. The connection is root with
# an empty password (ADR-021) and MySQL enforces nothing — root CAN write, and
# asserting otherwise would be a lie. The write guarantee is an application
# property, proven by apps/lab's guardrail tests (SC-002…SC-004).
#
# Exit codes: 0 = all assertions pass · 1 = at least one failure ·
#             2 = cannot run (MySQL unreachable).
#
# SHELL CONSTRAINT — bash 3.2 ONLY. No associative arrays, no mapfile, no
# ${var,,}. See scripts/lib/output.sh header for the full forbidden list.

set -u

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/output.sh
. "$SCRIPT_DIR/lib/output.sh"

# --- argument parsing -------------------------------------------------------
for arg in "$@"; do
    case "$arg" in
        --quiet) OUTPUT_QUIET=1 ;;
        *) echo "usage: $0 [--quiet]" >&2; exit 2 ;;
    esac
done

MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_DB=injazedu
EXPECTED_QUESTIONS=29142

# The tables this script confirms are present as InnoDB base tables (checked
# 2026-08-21). Ten are copyable; question_result is profile-only since
# 2026-08-26 (ADR-022) — readable as aggregates, never mirrored. Presence is
# all that is asserted here, so both belong on the list. Keep in sync with
# apps/lab/config/lab.php.
TABLES="categories courses chapters lectures quizzes sections questions options quiz_files results question_result"

# Helper: run SQL against the source. root, empty password — by design.
mysql_s() {
    mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u root --skip-column-names -A "$MYSQL_DB" "$@"
}

ASSERTIONS=3

# --- assertion 1: reachable --------------------------------------------------
alive=$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u root -e "SELECT 1" --skip-column-names 2>/dev/null)
if [ "$alive" != "1" ]; then
    die "MySQL not reachable on ${MYSQL_HOST}:${MYSQL_PORT} — start it (brew services start mysql) and re-run. Cannot prove anything without the source."
fi
ok "reachable" "MySQL answering on ${MYSQL_HOST}:${MYSQL_PORT}"

# --- assertion 2: eleven allowlisted tables readable, each named -------------
missing=0
for table in $TABLES; do
    count=$(mysql_s -e "SELECT COUNT(*) FROM $table" 2>/dev/null)
    if [ -z "$count" ]; then
        fail "table $table" "not readable in $MYSQL_DB"
        note "remediation: the allowlist was measured against the 2026-08-07 snapshot;"
        note "confirm the table exists in the local copy — do not substitute another"
        missing=$((missing + 1))
    else
        ok "table $table" "readable, $count rows"
    fi
done

# --- assertion 3: bank size --------------------------------------------------
qcount=$(mysql_s -e "SELECT COUNT(*) FROM questions" 2>/dev/null)
if [ "$qcount" = "$EXPECTED_QUESTIONS" ]; then
    ok "bank size" "questions = $qcount"
else
    fail "bank size" "questions = ${qcount:-<unreadable>} — expected $EXPECTED_QUESTIONS"
    note "remediation: the expected figure was measured 2026-08-21; a different count"
    note "means the local copy changed — re-measure and update the figure in the same change"
fi

# --- verdict -----------------------------------------------------------------
verdict "SOURCE ACCESS VERIFIED — $ASSERTIONS assertions, 11 tables, 0 failures" \
        "SOURCE ACCESS BROKEN" "$ASSERTIONS"
exit $?
