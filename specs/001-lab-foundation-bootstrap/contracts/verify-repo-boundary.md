# Contract — `scripts/verify-repo-boundary.sh`

**Implements**: FR-015 (proving FR-010…FR-013) · **Gates**: SC-003, SC-004 · **Phase**: المرحلة 1

## Invocation

```text
scripts/verify-repo-boundary.sh
```

## Exit codes

| Code | Meaning |
|---|---|
| `0` | Every category behaves as required, including the inverted case. |
| `1` | At least one category is wrong. |
| `2` | Could not run (not a git repository). |

## Output contract

One line per category, showing the matching rule so a failure is diagnosable (research R7):

```text
[ OK ] env file          apps/lab/.env              ignored by .gitignore:3
[ OK ] env template      .env.example               NOT ignored  (inverted — correct)
[ OK ] plain dump        backup.sql                 ignored by .gitignore:12
...
[ OK ] tracked noise     .DS_Store                  not tracked
[ OK ] snapshot dir      data/snapshots/            empty except .gitkeep

BOUNDARY VERIFIED — 10 categories, 1 inverted case, 0 failures
```

## Assertions

| # | Category | Path probed | Expected `check-ignore` exit |
|---|---|---|---|
| 1 | Environment files | `apps/lab/.env` | `0` |
| 2 | **Environment template** | `.env.example` | **`1`** — inverted |
| 3 | Plain dumps | `backup.sql`¹ | `0` |
| 4 | Compressed dumps | `backup.sql.gz` | `0` |
| 5 | Binary dumps | `lab.dump` | `0` |
| 6 | PHP dependencies | `apps/lab/vendor/x` | `0` |
| 7 | JS dependencies | `apps/lab/node_modules/x` | `0` |
| 8 | Python environment | `apps/ai-service/.venv/x` | `0` |
| 9 | Generated storage | `storage/documents/x` | `0` |
| 10 | Application logs | `apps/lab/storage/logs/x` | `0` |
| 11 | OS noise | `.DS_Store` | `0` |

Plus two state assertions that `check-ignore` cannot express:

| # | Assertion | Method |
|---|---|---|
| 12 | `.DS_Store` is no longer **tracked** (FR-013) | `git ls-files --error-unmatch .DS_Store` must fail |
| 13 | `data/snapshots/` holds nothing but `.gitkeep` (FR-012) | directory listing |

¹ **Probe path corrected during implementation** (was `data/snapshots/test.sql`): the mandated
`data/snapshots/*` containment rule (FR-012) also covers that path, so removing `*.sql` alone would
leave the category ignored and test case 2 could never fire. Probing `backup.sql` at the repository
root isolates the plain-dump rule.

## Behavioural guarantees

- **Creates and deletes nothing.** `git check-ignore` operates on path strings, so no forbidden file
  is ever materialised — important for a check whose purpose is keeping data out of the repository.
- **Does not touch the index.** No `git add`, no `git rm`, no stashing.
- **The inverted case is mandatory.** A rule set that ignores everything would pass 11 of 13
  assertions; only assertion 2 catches it.

## Test cases

| Given | Expect |
|---|---|
| Correct `.gitignore` | exit `0`, 13/13 — **SC-003** |
| `*.sql` rule removed | exit `1`, category 3 `[FAIL]` |
| `!.env.example` negation removed | exit `1`, category 2 `[FAIL]` — the inverted case earning its place |
| `.DS_Store` still tracked (**current repo state before FR-013**) | exit `1`, assertion 12 `[FAIL]` |
| A `.sql` file placed in `data/snapshots/` | exit `1`, assertion 13 `[FAIL]` |
| Run outside a git repository | exit `2` |
