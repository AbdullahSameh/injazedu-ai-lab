# Contract — the source access surface and the stack command

**Implements**: FR-001…FR-010 · **Gates**: SC-001…SC-008 · **Phases**: §3.2 + المرحلة 11

Two contracts, both because a **second party** depends on them: P1's ETL will call the source access
surface every time it reads or copies a row, and the operator drives the stack command daily.

---

## 1. The source access surface

### Configuration shape — `apps/lab/config/lab.php`

```php
// What may be COPIED INTO the Lab database. Unchanged since 002.
'source_tables' => [
    'categories', 'courses', 'chapters', 'lectures', 'quizzes', 'sections',
    'questions', 'options', 'quiz_files', 'results', 'question_result',
],

// Additionally READABLE as aggregates for §6 profiling. NEVER copied.
// Added 2026-08-23 (P0 §3.2) so §6 queries 15, 16 and 18 can run in P1.
'profile_tables' => [
    'course_user', 'course_order', 'orders', 'user_roles', 'roles', 'book_course',
],
```

Everything outside both lists is refused by name. Fifteen names remain forbidden in both directions,
`users` among them — which is why `lab:health` check 10 is unaffected by the split.

### `App\Support\SourceReader`

| Method | Behaviour |
|---|---|
| `table(string $table): Builder` | Query builder for a **readable** table (either list). Throws `SourceTableNotAllowed` naming the table otherwise. |
| `count(string $table): int` | Row count of a readable table. |
| `assertReadable(string $table): void` | Throws unless the table is in `source_tables ∪ profile_tables`. |
| `assertCopyable(string $table): void` | Throws unless the table is in `source_tables`. **P1's ETL calls this before writing anything.** |

**Behavioural guarantees**

- **Read and copy are separate questions, asked separately.** The union is never usable as a copy
  check — that is the whole safety property of the split. A caller that wants to store rows asks
  `assertCopyable`; a caller that wants to count asks `assertReadable`.
- **Refusal names the table.** A bare "not allowed" is a defect: the message is the thing that tells a
  future reader which list to look at.
- **Guards 1 and 2 are untouched.** No write target on the connection, and a query listener that
  throws on any non-read statement. The split changed guard 3 only (ADR-021, revised 2026-08-23).
- **The backstop is the schema, not the query.** `NoPiiInLabSchemaTest` asserts no Lab column can hold
  personal data. It is indifferent to how wide the read list is, which is why widening it is safe.

### Test obligations

```text
ForbiddenTableRefusalTest    15 names, one assertion each, each refused by name
                             users · book_order · coupons · certificates · complaints
                             complaint_responses · social_providers · personal_access_tokens
                             paymob_logs · zoom_users · audits · telescope_entries
                             google_oauth_tokens · failed_jobs · settings

SourceTableAllowlistTest     the 6 profile tables are READABLE
                             the 6 profile tables are NOT COPYABLE
                             the 11 source tables are both

ReadOnlyGuardTest            unchanged — each of the three layers still refuses alone
```

---

## 2. `scripts/lab-stack.sh`

```text
scripts/lab-stack.sh up | down | status
```

**Exit codes**: `0` every component is in the requested state · `1` at least one is not · `2` cannot
run. `#!/bin/bash`, **bash 3.2 only**, `scripts/lib/output.sh` for the line format, English output.

```text
[ OK ] postgres          container injazedu_lab_postgres healthy on 127.0.0.1:5433
[ OK ] ai-service        started, pid 17677, 127.0.0.1:8001
[ OK ] queue worker      started, pid 41853
[ OK ] model runtime     Ollama 0.32.15 reachable (not started by this script)
STACK UP - run: php artisan lab:health
```

**Behavioural guarantees**

- **Idempotent** (FR-008): `up` twice leaves exactly one worker and one service. Ownership is a pid
  file **plus** a liveness check on the recorded pid — a stale file after a reboot must not block a
  start, and must never cause an unrelated process to be adopted.
- **It starts nothing it does not own** (FR-009). Ollama is the official macOS app (§8 item K); it is
  checked and reported, never started. No login item, no launchd agent, no supervisor.
- **It reports; it does not verify** (FR-010). `php artisan lab:health` is the verdict, and the last
  line says so.
- **`down` is symmetric**: it stops what `up` started and leaves the model runtime alone.
- **`status` has no side effects** and is safe to run at any time.
