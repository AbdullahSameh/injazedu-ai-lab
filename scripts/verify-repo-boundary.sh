#!/bin/bash
# scripts/verify-repo-boundary.sh — prove the committed boundary holds.
#
# Implements FR-015 (proving FR-010…FR-013). Gates SC-003, SC-004.
# Contract: specs/001-lab-foundation-bootstrap/contracts/verify-repo-boundary.md
#
# Assertions 1–14: one `git check-ignore` per category. Eleven must be ignored
# (exit 0); three are INVERTED and must NOT be ignored (exit 1) — the two env
# templates and the repository's committed SQL. A .gitignore that swallows any of
# those is a defect only an inverted case can catch: the file is written, `git
# add` reports nothing, the working tree looks clean, and it never enters history.
# Assertion 15: .DS_Store is no longer tracked (FR-013).
# Assertion 16: data/snapshots/ contains nothing but .gitkeep (FR-012).
# Assertions 17–19 (003-service-health-guardrails, FR-026): the service's env
# file is ignored, its template is NOT (inverted, like the two existing
# templates), and no .env file's pepper value appears in tracked content.
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
# 12-14: added when the blanket `*.sql` rule was narrowed to dumps. 12 and 13 are
# INVERTED — they are the only assertions that can catch a re-broadening of the
# rule, which would silently un-commit the app's env template and المرحلة 11's
# eighteen profiling queries. 14 proves the location half of the dump rule still
# holds, so narrowing did not open a hole.
CAT_NAME[11]="app env template";   CAT_PATH[11]="apps/lab/.env.example";   CAT_INVERT[11]=1
CAT_NAME[12]="committed sql";      CAT_PATH[12]="sql/profiling/q01.sql";   CAT_INVERT[12]=1
CAT_NAME[13]="dump by location";   CAT_PATH[13]="data/snapshots/x.sql";    CAT_INVERT[13]=0
# 17-18: the service's env pair — same rule as the two templates above.
CAT_NAME[14]="service env file";    CAT_PATH[14]="apps/ai-service/.env";         CAT_INVERT[14]=0
CAT_NAME[15]="service env template"; CAT_PATH[15]="apps/ai-service/.env.example"; CAT_INVERT[15]=1

i=0
while [ "$i" -lt 16 ]; do
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
            rule=$(git check-ignore -v "$path" 2>/dev/null | cut -f1 | cut -d: -f1-2)
            fail "$name" "$path  ignored by $rule — this path must stay committable"
            note "remediation: narrow or negate the rule above; do not rename the file"
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

# --- assertion 19: the pepper appears in no tracked content (FR-026) --------
# Reads the value out of the untracked env file; never prints it. An empty
# value (pepper not yet generated) is a skip, not a pass — the assertion only
# means something once T017 has run.
pepper=$(sed -n 's/^STUDENT_REF_PEPPER=//p' apps/lab/.env 2>/dev/null | tr -d '\r"'"'"' ')
if [ -z "$pepper" ]; then
    note "pepper containment" "STUDENT_REF_PEPPER is empty in apps/lab/.env — assertion skipped"
elif git grep -q -F "$pepper" -- . >/dev/null 2>&1; then
    fail "pepper containment" "the pepper value appears in tracked content"
    note "remediation: remove it from the tracked file and rotate nothing —"
    note "the pepper is never regenerated; scrub the file and recommit"
else
    ok "pepper containment" "STUDENT_REF_PEPPER absent from all tracked content"
fi

# --- verdict -----------------------------------------------------------------
verdict "BOUNDARY VERIFIED — 13 categories, 4 inverted cases, pepper contained, 0 failures" \
        "BOUNDARY BROKEN" 19
exit $?
