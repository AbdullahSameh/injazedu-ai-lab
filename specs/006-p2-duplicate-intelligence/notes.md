# Phase 0 — Findings

**Feature**: `006-p2-duplicate-intelligence` · **Date**: 2026-08-27/28
**Method**: read P0/P1 code and migrations; ran read-only `SELECT`s against the loaded
`injazedu_lab` mirror; queried `pg_extension` in both databases. No MySQL access, no writes.

Ten findings. **N1, N2, N3 and N10 change what gets built.** N4–N6 stop a migration or a job from
being written against the plan's prose instead of the measured schema. N7–N9 close open items. N10
is the evidence base for the three operator decisions of 2026-08-28.

---

## N1 — The embedding budget is computed off the wrong key, and is ~5% larger than planned

**This is the finding that most changes the work.** The project plan (§5, Phase 4) and spec FR-036
both say the embed step selects "one representative per `question_with_options_hash`" and that this
yields **~11,416** representatives, so the call count should be "close to 2 × 11,416". Those are two
different grains, and only one of them is 11,416.

Measured over the 28,747 active questions, with a SQL approximation of the normalizer (lower-case,
Alef unification, diacritic and tatweel removal, whitespace collapse):

| Grain | Distinct values | What it is the natural key for |
|---|---|---|
| `md5(raw_text)` | **11,416** | nothing — it is the pre-normalization figure the plan quotes |
| normalized text — `question_text_hash` | **11,094** | **`stem_embedding`** (the question alone) |
| normalized text ⊹ options — `question_with_options_hash` | **12,969** | **`full_embedding`** (question + options) |

`question_with_options_hash` is a **more specific** key than the text hash — two questions sharing a
stem but carrying different option sets are distinct under it. So deduplicating both embeddings by it
produces ~12,969 representatives, not 11,416.

**Consequence.** The honest budget is:

```text
stem_embedding   dedup by question_text_hash          ~11,094 calls
full_embedding   dedup by question_with_options_hash  ~12,969 calls
                                                      ─────────────
                                                      ~24,063 calls
```

against `2 × 28,747 = 57,494` with no deduplication — a **58% saving**. The cascade's whole argument
survives intact; only the number was wrong. But an implementation that did the right thing would
**fail** the acceptance criterion as written, which is why this is corrected in the spec (FR-036,
SC-006, US3 scenario 1) rather than discovered during Phase 4.

**Each embedding deduplicates by its own key.** Sharing one key across both would either recompute
identical stem vectors 1,875 times or hand two questions with different options the same full vector.

The three figures are upper bounds: the real normalizer also strips punctuation, unifies digits and
strips option labels, all of which collapse further. Phase 4 records the measured count.

---

## N2 — The plan's duplicate-group tallies do not reconcile, and the real conflict backlog is ~18% smaller

The spec already flagged that §2.2's numbers did not add up (4,689 groups vs 4,602 + 136 = 4,738;
~1,227 raw conflicts vs a 1,125 no-image count). Measured directly, they now reconcile:

| Measure | Plan | **Measured** |
|---|---|---|
| Duplicate groups (same `raw_text`, active) | 4,689 | **4,689** ✅ |
| Questions in those groups | 22,020 | **22,020** ✅ |
| Redundant rows (60.3%) | 17,331 | **17,331** ✅ |
| Groups with **no** image member | 4,602 | **4,558** |
| Groups **with** an image member | 136 | **131** |
| — of which conflicting | 114 (83.8%) | **96 (73.3%)** |
| Conflicting, no image member | 1,125 (24.4%) | **928 (20.4%)** |
| Conflicting, total | ~1,227 | **1,024** |

4,558 + 131 = 4,689. The group counts now sum correctly.

**Decision 4 holds, and holds on measurement.** Image-bearing groups conflict at **73.3%** against a
**20.4%** base rate — **3.6×**. The effect is smaller than the plan's 83.8%/24.4% but is the same
finding, and it is still the strongest false-positive signal in the data.

**Decision 5 holds, and its number improves.** The backlog after excluding image-bearing groups is
**928**, not ~1,125. At §13.3's 2–5 min that is **31–77 trainer hours**, not 37–94 — still far past
§13.3's 30–60 hours **program-wide**, so ranking by student impact remains the whole design, and §8
item F remains a blocking scheduling commitment. The corrected figure belongs in item F.

**A definitional note that matters.** "Conflicting" here counts groups whose members have more than
one *distinct set of correct-option texts*. A member with no correct option at all contributes a NULL
and does not create a conflict — which is the behaviour FR-093 asks for: an unanswerable question is
broken, not a conflicting key.

**All of these are the pre-normalization floor.** They are measured over `raw_text`. `search_text`
collapses formatting variants, so Phase 3 will find **more** groups and **more** conflicts than these.
The acceptance criteria must not pin these numbers as expected values.

---

## N3 — `/embed` has no batch endpoint, and 24,063 sequential HTTP calls is the actual Phase 4 cost

`apps/ai-service/app/main.py` exposes `POST /embed` taking a single `{"text": ...}` and returning one
vector. `EmbeddingClient.embed()` posts one `input` string to Ollama's `/api/embed` and reads
`embeddings[0]`. **Ollama's own endpoint accepts a list**; the service does not expose that.

So Phase 4 is ~24,063 round trips of Laravel → FastAPI → Ollama. The plan budgets 10–20 minutes,
which requires ~20–40 embeddings/second sustained *including* two HTTP hops per call.

**Decision (ordinary engineering, stated not asked):** build Phase 4 against the existing
single-text endpoint and **measure the first full chunk**. Add a batch variant to the service only if
the measurement demands it. Adding one is a small, contained change — the prefix, normalization and
truncation logic already sit in `EmbeddingClient` and would be applied per item — but it is not
justified before a number says so (constitution IV: "services are added only when justified today").
`import_runs.elapsed_seconds` is what settles it.

---

## N4 — Everything `/embed` returns for truncation already exists; no service change is needed there

The response carries `truncated`, `prompt_eval_count`, `context_length` and
`embedding_config_version` (`main.py`), and `truncated` is computed as
`prompt_eval_count >= context_length` with the window read from `/api/show` and cached
(`embedding.py`). FR-038 and FR-039 read fields that are already there.

The failure modes are already distinct and typed:

| Condition | Status | `error` code |
|---|---|---|
| Zero-norm vector | 502 | `zero_norm_vector` |
| Runtime unreachable | 503 | `ollama_unreachable` |
| Empty/missing text | 422 | `invalid_input` |

FR-040's `EMBEDDING_FAILED` maps onto 502/503; the 422 case is a caller bug, not a data anomaly, and
should throw rather than be recorded as one.

---

## N5 — Column names and enum values the plan gets wrong

Read from the live schema, not from the plan's prose.

| The plan says | The schema has |
|---|---|
| `answer_key_state = 'single_key'` | **`single_correct`** (29,052) · `broken_no_key` (56) · `multi_key` (34) |
| options ordered by `option_index` | ✅ correct — `source_question_options.option_index`, with `source_order` as the raw value |
| "5,582 questions carry an attached image" | 5,582 **rows** over **5,578 questions** — four questions carry two images each |
| `source_media` at question grain | `type ∈ (video, image, audio)`, `attach_level ∈ (section, question)`; the image set is `type='image' AND attach_level='question'` |

**The four two-image questions are why `media_fingerprint` must hash an ordered *list*, not a single
path.** A one-path assumption would be right 99.93% of the time and silently wrong for four questions.

`source_media.path` is **nullable** — zero nulls in this snapshot at question/image grain, but the
fingerprint must define its behaviour for one rather than produce `sha256(null)`.

---

## N6 — `import_runs` requires a snapshot and a `ran_via`; `kind` has room

`import_runs.kind` is `string(20)`, so every P2 kind fits (`p2_derive_text` is the longest at 14).
But two columns are **NOT NULL** with no default and the plan does not mention either:

- `snapshot_id` — `LabDedup` must resolve `SourceSnapshot::latestRun()` and fail with a clear message
  if none exists, exactly as `LabImport` does.
- `ran_via` — `'inline' | 'queue'`. P2's steps run inline by default like `lab:import`, and the
  column records which.

`ImportErrorRecorder`, `ResumeCursor`, `BatchUpsert` and `ImportRunRecorder` are all reusable
unchanged. `ImportErrorCode` is a plain backed enum with `severity()` and `description()` `match`
arms — adding cases means adding an arm to each, and the enum's own docblock says a second list of
these strings anywhere is a defect.

---

## N7 — `NoPiiInLabSchemaTest` needs no exemption. Spec Open Item 5 closes.

The test scans `information_schema.columns` for ten identity-shaped names across **every**
non-framework table, and separately forbids `name` on three named behavioural tables.

`duplicate_reviews.reviewer_id` and `duplicate_eval_pairs.labelled_by` are **not** on the forbidden
list — that list contains `user_id`, not `*_id`. None of the eight P2 tables carries `email`,
`phone`, `name` or any other listed column.

**Consequence:** the eight tables pass the test **unchanged**, and FR-007's "extend the test to cover
them" needs no code — the scan is already table-agnostic and picks them up the moment they exist.
What FR-007 needs is a *verification*, not an edit. **Open Item 5 in the spec is closed by
measurement, and no narrow exemption is written.**

---

## N8 — Both extensions exist in both databases, verified by query

```text
injazedu_lab       pg_trgm 1.6 · vector 0.8.6 · plpgsql 1.0
injazedu_lab_test  pg_trgm 1.6 · vector 0.8.6 · plpgsql 1.0
```

Extensions are per-database in PostgreSQL, so the test database having them is a separate fact from
the real one having them — `infrastructure/postgres/init.sql` creates both, and its own header warns
that it runs only at volume creation and must never be trusted over a live query. Checked live.

`$table->vector($col, 768)` is native (`2026_08_22_120000_create_lab_vector_probes_table.php`), so no
`pgvector/pgvector-php` package is added. No migration anywhere issues `CREATE EXTENSION`, and P2's
must not either.

---

## N9 — The test suites already split the way P2 needs

`phpunit.xml` defines three suites. `composer test` runs `Unit,Feature` against the disposable
`injazedu_lab_test`; `composer test:mirror` runs `MirrorValidation` (`tests/Validation/`) against the
**real** mirror, read-only, and only when named explicitly.

This maps P2's tests cleanly with no new infrastructure:

| Suite | Gets |
|---|---|
| `Unit` | the normalizer, the hasher, the options builder, the threshold sweep, the closure algorithm |
| `Feature` | schema and FK-through-`source_id`, the media boundary, the LLM band proof, human override and the status map, idempotency — all on fixtures |
| `MirrorValidation` | the whole-bank assertions that need real rows: derived-row coverage, embedding coverage and one config version, the affected-student sums, the no-deletion count |

Feature tests must build their own fixtures. `tests/Validation/` is where "all 29,142 questions have
a derived row" belongs, because that claim is only true of the real database.

---

## N10 — Measurements behind the three operator decisions of 2026-08-28

Run read-only against `injazedu_lab` before the decisions were written into the spec, so each rests on
a number rather than on an expectation. Same method as N1/N2: `SELECT` only, no MySQL, no writes.

### The bank is not Arabic-only, and Latin option labels outnumber Arabic ones 3:1

```text
115,719 active options
  3,604 begin with a Latin label + delimiter   (A.  B)  c- …)
  1,200 begin with an Arabic label + delimiter (أ.  ب)  ج- …)
    254 begin with a digit label
  9,601 begin with a Latin letter at all

 2,844 of 28,747 active questions (9.9%) contain no Arabic character at all
 2,036 question stems begin with a Latin letter
```

InjazEdu carries English/STEP/IELTS courses beside the Arabic ones. **An Arabic-only label stripper
would miss the majority case**, which is why FR-139 names both alphabets and makes them configuration.

### Case folding was missing from FR-011 while the embedding budget assumed it

N1's 11,094 distinct-stem figure — the basis of the whole ~24,063 call budget — was measured with
lower-casing applied. FR-011 did not list case folding. That is a genuine inconsistency between a
requirement and the number quoted against it, not a stylistic gap, and FR-140 closes it.

### Both new folds are small-yield, and that is stated rather than discovered later

Rough SQL normalizer at the stem grain (lower-case, Alef unification, tatweel, whitespace collapse):

```text
distinct normalized stems                11,097     (agrees with N1's 11,094)
   + ة → ه fold                          11,085     →  12 more stems collapse
distinct without case folding            11,102
   + Unicode lower-case                  11,097     →   5 more stems collapse
```

The fuzzy form is therefore a **small, cheap recall aid, not a major source of duplication** — one
pure function, one hash column, one btree, and no second GIN index. FR-051's `orthographic` eval quota
exists so Phase 7 can retire it on evidence if the labels say it adds nothing.

### The conflict backlog reproduces at exactly 928, and its impact is extremely concentrated

Replicating N2's definition (same `raw_text` among active questions, group size > 1, more than one
distinct set of correct-option texts, no image member) and joining `source_item_stats.n` at
`scope = 'active'`:

```text
    928 conflicting groups, no image member          ← reproduces N2 exactly
269,153 total affected students across the backlog

      0 groups with zero measured impact   ← so NO zero-impact tier is built
     64 groups     1–29 students
    558 groups    30–199
    306 groups     ≥200

per group   p50 = 141   p75 = 282   p90 = 686   max = 6,966

Top  25 groups →  21.1% of all measured student exposure
Top  50 groups →  34.1%
Top 100 groups →  50.4%     ≈  3–8 hours at 2–5 min/item
Top 200 groups →  67.3%     ≈ 7–17 hours
All 928        →   100%     ≈ 31–77 hours
```

**Half the measured harm sits in the top 100 items.** At `daily_review_cap = 10` that is ten working
days. This is what turns "the solo operator cannot clear 928 items" from a concession into a design:
ranking is not a convenience here, it is the difference between 3–8 hours and 31–77 for the same half
of the benefit. It is also the evidence for FR-150's percentile cut points and for **not** building a
tier for zero-impact conflicts, of which there are none.

### For the record, on `source_item_stats.n` across the whole bank

```text
 1,328 of 29,142 questions have n = 0      (4.6% — no answer data)
 6,611 have n =  1–9
 9,027 have n = 10–29
12,176 have n >= 30
```

Note that no *conflicting group* sums to zero even though 1,328 individual questions do — a group has
several members, and one answered member is enough.

---

## What these findings change

| Finding | Change |
|---|---|
| N1 | **Spec amended** — FR-036, SC-006, US3 scenario 1. Two dedup keys, ~24,063 calls, 58% saved |
| N2 | **Spec amended** — the measured group and conflict figures replace the plan's; §8 item F's hours corrected to 31–77 |
| N3 | Phase 4 measures before any batch endpoint is considered; stated in the plan, not asked |
| N5 | `media_fingerprint` hashes an ordered list and defines its null-path behaviour; `single_correct` is the literal |
| N6 | `LabDedup` resolves a snapshot and records `ran_via`, like `LabImport` |
| N7 | **Spec Open Item 5 closes.** No exemption, no test edit — a verification instead |
| N9 | Test placement is decided before the first test is written |
| N10 | **Spec amended** — FR-011/FR-012 scoped, FR-139 to FR-154 added, FR-050/FR-051/FR-056/FR-061/FR-078/FR-089/FR-091/FR-137 amended, SC-031 to SC-034 added, human gate F reclassified out of blocking, constitution IV narrowed to v2.5.0 |
