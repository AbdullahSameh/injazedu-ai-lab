# §6 Profiling Query Pack — 18 queries, written, NOT executed

The eighteen queries of core plan **§6.1** (bank queries 1–12) and **§6.2** (behavioural queries
13–18), one runnable `.sql` file each, numbering preserved so a §6 reference resolves to a file.
Every query is **verbatim** from the program plan — none was rewritten or "cleaned up", because a
rewritten query silently answers a different question than the one §13's estimates were built on.

Written 2026-08-23 (P0 004). **No query in this pack has been executed against the source** — that
is P1 المرحلة 1's first act, and this increment must leave zero rows read beyond what `lab:health`
already reads and zero rows written (FR-021).

## Status: nothing is blocked

With the copy/profile allowlist split of 2026-08-23 (P0 §3.2, ADR-021 revised), **all eighteen
queries can execute in P1 without exception**. Queries 15, 16 and 18 were blocked before the split
by a rule written about *copying*, not reading; their tables now sit on `profile_tables`
(`apps/lab/config/lab.php`) and may be **read as counts, never copied into the Lab**. No file in
this pack carries a blocked-query warning, because no query is blocked.

## The pack

| # | File | Tables read | Allowlist |
|---|------|-------------|-----------|
| 1 | `01-bank-size.sql` | `questions` | copy |
| 2 | `02-options-per-question.sql` | `questions`, `options` | copy |
| 3 | `03-correct-answer-integrity.sql` | `questions`, `options` | copy |
| 4 | `04-points-distribution.sql` | `options` | copy |
| 5 | `05-option-order-ties.sql` | `options` | copy |
| 6 | `06-description-hint-fill.sql` | `questions` | copy |
| 7 | `07-general-vs-course-quizzes.sql` | `quizzes` | copy |
| 8 | `08-questions-per-quiz.sql` | `quizzes`, `sections`, `questions` | copy |
| 9 | `09-html-and-media-in-stems.sql` | `questions` | copy |
| 10 | `10-quiz-files-placement.sql` | `quiz_files` | copy |
| 11 | `11-literal-duplicates-md5.sql` | `questions` | copy |
| 12 | `12-sections-shared-stimulus.sql` | `sections` | copy |
| 13 | `13-answer-data-volume.sql` | `question_result` | copy |
| 14 | `14-answers-per-question-buckets.sql` | `question_result` | copy |
| 15 | `15-enrolment-source-course-user-vs-order.sql` | `course_user`, `course_order`, `orders` | **profile-only** |
| 16 | `16-course-user-roles.sql` | `course_user`, `user_roles`, `roles` | **profile-only** |
| 17 | `17-telegram-channel-coverage.sql` | `courses` | copy |
| 18 | `18-book-course-abandoned.sql` | `book_course` | **profile-only** |

"Copy" = every table is also on `source_tables` (readable **and** copyable into the Lab).
"Profile-only" = readable as counts/aggregates only — the rows stay in the source, always
(`assertCopyable()` refuses these names).

## How P1 runs it

Through the Laravel command, **never a direct `mysql` client** — a direct client connects as `root`
with no listener and no allowlist, which steps outside all three read-only layers (P1 plan §3.1
Decision 1):

```sh
php artisan lab:profile             # all eighteen, in order
php artisan lab:profile --query=3   # one file
php artisan lab:profile --dry-run   # list the files and their declared tables, execute nothing
```

The command reads the **Tables read** column of the table above as a declaration: every name goes
through `SourceReader::assertReadable()` before its file executes, and one refusal stops the run and
names the table. Results are persisted to `source_snapshots.profiling_results` (JSONB) and
`docs/reports/p1-profiling.md` is generated from that JSON — so the report and the data can never
drift.

Every query is a bare `SELECT`; nothing in the pack writes anything. Queries 13–14 read
`question_result`, which carries `user_id` — the counts it returns name no student, but treat its
output like the others: aggregate numbers for the report, not rows to import. The ETL that copies
rows is a separate P1 concern and must go through `App\Support\SourceReader::assertCopyable()`,
never through these files.
