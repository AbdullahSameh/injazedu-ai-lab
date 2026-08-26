# Phase 0 Notes — P1 Profiling & Question Mirror

**Date**: 2026-08-25 · **Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md)

Findings from reading the delivered P0 code and `docs/schema/injazedu-db-schema.md`. **No profiling
query was executed** — the first run of the §6 pack belongs to implementation, not to planning
(spec FR-001, and P0 004 FR-021 left the pack deliberately unexecuted).

Only findings that **change what gets built** are recorded. Seven do.

---

## N1 — Guard 2 rejects the query files exactly as they are written

`AppServiceProvider::boot()` registers `beforeExecuting` on the `injazedu` connection and takes the
first token of the statement:

```php
$keyword = strtoupper(strtok(ltrim($query), " \t\n\r("));
if (! in_array($keyword, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'], true)) { throw …; }
```

Every file in `sql/profiling/` opens with a `--` comment header. `ltrim` removes whitespace only, so
the first token of `file_get_contents('03-correct-answer-integrity.sql')` is `--`, and guard 2
throws `ReadOnlyViolation` on a pure `SELECT`.

**Consequence**: `lab:profile` MUST strip leading comment and blank lines before handing the
statement to `DB::select()`. This is not cosmetic — without it, the command fails on file 1 and the
failure looks like a security violation rather than a parser detail.

**Verified while I was in there**, because both would break the same call:

- All eighteen files are a **single statement**. Files 15, 16 and 18 contain a second `;`, but it is
  inside the English prose of the header comment, not a second query.
- All eighteen begin with `SELECT` once the header is stripped. **No file uses a `WITH` CTE** —
  which matters, because guard 2 would reject `WITH` too. Any future query file must respect the
  same four-keyword list.

---

## N2 — `NoPiiInLabSchemaTest` fails the moment the mirror exists, and misses the column that matters

The delivered test (`apps/lab/tests/Feature/NoPiiInLabSchemaTest.php`):

```php
private const FORBIDDEN_COLUMNS = ['email', 'phone', 'mobile', 'id_number', 'national_id', 'name'];
```

applied to every table that is not on a nine-name framework allowlist.

Two problems, in opposite directions:

1. **Too broad.** `name` is forbidden everywhere, but four mirror tables need it —
   `source_categories.name`, `source_courses.name`, `source_quizzes.name`, `source_sections.name`.
   These are category and quiz titles, not people. The test's own docblock already states the
   narrower intent: *"or name on a **behavioural** table"*. The implementation never expressed it.
2. **Too narrow.** `user_id` — the single column FR-011 and constitution III are actually about — is
   **not in the list at all**. The test as delivered would pass a mirror that stored `user_id` on
   all 1.1 million behavioural rows.

**Consequence**: the test is rewritten, not extended. See Open Question 1 in the plan — relaxing
`name`, even to match the stated intent, touches a security assertion.

---

## N3 — Two source tables have no soft delete, so `source_deleted_at` can never populate

| Source table | `deleted_at`? | Mirror consequence |
|---|---|---|
| `quiz_files` | **No** (§9 item 5 — deletion is permanent) | `source_media.source_deleted_at` is structurally always NULL |
| `question_result` | **No** | n/a since 2026-08-26 — answer events are aggregated, not mirrored (ADR-022) |

`results` **does** carry `deleted_at`, and `question_result` does not — so a soft-deleted attempt
keeps its answer rows, and an answer row alone cannot tell you its attempt was deleted. **Exclusion
must go through `results`.** This is exactly why the derived statistics are stored at two scopes:
`active` joins `results` and filters `deleted_at IS NULL`, `all` does not. 71% of attempts are
soft-deleted, so the two differ substantially and P3 must say which it means.

`quiz_files.path` is also nullable, so a media row can exist with no path at all — on top of
`path_unverified` (FR-035), which is about a path that exists but may point nowhere.

FR-010 still puts the column on every table for uniformity. The migrations say **why** it is
permanently NULL on these two, so a reader does not go looking for the bug.

---

## N4 — The `sorte_order` typo is on four tables, not one

| Table | Column as it exists |
|---|---|
| `categories` | `sorte_order` (sic) |
| `courses` | `sorte_order` |
| `chapters` | `sorte_order` |
| `lectures` | `sorte_order` |
| `quizzes` | `sort_order` — **spelled correctly** |

So "copy `sorte_order` as `sort_order`" is a **per-table** mapping, not a global rule, and a blanket
rename would silently produce a NULL column on `source_quizzes`.

---

## N5 — The table declaration exists twice; pick one and test the other against it

Each `.sql` file carries its own header —

```sql
-- Tables read : course_user, course_order, orders
-- Allowlist   : PROFILE-ONLY …
```

— and `sql/profiling/README.md` carries the same information as an eighteen-row table. FR-002 names
the README as the source.

**Decision**: parse the **file header**, because the declaration then travels with the query and a
new file cannot exist without one — which is the property FR-002 is protecting. A test asserts the
eighteen headers agree with the README table, so the two cannot drift. A file with no parseable
header is a hard failure, not a default-to-empty.

---

## N6 — Only `options.order` defaults to 0

| Column | Default |
|---|---|
| `options.order` | **0** — repeats constantly; this is §5.2's whole problem |
| `sections.order` | 1 |
| `questions.order` | 1 |

Ties remain *possible* on sections and questions, but they are not defects there and get no error
code. `OPTION_ORDER_TIE` stays specific to options, as §5.2 says. The two-key sort
(`` ORDER BY `order` ASC, id ASC ``) is applied to all three anyway, because stable ordering costs
nothing and unstable ordering is invisible until it corrupts something.

---

## N7 — Column facts that contradict a loose reading of the plan

- `courses.description` is **NOT NULL** and `courses.category_id` is **NOT NULL**. The spec copies
  courses as metadata only and omits `description`; that is a choice, not an oversight, and the
  migration comment says so.
- `sections` has **no `slug`** — the spec's `source_sections` correctly does not ask for one.
- `questions` has **no status column** (§9 item 10). The Lab's status is the only status. Nothing in
  the mirror may imply otherwise.
- `orders` (profile-only) carries `customer_name`, `customer_email`, `customer_phone`. Query 15
  reads it. This is the sharpest illustration of why `assertReadable` and `assertCopyable` are
  separate methods: that table is legitimately readable as a count and would be a serious leak if
  it were ever copyable.

---

## N8 — The baseline this project must not break

Measured 2026-08-23 at the close of P0, re-stated here because every phase is checked against it:

```text
php artisan lab:health   10/10, exit 0, 7.058 s cold
Lab database             8,398 kB, 12 tables
PHP                      8.4.2 at /opt/homebrew/opt/php@8.4/bin/php — never brew link
All SQL                  runs in-container (host psql 14.18 vs server 17.11)
```
