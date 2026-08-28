# Data Model — P2 Duplicate Intelligence

**Feature**: `006-p2-duplicate-intelligence` · **Date**: 2026-08-28
**Checked against**: the live `injazedu_lab` schema and P1's migrations, not the project plan's prose.

Eight new tables, all **Lab-owned and Postgres-native**. None mirrors a source table, so none carries
P1's common mirror columns (`source_system`, `imported_at`, `payload_hash`, …) and none belongs on
either allowlist in `config/lab.php` — those govern reads against MySQL, and **P2 performs none**.

---

## 1. The two foreign-key conventions

This is the single most likely defect in the feature (spec Decision 2), so it is stated before any
table:

```text
*_source_id   →  references a MIRROR table by its source_id      (the Production identifier)
*_id          →  references a P2 table by its Lab surrogate id   (BIGSERIAL)
```

Verified in P1's code, not assumed:

```text
SourceQuestion::options()   hasMany(SourceQuestionOption, 'question_source_id', 'source_id')
SourceSection::questions()  hasMany(SourceQuestion,       'section_source_id',  'source_id')
```

Every mirror table carries **both** a Lab `id` (BIGSERIAL) and `source_id`. A P2 table that joined to
`source_questions.id` would return rows and be silently wrong. Phase 1's acceptance criterion is a
test that joins **through `source_id`** and fails if the convention is broken.

**No database-level FK constraints to mirror tables.** P1 declares none between mirror tables either
— `source_id` is unique per `(source_system, source_id)`, not on its own, so a single-column
reference cannot be constrained without changing the mirror. The relation is enforced by the models
and by tests, which is the existing precedent (`import_errors.import_run_id` carries no constraint).

---

## 2. `source_question_derived` — text layers, hashes, vectors

One row per question, **including soft-deleted ones** (spec FR-020). Every downstream step filters
`source_deleted_at IS NULL` at read time.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGSERIAL PK | |
| `question_source_id` | `unsignedBigInteger`, **UNIQUE** | → `source_questions.source_id` |
| `clean_text` | `text` | technical cleanup only; meaning preserved |
| `search_text` | `text` | the comparison representation |
| `question_text_hash` | `char(64)`, indexed | SHA-256(`search_text`) — **Layer 0**, and the `stem_embedding` dedup key |
| `question_with_options_hash` | `char(64)`, indexed | SHA-256(`search_text` ⊹ ordered options) — **Layer 1**, and the `full_embedding` dedup key |
| `fuzzy_text_hash` | `char(64)`, nullable, indexed | SHA-256 over the **recall-only** fold of `search_text` (FR-141). **Never** an identity key — see below |
| `fuzzy_rules_version` | `text`, nullable | versions the fold map separately from `normalizer_version`, so switching the map off does not invalidate a single strict hash |
| `media_fingerprint` | `char(64)`, nullable, indexed | SHA-256 over the **ordered list** of attached image paths; NULL when the question has no image |
| `normalizer_version` | `text` | versions the ruleset; a rule change makes stale hashes visible |
| `stem_embedding` | `vector(768)`, nullable | |
| `full_embedding` | `vector(768)`, nullable | |
| `embedding_config_version` | `text`, nullable | from the `/embed` response, never from local config |
| `stem_truncated` | `boolean` default false | |
| `full_truncated` | `boolean` default false | |
| `text_computed_at` | `timestampTz`, nullable | |
| `embedded_at` | `timestampTz`, nullable | |

**`media_fingerprint` hashes a list, not a path (notes.md N5).** 5,582 media rows sit over 5,578
questions — four questions carry two images each. The input is the paths of
`source_media WHERE type='image' AND attach_level='question'` for that question, ordered by
`source_id` for determinism. `path` is nullable in the mirror (zero nulls at this grain today); a
NULL is folded in as the empty string so the fingerprint is defined rather than `sha256(null)`.

**Video is excluded** from the fingerprint. `source_media.type` admits `video`, and Decision 4's
evidence is about images; a video attachment is recorded in the mirror and is not part of the
identity test until something measures that it should be.

**`fuzzy_text_hash` stores a hash, not a text (added 2026-08-28).** The fold is a pure function of
`search_text`, so storing the folded string would be a second copy of ~29,142 rows that can drift.
The hash is all the candidate-grouping query needs, and a plain btree on it reuses the Layer 0/1
machinery — **no second GIN index is created**, so constitution VII's "indexes are earned" is not
spent on a small-yield recall aid. Measured yield at the stem grain: ~12 additional distinct stems
collapse (notes.md N10).

**What `fuzzy_text_hash` may and may not do (FR-141, FR-142).** It may group questions into
**candidates** carrying `hash_match_level = 'orthographic'`. It may **not** appear in any cluster key,
any auto-cluster path, or any `relation_type = 'exact_duplicate'` decision. `fuzzy_rules_version` is
deliberately a **separate** column from `normalizer_version`: changing the fold map must not make a
single strict hash look stale, because the strict hashes do not depend on it. A test asserts both
strict hashes are byte-identical with the fold enabled and disabled (FR-143).

**Indexes created here**: the four hashes and the unique key. **The trigram GIN index is not created
here** — Phase 5 earns it (constitution VII).

---

## 3. `source_section_derived` — built, and empty on this snapshot

| Column | Type |
|---|---|
| `id` | BIGSERIAL PK |
| `section_source_id` | `unsignedBigInteger`, **UNIQUE** → `source_sections.source_id` |
| `clean_text` | `text` |
| `search_text` | `text` |
| `stimulus_text_hash` | `char(64)`, indexed |
| `normalizer_version` | `text` |
| `text_computed_at` | `timestampTz`, nullable |

Populated only `WHERE has_stimulus = true`. **Verified 2026-08-28**: `has_stimulus` is true on 0 of
3,316 rows and `max(length(stimulus_raw))` is 0 — the table stays empty, and the acceptance criterion
asserts that rather than pretending coverage.

**No embedding column.** Adding one before a passage exists is speculation, and it is one migration to
add later.

---

## 4. `duplicate_candidates` — every pair the paid layers proposed

| Column | Type | Notes |
|---|---|---|
| `id` | BIGSERIAL PK | |
| `question_a_source_id` | `unsignedBigInteger`, indexed | canonical: **always the smaller** |
| `question_b_source_id` | `unsignedBigInteger`, indexed | |
| `trgm_score` | `double`, nullable | Layer 2 |
| `stem_cosine_sim` | `double`, nullable | Layer 3, **primary** |
| `full_cosine_sim` | `double`, nullable | Layer 3, secondary |
| `hash_match_level` | `text`, nullable | `exact` \| `formatting` \| `orthographic` \| NULL — `orthographic` added 2026-08-28 (FR-142) |
| `same_section` | `boolean` | the structural rule that survives Decision 3 |
| `media_relation` | `text` | `same_media` \| `different_media` \| `no_media` |
| `band` | `text`, nullable, indexed | `exact` \| `high` \| `uncertain` \| `low` — NULL until calibration |
| `llm_verdict_relation` | `text`, nullable | one of the seven verdict values |
| `llm_same_learning_objective` | `boolean`, nullable | |
| `llm_same_correct_answer` | `boolean`, nullable | |
| `llm_confidence` | `double`, nullable | |
| `llm_issues` | `jsonb`, nullable | |
| `llm_recommended_action` | `text`, nullable | |
| `llm_review_required` | `boolean`, nullable | |
| `llm_prompt_version` | `text`, nullable | |
| `llm_verdict_at` | `timestampTz`, nullable | |
| `verdict_attempts` | `integer` default 0 | **clarification 2026-08-27**, FR-123 |
| `verdict_last_error` | `text`, nullable | **clarification 2026-08-27**, FR-123 |
| `verdict_failed` | `boolean` default false | **terminal state**, FR-124 — excluded from dispatch |
| `generated_at` | `timestampTz` | |
| `embedding_config_version_at_generation` | `text`, nullable | |

**UNIQUE** (`question_a_source_id`, `question_b_source_id`) · **INDEX** (`band`) ·
**INDEX** (`band`, `verdict_failed`) where the dispatch query reads.

The seven `llm_*` verdict fields are **seven columns, not one JSON blob**, so a verdict is queryable.
`llm_issues` is the one genuinely list-shaped field and stays `jsonb`.

**Why three columns for failure and not one.** `verdict_failed` alone would lose why; `verdict_attempts`
alone cannot express "stop trying". Together they make FR-124's bound enforceable and FR-125's console
count possible.

**`hash_match_level = 'orthographic'` is a candidate level, never a cluster level.** It marks a pair
equal under `fuzzy_text_hash` but not under `question_text_hash`. Like `formatting`, it routes to the
high band for a verdict or a human; **unlike** `exact`, no automatic path may promote it to a cluster
(FR-142). This is the storage-level expression of the operator's rule that a match caused only by
typo tolerance never becomes an `exact_duplicate` on its own.

---

## 5. `duplicate_clusters` — the grouping, never a deletion

| Column | Type | Notes |
|---|---|---|
| `id` | BIGSERIAL PK | |
| `canonical_question_source_id` | `unsignedBigInteger`, indexed | the **lowest** member `source_id`, after closure |
| `relation_type` | `text` | the **cluster** vocabulary — see §9 |
| `status` | `text`, indexed | `auto` \| `pending_review` \| `confirmed` \| `rejected` \| `urgent_review` \| `resolved` \| `skipped` |
| `source_layer` | `text`, indexed | `hash` \| `high_band_auto` \| `llm_verdict` \| `human_manual` |
| `affected_student_count` | `integer`, nullable | deterministic SQL over `source_item_stats.n`; the ranking key |
| `priority_tier` | `text`, nullable, indexed | `tier_1_critical` \| `tier_2_high` \| `tier_3_standard` \| `tier_4_deferred` — deterministic SQL from the measured distribution (FR-150). **Never written by a model** |
| `ai_triage_recommendation` | `text`, nullable | advisory only (FR-153) |
| `ai_triage_rationale` | `text`, nullable | one short paragraph, displayed as a labelled recommendation |
| `ai_triage_confidence` | `double`, nullable | |
| `ai_triage_prompt_version` | `text`, nullable | |
| `ai_triage_at` | `timestampTz`, nullable | |
| `member_count` | `integer` | denormalized for the size guard and the console |
| `created_at` / `updated_at` | `timestampTz` | |

**INDEX** (`status`, `priority_tier`, `affected_student_count` DESC) — the backlog's ordering
(FR-089), which must never fall back to `id`.

**`priority_tier` is a stored, recomputable derivation, not a judgement.** The cut points are
percentiles of the live `affected_student_count` distribution held in `config('lab.dedup')`
(0.50 / 0.75 / 0.90 on arrival); the **computed values** are logged with each run because they are a
measurement of the current population, not constants. Measured 2026-08-28 over the 928-group backlog:
p50 = 141, p75 = 282, p90 = 686, max = 6,966, and **no group has zero impact** — which is why there is
no zero-impact tier (notes.md N10).

**The five `ai_triage_*` columns are prefixed for exactly one reason:** so a reviewer, a query and a
test can all tell at a glance that nothing in them is measured. FR-153 forbids AI output from
reaching `affected_student_count`, `priority_tier`, `status` or `relation_type`, and a test asserts
it. Only a `duplicate_reviews` row moves a conflict out of `urgent_review` (FR-129).

**A question belongs to at most one cluster per `source_layer`** (FR-116). That is a property of the
closure algorithm, and it is asserted by a test rather than by a constraint, because a partial unique
index over the *member* table cannot see the parent's layer without a join.

---

## 6. `duplicate_cluster_members`

| Column | Type |
|---|---|
| `id` | BIGSERIAL PK |
| `duplicate_cluster_id` | `unsignedBigInteger`, indexed → `duplicate_clusters.id` |
| `question_source_id` | `unsignedBigInteger`, indexed → `source_questions.source_id` |
| `is_canonical` | `boolean` default false |
| `added_at` | `timestampTz` |

**UNIQUE** (`duplicate_cluster_id`, `question_source_id`).

This constraint permits a question in several clusters — which is **correct**, because clusters of
different layers may overlap (FR-121). What it must not permit is two clusters of the *same* layer
holding one question, and that is the test in FR-120.

---

## 7. `duplicate_reviews` — the one irreproducible artefact in the Lab

| Column | Type | Notes |
|---|---|---|
| `id` | BIGSERIAL PK | |
| `duplicate_cluster_id` | `unsignedBigInteger`, indexed → `duplicate_clusters.id` | |
| `decision` | `text` | `same` \| `valid_variant` \| `not_duplicate` \| `conflict` \| `skip` |
| `reviewer_id` | `unsignedBigInteger` | → `users.id` — **Lab operator accounts**, never Production identities |
| `reviewed_at` | `timestampTz` | |
| `previous_status` | `text`, nullable | the transition, stored (FR-131) |
| `new_status` | `text`, nullable | |
| `previous_relation_type` | `text`, nullable | **clarification 2026-08-27** — a decision may change it (FR-130) |
| `new_relation_type` | `text`, nullable | |
| `notes` | `text`, nullable | |

**Append-only.** A human decision never overwrites the AI verdict; the two sit side by side, which is
what makes "how often was the model wrong?" answerable. Constitution III names these rows the one
thing in the Lab with no other source — durability is a go-live concern, not a local gate.

**`reviewer_id` is not `user_id`, and that is why the PII test passes** (notes.md N7).
`NoPiiInLabSchemaTest` forbids the literal column name `user_id`; it does not forbid `*_id`. No
exemption is written and the test needs no edit.

---

## 8. `duplicate_eval_pairs` — the labelled set, three purposes

| Column | Type | Notes |
|---|---|---|
| `id` | BIGSERIAL PK | |
| `question_a_source_id` / `question_b_source_id` | `unsignedBigInteger`, indexed | |
| `purpose` | `text`, indexed | `calibration` \| `spot_check` \| `uncertain_review` |
| `label_round` | `smallint` default 1 | **which labeller**: 1 = the primary label, 2 = the independent second |
| `sample_wave` | `smallint` default 1, indexed | **which expansion wave**: 1 → 4 (FR-050). A *different axis* from `label_round` |
| `sampled_band` | `text` | the stratum it was drawn from |
| `sim_score_at_sampling` | `double`, nullable | |
| `embedding_config_version_at_sampling` | `text`, nullable | |
| `media_relation` | `text`, nullable | so Decision 4's rule is measured, not assumed |
| `human_relation` | `text`, nullable | one of the **seven verdict values** (§9) |
| `human_same_learning_objective` | `boolean`, nullable | |
| `human_same_correct_answer` | `boolean`, nullable | |
| `human_confidence` | `double`, nullable | |
| `labelled_by` | `unsignedBigInteger`, nullable → `users.id` | |
| `labelled_at` | `timestampTz`, nullable | |
| `ai_suggested_relation` | `text`, nullable | the pre-label (FR-147). **Never** ground truth |
| `ai_suggested_confidence` | `double`, nullable | |
| `ai_prompt_version` | `text`, nullable | |
| `ai_suggested_at` | `timestampTz`, nullable | |
| `ai_suggestion_shown` | `boolean` default false | whether the labeller saw it — set only **after** `human_relation` was recorded (FR-148) |
| `human_relation_revised` | `boolean` default false | the human changed their label after seeing the suggestion |
| `notes` | `text`, nullable | |
| `created_at` | `timestampTz` | |

**UNIQUE** (`question_a_source_id`, `question_b_source_id`, `purpose`, `label_round`) ·
**INDEX** (`purpose`).

`purpose` is in the key so a pair sampled for calibration may later be spot-checked. `label_round` is
in the key so the **doubled subsample** (FR-056) is a second row on the same pair with
`label_round = 2` and a different `labelled_by`.

**Why a round column rather than a ninth table or doubled columns.** Inter-rater agreement is then one
self-join between rounds 1 and 2 on the pairs that have both, the cumulative label count stays
`WHERE purpose = 'calibration' AND label_round = 1`, and a third labeller costs a row rather than a
migration — the same reasoning `source_item_stats.scope` records for its own discriminator.

**`sample_wave` is a separate column from `label_round`, and conflating them is the likeliest silent
defect in the progressive-calibration design (added 2026-08-28).** They are orthogonal axes:
`label_round` says *who labelled this row*, `sample_wave` says *which expansion drew this pair*.
Overloading `label_round` to carry waves would break inter-rater agreement, which is defined as the
self-join between rounds 1 and 2 — waves 2 and 3 would silently be read as second and third
labellers. `sample_wave` is **not** in the UNIQUE key, because a pair is drawn in exactly one wave;
the key stays (`a`, `b`, `purpose`, `label_round`).

**The six `ai_*` and revision columns keep the ground truth separable by construction (FR-147,
FR-148).** The suggestion is stored beside the human label, never in it, so precision and recall are
always computable against a human column that no model wrote. `ai_suggestion_shown` and
`human_relation_revised` make anchoring **measurable**: with `label_round = 2` labelled blind, the
existing agreement calculation compares an assisted labeller against an unassisted one at no extra
cost. A test asserts no `ai_*` value ever appears in `human_relation` or the positive class (SC-032).

---

## 9. The two relation vocabularies (FR-132 – FR-134)

They are deliberately **not** the same enum, and a test asserts the separation.

| | Verdict + human label | Cluster `relation_type` |
|---|---|---|
| `exact_duplicate` | ✅ | ✅ |
| `formatting_duplicate` | ✅ | ✅ |
| `semantic_duplicate` | ✅ | ✅ |
| `same_objective_variant` | ✅ | ✅ |
| `related_not_duplicate` | ✅ | ✅ |
| `conflicting_duplicate` | ✅ | ✅ |
| `not_related` | ✅ | ❌ — no cluster is created for unrelated questions |
| `probable_duplicate` | ❌ — the model must not be able to claim it | ✅ — **only** the high-band auto path writes it |

Calibration's positive class is `exact_duplicate ∪ semantic_duplicate`, taken from §17 literally.

**Stored as `text` with a check constraint, not as a Postgres `enum` type.** P1 uses `enum` for two
short fixed lists (`source_media.type`) and `text` for discriminators it expects to extend
(`source_item_stats.scope`, with the migration note "a third scope costs a row, never a migration").
These vocabularies are the second kind: a new relation value must cost an edit, not a type migration
with data in it.

---

## 10. `duplicate_eval_runs` — never overwritten

| Column | Type |
|---|---|
| `id` | BIGSERIAL PK |
| `run_kind` | `text` — `calibration` \| `embedder_benchmark` |
| `embedder_model` | `text` |
| `embedder_dimension` | `integer` |
| `embedding_config_version` | `text`, nullable |
| `eval_pair_count` | `integer` — the **cumulative** labelled count this run was computed on |
| `sample_wave` | `smallint`, nullable — which wave produced this run (FR-050) |
| `positive_class_count` | `integer`, nullable — pairs in `exact ∪ semantic`; FR-144 condition 3 needs ≥ 30 |
| `recall_at_20` | `double`, nullable — **the decisive benchmark metric** (§12.4) |
| `precision_at_threshold` | `double`, nullable |
| `precision_ci_low` / `precision_ci_high` | `double`, nullable — 95% Wilson interval; FR-144 tests the low bound, FR-145 the high |
| `recall_at_threshold` | `double`, nullable |
| `recall_ci_low` / `recall_ci_high` | `double`, nullable — 95% Wilson interval |
| `expansion_decision` | `text`, nullable — `expand` \| `stop_pass` \| `stop_fail` (FR-144, FR-145) |
| `threshold_low` / `threshold_high` | `double`, nullable |
| `projected_uncertain_band_count` | `integer`, nullable |
| `storage_mb` | `double`, nullable |
| `time_per_1k_ms` | `double`, nullable |
| `gate_passed` | `boolean`, nullable — precision ≥ 0.90 **AND** recall ≥ 0.70 |
| `is_selected` | `boolean` default false |
| `inter_rater_agreement` | `double`, nullable — measured on the doubled subsample (FR-056) |
| `computed_at` | `timestampTz` |
| `notes` | `text`, nullable |

The `source_snapshots` pattern applied to calibration: a re-run produces a **comparison**, not a
replacement, so "did changing the model help?" stays answerable. This table is the program's
reference for every similarity threshold — P4 reads `threshold_high` from here rather than
re-calibrating (spec Handoff).

**Progressive calibration writes one row per wave (added 2026-08-28).** The wave history is therefore
readable as a sequence — `expand` at wave 1, `expand` at wave 2, `stop_pass` at wave 3 — and FR-065's
never-overwrite rule already guarantees it. Only the row that actually settled the decision carries
`is_selected`. Storing both confidence intervals rather than only point estimates is what makes
FR-144's stopping rule reconstructible after the fact: "why did we stop at 200?" is answered by the
row, not by memory.

---

## 11. Migration order

Dependency order, one migration per table, plus one for the earned index:

```text
1  source_question_derived            (no dependencies)
2  source_section_derived             (no dependencies)
3  duplicate_candidates               (no dependencies)
4  duplicate_clusters                 (no dependencies)
5  duplicate_cluster_members          → duplicate_clusters.id
6  duplicate_reviews                  → duplicate_clusters.id, users.id
7  duplicate_eval_pairs               → users.id
8  duplicate_eval_runs                (no dependencies)
─────────────────────────────────────────────────────────────
9  add_trgm_index_to_source_question_derived   ← Phase 5, DB::statement(), earned
```

Every migration carries a header stating the table is **P2-owned and not part of the P1 mirror**, so
a later reader does not mistake it for something the ETL maintains (FR-006).

**No migration issues `CREATE EXTENSION`.** `vector 0.8.6` and `pg_trgm 1.6` are present in **both**
`injazedu_lab` and `injazedu_lab_test`, verified by querying `pg_extension` rather than by reading
`init.sql` (notes.md N8).

---

## 12. What P2 reads from P1, and never writes

```text
source_questions            raw_text · source_deleted_at · answer_key_state · section_source_id
source_question_options     raw_text · option_index · is_correct_derived · source_deleted_at
source_media                type · attach_level · path · question_source_id
source_sections             has_stimulus · stimulus_raw · source_id
source_item_stats           n, at scope = 'active' — the ranking key, and nothing else
```

`answer_key_state` values are **`single_correct` · `broken_no_key` · `multi_key`** — not the
`single_key` the project plan writes (notes.md N5). `source_item_stats` holds one row per
(question, scope) for all 29,142 questions, with `n = 0` where there is no answer data, so a consumer
can tell "no data" from "not computed".

**Not one column of any mirror table is written by this feature.**
