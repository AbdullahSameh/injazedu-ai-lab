#!/bin/bash
# scripts/preflight-check.sh — machine safety gate (المرحلة 0)
#
# Implements FR-001…FR-008 per specs/001-lab-foundation-bootstrap/contracts/preflight-check.md
# Exit codes: 0 = pass · 1 = blocking failure · 2 = cannot-run. There is deliberately
# no --force flag: no flag skips a check.
#
# SHELL CONSTRAINT — bash 3.2 ONLY (research R2). Forbidden: declare -A, mapfile,
# ${var,,}, &>>, ${!prefix@}. See scripts/lib/output.sh for the full list.

set -u

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
REPO_ROOT=$(cd "$SCRIPT_DIR/.." && pwd)

# shellcheck disable=SC1091
. "$SCRIPT_DIR/lib/output.sh" || {
    printf 'ERROR: cannot source scripts/lib/output.sh\n' >&2
    exit 2
}

usage() {
    printf 'Usage: %s [--quiet]\n' "$0"
    printf '  --quiet   suppress per-condition lines; print only the verdict\n'
    printf 'Exit: 0 pass / 1 blocking failure / 2 cannot-run. No flag skips a check.\n'
}

while [ $# -gt 0 ]; do
    case "$1" in
        --quiet)
            OUTPUT_QUIET=1
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            printf 'ERROR: unknown argument: %s\n' "$1" >&2
            usage >&2
            exit 2
            ;;
    esac
    shift
done

# --- Conditions 1–5 run below in fixed contract order, each independently readable ---

# Condition 1 — disk encryption (FR-002). An unparseable result is treated as Off,
# never as a pass.
if ! command -v fdesetup >/dev/null 2>&1; then
    die "fdesetup not found in PATH — cannot determine encryption state"
fi
fv_status=$(fdesetup status 2>/dev/null)
case "$fv_status" in
    "FileVault is On."*)
        ok "encryption" "On"
        ;;
    "Encryption in progress"*)
        ok "encryption" "Encryption in progress"
        ;;
    "FileVault is Off."*)
        fail "encryption" "Off"
        note "The production snapshot is on an unencrypted disk."
        note "Remediation: System Settings > Privacy & Security > FileVault > Turn On."
        note "Store the recovery key off this machine, then record its location in"
        note "docs/runbooks/safety.md."
        note "BLOCKING: no further phase may proceed (P0 §8 Item A)."
        ;;
    *)
        fail "encryption" "unparseable ('$fv_status') — treated as Off"
        note "fdesetup returned an unrecognised state; an unreadable answer is never a pass."
        note "Remediation: verify FileVault in System Settings > Privacy & Security."
        note "BLOCKING: no further phase may proceed (P0 §8 Item A)."
        ;;
esac

# Condition 2 — recovery-key custody (FR-006a). Blocking input: docs/runbooks/safety.md.
# Fails while the placeholder token is present, while the location is empty, or while
# attested_on does not parse as an ISO date.
SAFETY_FILE="$REPO_ROOT/docs/runbooks/safety.md"
[ -r "$SAFETY_FILE" ] || die "cannot read docs/runbooks/safety.md — custody record missing"

rk_location=$(sed -n 's/^recovery_key_location:[[:space:]]*//p' "$SAFETY_FILE" | sed -n '1p')
rk_attested_on=$(sed -n 's/^attested_on:[[:space:]]*//p' "$SAFETY_FILE" | sed -n '1p')

rk_fail=0
case "$rk_location" in
    *"<UNSET"*|"") rk_fail=1 ;;
esac
case "$rk_attested_on" in
    *"<UNSET"*) rk_fail=1 ;;
esac
case "$rk_attested_on" in
    [0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9])
        if ! date -j -f "%Y-%m-%d" "$rk_attested_on" >/dev/null 2>&1; then
            rk_fail=1
        fi
        ;;
    *)
        rk_fail=1
        ;;
esac

if [ "$rk_fail" -eq 0 ]; then
    ok "recovery key" "off-machine, attested $rk_attested_on"
else
    fail "recovery key" "location='$rk_location' attested_on='$rk_attested_on'"
    note "The FileVault recovery-key custody record is missing, holds its placeholder,"
    note "or carries an attestation date that does not parse (YYYY-MM-DD)."
    note "Remediation: store the recovery key off this machine, then record its location"
    note "and attestation date in docs/runbooks/safety.md. Never record the key itself."
fi

# Condition 3 — sync exposure (FR-003, research R1). Resolve the repo's real path and
# test it against known cloud-sync roots. When iCloud Desktop & Documents sync is on,
# macOS relocates ~/Desktop and ~/Documents into the iCloud container, so both count
# as sync roots. On a match, the matched root is reported.
repo_real=$(realpath "$REPO_ROOT" 2>/dev/null)
[ -n "$repo_real" ] || die "cannot resolve the repository's real path"

sync_roots="$HOME/Library/Mobile Documents
$HOME/Library/CloudStorage"
for d in "$HOME"/OneDrive* "$HOME"/Dropbox*; do
    if [ -d "$d" ]; then
        sync_roots="$sync_roots
$d"
    fi
done
if [ -d "$HOME/Library/Mobile Documents/com~apple~CloudDocs/Desktop" ]; then
    sync_roots="$sync_roots
$HOME/Desktop
$HOME/Documents"
fi

matched_root=""
while IFS= read -r root; do
    [ -n "$root" ] || continue
    case "$repo_real" in
        "$root"|"$root"/*)
            matched_root="$root"
            break
            ;;
    esac
done <<EOF_ROOTS
$sync_roots
EOF_ROOTS

if [ -z "$matched_root" ]; then
    ok "sync exposure" "$repo_real — outside all sync roots"
else
    fail "sync exposure" "$repo_real — inside $matched_root"
    note "The repository resolves inside a cloud-sync root. Snapshot-adjacent working"
    note "files would upload off this machine."
    note "Remediation: relocate the repository outside the matched root, or disable"
    note "sync for that root, then re-run this check."
fi

# Condition 4 — free disk (FR-004). Threshold 20 GB; both measured and threshold
# values are printed.
FREE_DISK_THRESHOLD_GB=20
free_gb=$(df -g / 2>/dev/null | awk 'NR==2 {print $4}')
case "$free_gb" in
    ''|*[!0-9]*)
        fail "free disk" "unparseable df output — cannot measure free space"
        note "Remediation: run 'df -g /' manually and investigate."
        ;;
    *)
        if [ "$free_gb" -ge "$FREE_DISK_THRESHOLD_GB" ]; then
            ok "free disk" "$free_gb GB (threshold $FREE_DISK_THRESHOLD_GB GB)"
        else
            fail "free disk" "$free_gb GB (threshold $FREE_DISK_THRESHOLD_GB GB)"
            note "The snapshot and Lab stack need headroom this disk does not have."
            note "Remediation: free at least $FREE_DISK_THRESHOLD_GB GB on /, then re-run."
        fi
        ;;
esac

# Condition 5 — container engine (FR-005). Three distinct outcomes with distinct
# remediation: responding / installed but not running / not installed.
if ! command -v docker >/dev/null 2>&1; then
    fail "container engine" "not installed"
    note "No docker CLI found in PATH."
    note "Remediation: install OrbStack (P0 §10's lighter alternative) or Docker Desktop."
elif docker info >/dev/null 2>&1; then
    engine_ctx=$(docker context show 2>/dev/null)
    [ -n "$engine_ctx" ] || engine_ctx="docker"
    ok "container engine" "responding ($engine_ctx)"
else
    fail "container engine" "installed but not running"
    note "The docker CLI is present but the daemon does not respond."
    note "Remediation: start the engine (open -a OrbStack), then re-run this check."
fi

# --- Verdict -----------------------------------------------------------------------

verdict "PREFLIGHT PASSED — machine is safe to hold the snapshot" "PREFLIGHT FAILED" 5
exit $?
