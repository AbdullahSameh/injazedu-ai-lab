#!/bin/bash
# scripts/verify-repo-boundary.sh — prove the committed boundary holds.
#
# Implements FR-015 (proving FR-010…FR-013). Gates SC-003, SC-004.
# Contract: specs/001-lab-foundation-bootstrap/contracts/verify-repo-boundary.md
#
# Assertions 1–11: one `git check-ignore -v` per forbidden category. Ten must be
# ignored (exit 0); assertion 2 (.env.example) is INVERTED — it must NOT be
# ignored (exit 1), because a .gitignore that swallows the template is a defect
# only this case can catch.
# Assertion 12: .DS_Store is no longer tracked (FR-013).
# Assertion 13: data/snapshots/ contains nothing but .gitkeep (FR-012).
#
# Behavioural guarantees: creates and deletes nothing, does not touch the index.
# check-ignore operates on path strings, so no forbidden file is materialised.
#
# Exit codes: 0 = all 13 assertions pass · 1 = at least one failure · 2 = cannot
# run (not a git repository).
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

# --- precondition: must run inside a git repository -------------------------
REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null) \
    || die "not a git repository — the boundary cannot be checked"
cd "$REPO_ROOT" || die "cannot enter repository root $REPO_ROOT"

# --- assertions 1–11: one representative path per category -------------------
# Parallel indexed arrays (bash 3.2 has no associative arrays).
CAT_NAME[0]="env file";            CAT_PATH[0]="apps/lab/.env";            CAT_INVERT[0]=0
CAT_NAME[1]="env template";        CAT_PATH[1]=".env.example";             CAT_INVERT[1]=1
CAT_NAME[2]="plain dump";          CAT_PATH[2]="backup.sql";               CAT_INVERT[2]=0
CAT_NAME[3]="compressed dump";     CAT_PATH[3]="backup.sql.gz";            CAT_INVERT[3]=0
CAT_NAME[4]="binary dump";         CAT_PATH[4]="lab.dump";                 CAT_INVERT[4]=0
CAT_NAME[5]="php dependencies";    CAT_PATH[5]="apps/lab/vendor/x";        CAT_INVERT[5]=0
CAT_NAME[6]="js dependencies";     CAT_PATH[6]="apps/lab/node_modules/x";  CAT_INVERT[6]=0
CAT_NAME[7]="python environment";  CAT_PATH[7]="apps/ai-service/.venv/x";  CAT_INVERT[7]=0
CAT_NAME[8]="generated storage";   CAT_PATH[8]="storage/documents/x";      CAT_INVERT[8]=0
CAT_NAME[9]="application logs";    CAT_PATH[9]="apps/lab/storage/logs/x";  CAT_INVERT[9]=0
CAT_NAME[10]="os noise";           CAT_PATH[10]=".DS_Store";               CAT_INVERT[10]=0

i=0
while [ "$i" -lt 11 ]; do
    name=${CAT_NAME[$i]}
    path=${CAT_PATH[$i]}
    invert=${CAT_INVERT[$i]}

    # Truth value comes from plain `git check-ignore -q`: exit 0 = ignored,
    # 1 = not ignored, 128 = misuse. Verbose mode (-v) is NOT used for the
    # decision — on git 2.45 a path matched by a negation rule (e.g.
    # !.env.example) exits 0 with the negation shown, even though the path is
    # NOT ignored. -v is only consulted afterwards, to name the matching rule.
    git check-ignore -q "$path" 2>/dev/null
    ci_rc=$?

    if [ "$invert" -eq 1 ]; then
        if [ "$ci_rc" -eq 1 ]; then
            ok "$name" "$path  NOT ignored  (inverted — correct)"
        else
            fail "$name" "$path  ignored — the template must stay committable"
            note "remediation: add the negation !.env.example to .gitignore"
        fi
    else
        if [ "$ci_rc" -eq 0 ]; then
            # Keep only source:line (e.g. .gitignore:12) for the report.
            rule=$(git check-ignore -v "$path" 2>/dev/null | cut -f1 | cut -d: -f1-2)
            ok "$name" "$path  ignored by $rule"
        else
            fail "$name" "$path  NOT ignored — category is unprotected"
            note "remediation: add a matching rule for this category to .gitignore"
        fi
    fi
    i=$((i + 1))
done

# --- assertion 12: .DS_Store is no longer tracked (FR-013) -------------------
if git ls-files --error-unmatch .DS_Store >/dev/null 2>&1; then
    fail "tracked noise" ".DS_Store  still tracked in the index"
    note "remediation: git rm --cached .DS_Store  (do not rewrite history)"
else
    ok "tracked noise" ".DS_Store  not tracked"
fi

# --- assertion 13: data/snapshots/ holds nothing but .gitkeep (FR-012) -------
snap_listing=$(ls -A data/snapshots/ 2>/dev/null | grep -v '^\.gitkeep$')
if [ -z "$snap_listing" ]; then
    ok "snapshot dir" "data/snapshots/  empty except .gitkeep"
else
    fail "snapshot dir" "data/snapshots/  contains: $(printf '%s' "$snap_listing" | tr '\n' ' ')"
    note "remediation: the snapshot is never copied into the repository —"
    note "remove the file; see docs/runbooks/snapshot.md (containment rule)"
fi

# --- verdict -----------------------------------------------------------------
verdict "BOUNDARY VERIFIED — 10 categories, 1 inverted case, 0 failures" \
        "BOUNDARY BROKEN" 13
exit $?
