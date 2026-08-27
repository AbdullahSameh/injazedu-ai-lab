# Contract — `source_snapshots.profiling_results`

**Producer**: `php artisan lab:profile` (this feature)
**Consumers**: P2, P3, P4, P5, P9 — and `docs/reports/p1-profiling.md`, generated from it
**Status**: in force from the first `lab:profile` run
**Date**: 2026-08-25

This contract exists because of P1 plan §14.3: **a project that needs a bank number reads it from
here and does not re-query the source.** Otherwise every project ends up with its own version of the
truth, and the program loses the ability to say what it measured and when.

The second party is a *future* project, so the shape is fixed now, before there is data in it.

---

## 1. The envelope

```jsonc
{
  "schema_version": 1,               // integer; bump only on a breaking shape change
  "snapshot_taken_at": "2026-08-07", // the fixed snapshot — context, never a gate
  "run_at": "2026-08-25T21:14:03Z",  // when lab:profile executed
  "mysql_version": "9.1.0",
  "source_database_size_mb": 2189.0,
  "queries": { "1": { … }, "2": { … }, … "18": { … } }
}
```

`queries` is keyed by the **§6 query number as a string**, 1–18, matching the file numbering in
`sql/profiling/`. A §6 reference resolves to a file, and a file resolves to a key here.

## 2. One query entry

```jsonc
"3": {
  "file": "03-correct-answer-integrity.sql",
  "title": "correct answer integrity",
  "tables_read": ["questions", "options"],
  "allowlist": "copy",               // "copy" | "profile-only"
  "executed_at": "2026-08-25T21:14:05Z",
  "duration_ms": 4821,
  "row_count": 4,
  "columns": ["correct_count", "questions"],
  "rows": [ { "correct_count": 0, "questions": 137 }, … ]
}
```

`columns` was added during implementation, additively (not a `schema_version` bump — see §6): PostgreSQL's `jsonb` type does not preserve object key order on round-trip, it reorders members by length then lexicographically. `columns` records the query's own column order at write time, verbatim, so the report can render it correctly; `rows` must always be read by column name, never by PHP/JS object-iteration order.

- **`rows` is the result set verbatim** — column names exactly as the query aliased them, values
  unconverted. No renaming, no rounding, no derived percentages. A consumer that wants a rate
  computes it; the record stores what the database returned.
- **`tables_read` is the declaration that was enforced**, not a description written afterwards. It
  is parsed from the file header and every name passed `assertReadable()` before execution
  (FR-002).
- A query that failed records `"error"` with the message and **no `rows` key**. A partial run is
  still persisted — a missing measurement must be visible, not silently absent.

## 3. What a consumer may rely on

| Guarantee | Meaning |
|---|---|
| **Keys are stable** | `"1"`…`"18"` never renumber. A new query is 19, and never displaces one. |
| **`rows` is verbatim** | Column names come from the query's own aliases and do not change without `schema_version` changing. |
| **Every run is kept** | One `source_snapshots` row per run. Re-running compares; it never overwrites (FR-006). |
| **The date travels** | `snapshot_taken_at` is on the envelope and on every screen and report. It is **context, never a threshold** — no consumer may gate on it (constitution III). |
| **Nothing here came from a `profile_tables` row** | Queries 15, 16 and 18 read profile-only tables and contribute **counts only**. No row from those tables is stored in this JSON or anywhere else. |

## 4. What a consumer must NOT do

- **Do not re-query the source for a number that is here.** That is the whole point (§14.3).
- **Do not read `docs/reports/p1-profiling.md` programmatically.** It is *generated* from this JSON
  and is for humans. The JSON is authoritative; the prose is a view.
- **Do not treat a missing key as zero.** Missing means not measured or failed — a different fact.

## 5. The three entries that gate downstream code

Named here so a consumer knows which numbers carry a decision rather than a datum:

| Query | Feeds | Decision it forces |
|---|---|---|
| 3 + 4 | `answer_key_state`, and P2 | The meaning of multi-key. Until it is recorded, `answer_key_state` stays `pending` (FR-061) |
| 15 + 16 | P5, P6 | Which table records enrolment — `course_user` or `course_order` |
| 3 | P1 scope, P2 ordering | `correct_count = 0` above **2%** stops P2's dedup track and reopens P1's remaining scope |

Everything else in the pack is recorded and read, and **blocks nothing** — including
`has_description` (< 30% ⇒ P9's explanation track starts from zero), `has_img_tag` (> 10% ⇒ the
media track becomes a declared sub-project), and `long_stimulus` (large ⇒ §8 is a core requirement,
not an add-on). Those change *later* projects' scope, not this one's code.

## 6. Changing this contract

Adding a query is not a breaking change: add key 19. Renaming a column inside `rows`, restructuring
the envelope, or changing what `tables_read` means **is**, and requires `schema_version: 2` plus a
note here. Silently reshaping the JSON breaks a consumer that does not exist yet and cannot complain.
