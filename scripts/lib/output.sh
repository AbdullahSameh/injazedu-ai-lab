# scripts/lib/output.sh — shared output helpers for the P0 verification scripts.
#
# Implements the line format defined in specs/001-lab-foundation-bootstrap/contracts/:
#   [ OK ] <condition> <value>          — condition passed
#   [FAIL] <condition> <value>          — condition failed (blocking)
#          <remediation line(s)>        — indented detail following a [FAIL]
#   [WARN] <condition> <value>          — non-blocking warning
# plus a failure counter and a final verdict line.
#
# Output is English only (FR-028). Quiet mode (OUTPUT_QUIET=1, set by the sourcing
# script when it parses --quiet) suppresses per-condition lines; the verdict and
# die() messages are never suppressed.
#
# ---------------------------------------------------------------------------
# SHELL CONSTRAINT — bash 3.2 ONLY (research R2, measured 2026-08-20)
#
# The only bash on the target machine is /bin/bash 3.2.57. There is no Homebrew
# bash. Anything sourced from this file MUST remain bash 3.2 compatible.
#
# Forbidden constructs (bash 4+; do not reintroduce):
#   - associative arrays        declare -A
#   - mapfile / readarray
#   - case conversion           ${var,,}  ${var^^}
#   - combined redirection      &>>
#   - indirect prefix expansion ${!prefix@}  ${!prefix*}
# Use parallel indexed arrays, tr, and explicit >> file 2>&1 instead.
# ---------------------------------------------------------------------------

# Failure counter, incremented by fail(). Read by verdict().
FAILURES=0

# Set to 1 by the sourcing script when --quiet is parsed.
OUTPUT_QUIET=${OUTPUT_QUIET:-0}

# ok <condition> <value>
ok() {
    [ "$OUTPUT_QUIET" -eq 1 ] && return 0
    printf '[ OK ] %-22s %s\n' "$1" "$2"
}

# fail <condition> <value> — increments the failure counter.
fail() {
    FAILURES=$((FAILURES + 1))
    [ "$OUTPUT_QUIET" -eq 1 ] && return 0
    printf '[FAIL] %-22s %s\n' "$1" "$2"
}

# warn <condition> <value> — non-blocking; does not touch the counter.
warn() {
    [ "$OUTPUT_QUIET" -eq 1 ] && return 0
    printf '[WARN] %-22s %s\n' "$1" "$2"
}

# note <line> — indented detail line, used for remediation text after a [FAIL].
note() {
    [ "$OUTPUT_QUIET" -eq 1 ] && return 0
    printf '       %s\n' "$1"
}

# die <message> — the check itself cannot run. Prints to stderr and exits 2
# (contract: exit 2 = cannot-run, never treated as a pass). Never suppressed.
die() {
    printf 'ERROR: %s\n' "$1" >&2
    exit 2
}

# verdict <pass-message> <fail-label> <total-conditions>
# Prints the final verdict line (never suppressed) and returns 0 on pass, 1 on
# blocking failure, so the caller can `exit $(verdict ...)` — or capture it.
verdict() {
    if [ "$FAILURES" -eq 0 ]; then
        printf '%s\n' "$1"
        return 0
    fi
    printf '%s — %d of %d conditions blocking\n' "$2" "$FAILURES" "$3"
    return 1
}
