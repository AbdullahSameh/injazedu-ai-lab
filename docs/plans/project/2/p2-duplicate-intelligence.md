# P2 — Arabic Normalization & Duplicate Intelligence
## Implementation Plan — Third Project

**Project:** P2 — Arabic Normalization & Duplicate Intelligence
**Short name:** *Duplicate Intelligence* — the filename and branch use it; **§17 governs the title**
**Order:** Third in the program — depends on P1, and is depended on by P4; runs in parallel with P3
**Version:** 1.0 — 2026-08-27
**Governing reference:** §17 of `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (v2.0), with §8, §12 and §13 as supporting contracts
**Delivered as:** one Spec Kit feature — see §7.1
**Status:** Ready for implementation
**Effort estimate:** ~12.5 focused working days for a single developer

> **What the mirror changed before a line was written.** This plan was drafted against §17's
> assumptions and then checked against the loaded mirror. Four measurements changed it.
> (1) **60.3% of the active bank is literally duplicated** — 28,747 questions carry only 11,416
> distinct texts — so the cascade's cheapest layer does most of the work and every downstream budget
> halves. (2) **`sections.description` is empty in all 3,316 rows**, so §8's passage track has
> nothing to operate on in this snapshot. (3) **In its place, 5,582 questions carry an attached
> image**, and image-bearing duplicate groups conflict at 83.8% against 24.4% for the rest — the
> image is the real false-positive trap, and the data proves it. (4) **~1,125 same-text groups carry
> genuinely different answer keys**, which makes `conflicting_duplicate` a backlog to triage, not a
> trickle to queue. Nothing in §17's engineering was dropped; three of its estimates were corrected
> by measurement, which is what P1 was built to make possible.

---

# 1. Context and Goal

## 1.1 Why the Cheapest Layer Comes First

§17 opens with a cascade of five layers and one sentence that governs the whole project: detect the
real duplication **within a finite compute budget**. P1 made that budget calculable for the first
time, and the answer is more favourable than §13 assumed:

```text
28,747 active questions  ->  11,416 distinct raw texts
                             17,331 redundant (60.3%), in 4,689 groups
```

§13.2 sized the LLM problem from 25,000 questions and reached "~33 continuous hours" and the
conclusion that "it is not possible to run every candidate through an LLM." That conclusion stands,
but the arithmetic that produced it does not: after the zero-cost hash layer collapses identical
texts, **the semantic layers see 11,416 items, not 28,747**. Every estimate downstream — embedding
calls, candidate pairs, LLM hours — is computed against that number in this plan, not against the
bank size.

This is the whole argument for the cascade stated as a measurement. A project that reached for the
model first would have paid for 28,747 embeddings and tens of thousands of verdicts to rediscover
something `GROUP BY md5(...)` finds in 61 milliseconds.

## 1.2 The Governing Principle

```text
Cheap layers run first, and each layer only ever sees what the layer below could not settle.
Skipping a layer to "just ask the model" is a defect, not a shortcut.
Nothing is deleted. Nothing is written to Production. A verdict is a recommendation.
```

P2 is a **detector and a queue**, not an editor. It finds duplication, ranks it, explains it, and
hands it to a human. Every action it recommends is carried out by a person, outside the Lab.

## 1.3 Definition of Done

> One command (`lab:dedup`) runs the five-layer cascade end to end — idempotent, resumable, and
> provably rationed, with a counter showing the LLM saw nothing outside the uncertain band; a
> calibrated threshold pair backed by a 400-pair human-labelled set that clears **precision ≥ 0.90 at
> recall ≥ 0.70**; and an Arabic review console where a moderator can settle a pair in seconds and a
> trainer can work a prioritized `conflicting_duplicate` backlog ordered by how many students each
> error reached — with not one question deleted and not one row written to `injazedu`.

## 1.4 What This Project Unblocks

| Next project | What it needs from P2 |
|--------------|------------------------|
| P3 — Item Statistics | **Nothing.** P3 depends only on P1 and may run in parallel — but the intersection below is the payoff |
| P3 ∩ P2 | A `conflicting_duplicate` cluster whose members also carry `r_pbis < 0` is, per §18, "the strongest signal in the program" — two independent methods naming the same wrong answer key |
| P4 — Quality Audit | `duplicate_clusters` as layer one of the three-layer audit (§19), plus `search_text` as the comparable text representation |
| P5 — Taxonomy & Coverage | Deduplicated clusters — a coverage map built over 11,416 distinct items instead of 28,747 rows says something true about the bank |
| P9 — Copilot | Canonical questions as few-shot examples, and the clusters as the evidence for what not to generate more of |

---

# 2. What P2 Inherits from P1

## 2.1 Ready, and Not to Be Rebuilt

```text
source_questions.raw_text                 original text, unmodified — the input to the three layers
source_question_options                   stable option_index + is_correct_derived (points > 0)
source_sections.stimulus_raw              present, and empty in this snapshot — see §2.2
source_media                              5,582 images at question grain, 4 audio at section grain
source_item_stats / source_option_stats   n · n_correct · p_value · m1 · m0 · sd   (ADR-022)
answer_key_state                          which questions are comparable at all
payload_hash                              row-level change detection, not text similarity
App\Support\Import\ImportRunRecorder      run accounting — import_runs.kind is a plain string(20)
App\Support\Import\ResumeCursor           the resume contract, already tested
App\Support\Import\ImportErrorRecorder    append-only error log + ImportErrorCode
App\Support\Import\BatchUpsert            chunked idempotent writes
App\Support\Derive\OptionIndexDeriver     stable ordering — never re-derive it
PostgreSQL 17 · pgvector 0.8.6 · pg_trgm 1.6      installed, with no index on any mirror table yet
apps/ai-service  POST /embed              the embedding contract, live and health-checked
config('lab.embedding.*')                 config_version · prefix_template · dimension · models
database queue (no Horizon)               ADR-011 — ready for P2's batches
php artisan lab:health                    10/10, exit 0 — the instrument every phase is checked against
```

`lab:health` staying green is the condition on every phase below. Phase 11 adds an eleventh check.

## 2.2 Numbers That Are Not Re-derived — and Four That Change the Plan

Measured against the fixed **2026-08-07** snapshot on 2026-08-27, from the loaded mirror. The
snapshot is not refreshed, and nothing here blocks on its age.

| Measurement | Value | What it changes |
|---|---|---|
| Active questions | **28,747** (29,142 total, 395 soft-deleted) | The denominator for every rate below |
| **Distinct raw texts** | **11,416** | **Embedding and candidate budgets are computed from this, not 28,747** |
| Literal duplicate groups | **4,689**, holding 22,020 questions | Layer 0 alone resolves 17,331 redundant rows at zero cost |
| Redundancy rate | **60.3%** | §13.2's pair estimate halves; see §5 |
| `has_html` | **0** | HTML stripping is a no-op today — implemented defensively, priced at nothing |
| `has_img` (inline `<img>`) | **0** | Images are attached rows, never markup |
| `source_media` images | **5,582** at question grain | **The live boundary rule** — see Decision 4 |
| Sections with stimulus | **0 of 3,316** (`max(stimulus_length) = 0`) | **§8's passage track is inert on this snapshot** — see Decision 3 |
| Audio items | **4**, at section grain (18 questions flagged) | Excluded from every text path, as P1 already flags them |
| Questions with a description | 336 (1.2%) | No explanation corpus to lean on — P9's problem, noted not solved |
| Same-text groups with **differing answer keys** | **~1,227 raw → ~1,125 after excluding image-bearing groups** | `conflicting_duplicate` is a backlog, not a trickle — see Decision 5 |

**The image finding, stated precisely**, because one decision rests on it:

```text
Duplicate groups with no image member :  4,602 groups, 1,125 conflicting  (24.4%)
Duplicate groups with an image member :    136 groups,   114 conflicting  (83.8%)
```

A group whose members carry different images conflicts at more than three times the base rate. The
overwhelmingly likely reading is not that these are broken questions — it is that **the image is
part of the question**, and two items sharing a stem while pointing at different diagrams are
different items with correctly different answers. This is §8's "different passage ⇒ not a duplicate"
rule, arriving through the medium the bank actually uses.

---

# 3. Deviations and Approved Decisions

§17 was written at program level, before the mirror existed. These decisions are settled and are not
reopened during implementation. Per Principle I, each is either reversible by an edit (decided here,
stated plainly) or is escalated to §8 as a human decision.

## 3.1 The Six Decisions

### Decision 1 — New Sibling Tables, Never `ALTER` on the Mirror

§17 speaks of `clean_text` and `search_text` as though they were columns on the question. Adding them
to `source_questions` would put the derived text inside the mirror, and the mirror's contract is that
it is **faithful**: what P1 copied is what Production holds.

**Decision:** every P2 artefact lives in new tables. `source_question_derived` holds the text layers,
the hashes and the vectors, keyed one-to-one to the question. This follows the precedent P1 already
set — `source_item_stats` is a derived register beside the mirror, not extra columns bolted onto it.

**Why it also matters procedurally:** Principle I names "the mirror schema shape" as expensive to
reverse and therefore stop-and-ask. New sibling tables referencing the mirror by FK are ordinary,
reversible engineering that needs no approval. Choosing the additive design keeps the whole project
inside the "decide with judgement" half of the gate.

### Decision 2 — Every P2 Foreign Key References `source_id`, Never the Surrogate `id`

Verified in the code, not assumed:

```text
apps/lab/app/Models/SourceQuestion.php:46
  hasMany(SourceQuestionOption::class, 'question_source_id', 'source_id')
apps/lab/app/Models/SourceQuestionOption.php:39
  belongsTo(SourceQuestion::class, 'question_source_id', 'source_id')
apps/lab/app/Models/SourceSection.php:43
  hasMany(SourceQuestion::class, 'section_source_id', 'source_id')
```

Every mirror table carries both a Lab-generated surrogate `id` (BIGSERIAL) and `source_id` (the
verbatim Production id). P1 wired every relation through **`source_id`**, because Production's own
foreign keys are copied as-is and need no translation at ETL time.

**Decision:** P2's `*_source_id` columns follow the same convention exactly. P2's own native tables
(`duplicate_clusters` ↔ `duplicate_cluster_members` ↔ `duplicate_reviews`) reference each other by
ordinary Lab surrogate `id`, the way `import_errors.import_run_id` already does.

**Why it is written down:** a table that FKs to `source_questions.id` would join, return rows, and be
silently wrong. It is the single most likely defect in this project's schema.

### Decision 3 — The Stimulus Track Is Built Minimally and Declared Inert

§8 makes passage-based item sets a first-class object, and §17 derives a mandatory blocking rule from
it. The mirror says `sections.description` is empty in **all 3,316 rows**; `max(stimulus_length)` is
zero. There is no passage text in this snapshot.

**Decision:** `source_section_derived` is created and its population step is implemented, because the
column exists in Production and may be filled later. It is **not** given an embedding phase, a
passage-excerpt builder, or a share of the effort estimate, and the acceptance criteria assert it
holds zero rows rather than asserting coverage. If a future snapshot carries stimulus text, the
excerpt rule (§8: an excerpt, never the whole passage, against a 2K context) is implemented then, as
its own small piece of work.

**What is not dropped:** the *structural* rule survives. `section_source_id` still records which
section a question belongs to, still lands on `duplicate_candidates` as `passage_relation`, and is
still shown to a reviewer. What changes is that it cannot currently justify an auto-exclusion,
because a shared section with no shared text is not evidence of anything.

**Why not skip the table entirely:** building it costs a migration and a job; discovering later that
a re-import filled `description` and that nothing normalizes it would cost a schema change with data
in it.

### Decision 4 — The Image Boundary Replaces the Passage Boundary as the Blocking Rule

§17's mandatory rule — two identical questions on two different passages are not duplicates — has no
passages to apply to. The mirror shows what it does have: 5,582 questions with an attached image, and
image-bearing duplicate groups conflicting at **83.8%** against a **24.4%** base rate (§2.2).

**Decision:** the rule is preserved in force and re-grounded on media:

```text
Two questions with identical search_text whose attached image sets differ
  -> never auto-clustered
  -> never escalated as conflicting_duplicate
  -> recorded as a candidate with media_relation = 'different_media', and shown to a
     human with both images side by side
```

Enforced at the hash layer (Phase 3) and again at candidate generation (Phase 5) — **twice, and not
left to the model**, exactly as §17 requires of the passage rule. The verdict prompt is told about it
as a third line of defence (Phase 8).

**Why this is a re-grounding and not a scope change:** §8's underlying claim is that a question's
identity includes its stimulus. In this bank the stimulus is an image. Applying the rule to the
medium in use is what §8 asks for; applying it to an empty column is not.

### Decision 5 — `conflicting_duplicate` Is a Prioritized Backlog, Not a Queue

§17 calls this "the most important immediate deliverable in the whole program" and describes a path
that reads as though conflicts arrive one at a time. The mirror says roughly **1,125 same-text groups
carry genuinely different answer keys** after excluding the image-bearing groups Decision 4 removes.
At §13.3's 2–5 minutes per arbitration that is 37–94 trainer hours — against a §13.3 budget that
allotted "variable, high priority" and a program-wide total of 30–60 hours.

**Decision:** the escalation path ships as specified, but ordered and capped rather than exhaustive:

```text
1. Rank every conflicting cluster by affected_student_count, computed by deterministic SQL
   over source_item_stats.n  (Principle IV: the LLM never computes this number)
2. The trainer works the ranked list; the queue is never presented as a finite task
3. lab:dedup --step=conflict-report generates a report of the top N by impact, regenerable,
   which a human acts on in the Production admin
4. The full backlog stays visible and countable in the console, so its size is never hidden
```

**Why ranking is the whole design:** a wrong answer key on a question 4,000 students answered and a
wrong key on one answered twice are not the same defect. Without `source_item_stats.n` the backlog
would be an undifferentiated 1,125 items; with it, the first day of trainer time reaches the errors
that touched the most students. P1 built that column, and this is what it was for.

**Escalated to §8 as item F**, because the volume is a scheduling commitment no developer can make.

### Decision 6 — One Command, One Labeling Screen, No New Frameworks

**Decision:** one command, `php artisan lab:dedup {--step=…} {--resume} {--chunk=}`, following
`lab:import`'s established shape rather than `lab:profile`'s, dispatching to job classes under
`app/Jobs/Dedup/`. It reuses `ImportRunRecorder`, `ResumeCursor`, `ImportErrorRecorder` and
`BatchUpsert` unchanged; `import_runs.kind` is a plain `string(20)`, so `p2_derive_text`, `p2_embed`,
`p2_candidates` and `p2_verdict` cost nothing to add.

**And one Filament labeling screen with three modes** (operator decision, 2026-08-27):

```text
purpose = 'calibration'        the 7-value relation taxonomy — must match the verdict schema
                               exactly, or precision/recall cannot be computed against it
purpose = 'spot_check'         confirm / reject, for the 5% sample of the auto-clustered band
purpose = 'uncertain_review'   the five production buttons from §17
```

Decisions land in the database immediately, attributed and timestamped. `reviewer_id` references the
Lab's **own** `users` table — a framework table holding operator accounts, exempt from
`NoPiiInLabSchemaTest` and unrelated to Production identities.

**The P3 statistics row is omitted, not faked** (operator decision). `App\Support\Dedup\P3StatsLookup`
reports unavailability and the review screen hides the row entirely rather than rendering placeholder
dashes. P2 does not model P3's eventual `item_statistics` schema before P3 exists; when P3 ships, the
row appears with no rework to anything P2 stored.

## 3.2 Two Smaller Decisions — Declared, Not Implicit

**HTML cleaning is implemented and costs nothing.** `has_html` is 0 across 29,142 rows, so
`clean_text` is currently a whitespace-and-Unicode pass. The HTML branch is still written, because
`clean_text`'s contract is "technical cleanup, meaning preserved" and a later import that carries
markup must not silently produce a hash over raw tags. It is priced at zero because it is a few lines
inside a class Phase 2 builds anyway.

**Deleted questions are derived but never compared.** All 29,142 rows get a `source_question_derived`
row, matching P1's "copy everything, filter at analysis" rule. Every embedding, candidate and cluster
step filters `WHERE source_deleted_at IS NULL`. A soft-deleted question is history; it is not a
duplicate of anything.

---

# 4. Scope

## 4.1 In Scope

```text
An Arabic normalizer: raw_text -> clean_text -> search_text, unit-tested, with ة -> ه
  forbidden by an explicit negative test
Layer 0/1 hashing and auto-clustering of literal and formatting duplicates, zero LLM cost
The media boundary rule, enforced at hashing AND at candidate generation
A pg_trgm GIN index and lexical candidate generation
Embeddings over the 11,416 distinct texts: stem_embedding + full_embedding, the mandatory
  prefix, 768 dims, embedding_config_version on every row, truncation logged never dropped
pgvector top-K=20 candidate generation, exact scan, no HNSW
A 400-pair human-labelled evaluation set, stratified across real similarity bands
Threshold calibration (T_low / T_high) against precision >= 0.90 at recall >= 0.70
A conditional embedder benchmark: bge-m3, multilingual-e5-large, Matryoshka-512
POST /verdict on the existing ai-service: structured JSON, schema-validated, prompt-versioned
LLM calls rationed to the uncertain band, proven by a counter that must read zero
Auto-clustering of the high-similarity band with a 5% human spot-check
The conflicting_duplicate backlog, ranked by affected students, with a generated report
An Arabic Filament review console: side by side, five actions, every decision attributed
```

## 4.2 Out of Scope — Explicitly

```text
Deleting any question, under any relation_type, ever   <- forbidden program-wide; FKs carry history
Writing anything to Production MySQL                   <- the conflict report is an artefact a human acts on
Editing raw_text, or any mirror column, in place       <- P2 adds tables; it never mutates the mirror
Discrimination (r_pbis), p-value, distractor analysis  <- P3; P2 only reads source_item_stats.n
Classification, taxonomy, subject tagging, coverage    <- P5
Question generation, rewriting, authoring help         <- P9
A passage-excerpt embedding pipeline                   <- Decision 3: zero stimulus rows in this snapshot
Modelling P3's item_statistics schema before it exists <- Decision 6: the row is omitted, not guessed
An HNSW index                                          <- constitution VII: indexes are earned; ~90 MB exact-scans fine
Inventing the governing plan's missing §21             <- §15; §17's own gate numbers are used directly
A new queue framework, or Horizon                      <- ADR-011: the database driver, already proven
Switching the embedding model without approval         <- Principle I names the embedding contract
```

**The guardrail, in this project's terms:** if you find yourself writing code that decides what a
question is *about*, you are in P5; if you find yourself writing a statement that removes a question,
you are nowhere in this program.

---

# 5. Architecture — Data Flow

```text
                    source_questions (28,747 active) · source_question_options · source_media
                                          │
                                          ▼
  ┌────────────────────────────────────────────────────────────────────────────────┐
  │  PHASE 3   raw_text ─► clean_text ─► search_text          -> source_question_derived
  │            (HTML no-op today · NFC · tatweel · diacritics · digits · Alef)
  │            MEANING PRESERVED — ة is never rewritten to ه
  └────────────────────────────────────────────────────────────────────────────────┘
                                          │
   LAYER 0  question_text_hash            │  zero cost
   LAYER 1  question_with_options_hash    ▼  zero cost
  ┌────────────────────────────────────────────────────────────────────────────────┐
  │  ~4,689 groups collapse · 17,331 redundant rows resolved with no model at all   │
  │  BLOCKED: groups whose members carry different images  (Decision 4)             │
  └────────────────────────────────────────────────────────────────────────────────┘
                                          │
                        11,416 distinct texts survive to the paid layers
                                          │
                                          ▼
  ┌────────────────────────────────────────────────────────────────────────────────┐
  │  PHASE 4   stem_embedding + full_embedding      ~22,832 calls · 10–20 min       │
  │            prefix: "task: sentence similarity | query: {text}" · 768 · l2norm   │
  │            embedding_config_version on every row · truncation logged            │
  └────────────────────────────────────────────────────────────────────────────────┘
                                          │
   LAYER 2  pg_trgm GIN on search_text    │  low cost
   LAYER 3  pgvector top-K=20, exact scan ▼  medium cost
  ┌────────────────────────────────────────────────────────────────────────────────┐
  │  PHASE 5   duplicate_candidates   (~114,000 undirected pairs, upper bound)      │
  │            media_relation computed at insertion — the rule, enforced twice      │
  └────────────────────────────────────────────────────────────────────────────────┘
                                          │
              PHASE 6 stratified sample ──┴──► 400 pairs ──► 🔴 human labels
                                          │                        │
                                          │    PHASE 7 calibrate ◄─┘
                                          │    T_low / T_high, gate: P>=0.90 @ R>=0.70
                                          ▼
        ┌─────────────────┬───────────────────────────┬─────────────────┐
        │ sim <= T_low    │  T_low < sim < T_high     │ sim >= T_high   │
        │ dropped         │  PHASE 8  LLM verdict     │ PHASE 9 auto-   │
        │ zero cost       │  target <= 5,000 pairs    │ cluster + 5%    │
        │                 │  ~6 h overnight batch     │ spot-check      │
        │                 │  COUNTER MUST READ ZERO   │ zero LLM cost   │
        │                 │  outside this band        │                 │
        └─────────────────┴───────────┬───────────────┴─────────────────┘
                                      ▼
  ┌────────────────────────────────────────────────────────────────────────────────┐
  │  PHASE 10  duplicate_clusters · cluster_members · reviews                      │
  │            conflicting_duplicate ranked by affected_student_count              │
  │              (deterministic SQL over source_item_stats.n — never the LLM)      │
  │            Arabic review console · docs/reports/p2-conflicting-duplicates.md   │
  └────────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
                        A human, in the Production admin.
                        The Lab never writes to injazedu.
```

---

# 6. Schema

## 6.1 Conventions

Every table below is **new, Lab-owned, and Postgres-native**. None mirrors a source table, so none
carries P1's common mirror columns (`source_system`, `imported_at`, `payload_hash`, …) and none
belongs on either allowlist in `config/lab.php` — those lists govern reads against MySQL, and P2
performs none.

Two FK conventions, per Decision 2: a column named `*_source_id` references the mirror by
**`source_id`**; a column named `*_id` references a P2 table by its Lab surrogate `id`.

## 6.2 The Eight Tables

### `source_question_derived` — the text layers and the vectors, one row per question

```text
id                          BIGSERIAL PK
question_source_id          BIGINT  UNIQUE, indexed      -> source_questions.source_id
clean_text                  TEXT        technical cleanup only; meaning preserved
search_text                 TEXT        the comparison representation
question_text_hash          CHAR(64)    indexed   SHA256(search_text)                  Layer 0
question_with_options_hash  CHAR(64)    indexed   SHA256(search_text ⊹ ordered options) Layer 1
media_fingerprint           CHAR(64)    nullable, indexed   SHA256 of the ordered attached
                                        image paths — null when the question carries no media
normalizer_version          TEXT        versions the ArabicNormalizer ruleset
stem_embedding              vector(768) nullable
full_embedding              vector(768) nullable
embedding_config_version    TEXT        nullable
stem_truncated              BOOLEAN     default false
full_truncated              BOOLEAN     default false
text_computed_at            TIMESTAMPTZ nullable
embedded_at                 TIMESTAMPTZ nullable
```

One row per question including soft-deleted ones (§3.2). `media_fingerprint` is what Decision 4's
rule compares; it is computed in the same pass as the hashes and costs nothing.

### `source_section_derived` — built, and empty on this snapshot

```text
id                       BIGSERIAL PK
section_source_id        BIGINT  UNIQUE, indexed        -> source_sections.source_id
clean_text               TEXT
search_text              TEXT
stimulus_text_hash       CHAR(64)  indexed
normalizer_version       TEXT
text_computed_at         TIMESTAMPTZ nullable
```

Populated only `WHERE has_stimulus = true` — currently zero rows (Decision 3). No embedding column:
adding one before a passage exists would be speculation, and it is one migration to add later.

### `duplicate_candidates` — every pair the paid layers proposed

```text
id                                     BIGSERIAL PK
question_a_source_id                   BIGINT indexed   canonical: always the smaller source_id
question_b_source_id                   BIGINT indexed
trgm_score                             DOUBLE nullable                            Layer 2
stem_cosine_sim                        DOUBLE nullable                            Layer 3, primary
full_cosine_sim                        DOUBLE nullable                            Layer 3, secondary
hash_match_level                       TEXT nullable    exact | formatting | null
same_section                           BOOLEAN
media_relation                         TEXT   same_media | different_media | no_media
band                                   TEXT nullable    exact | high | uncertain | low
llm_verdict_relation                   TEXT nullable
llm_same_learning_objective            BOOLEAN nullable
llm_same_correct_answer                BOOLEAN nullable
llm_confidence                         DOUBLE nullable
llm_issues                             JSONB nullable
llm_recommended_action                 TEXT nullable
llm_review_required                    BOOLEAN nullable
llm_prompt_version                     TEXT nullable
llm_verdict_at                         TIMESTAMPTZ nullable
generated_at                           TIMESTAMPTZ
embedding_config_version_at_generation TEXT nullable

UNIQUE (question_a_source_id, question_b_source_id) · INDEX (band)
```

`band` stays null until Phase 7 calibrates. The seven `llm_*` columns are exactly §17's verdict
schema, one column per field, so a verdict is queryable rather than buried in JSON.

### `duplicate_clusters` — the grouping, never a deletion

```text
id                            BIGSERIAL PK
canonical_question_source_id  BIGINT indexed        the lowest source_id in the group — deterministic
relation_type                 TEXT   exact_duplicate | formatting_duplicate | semantic_duplicate |
                                     same_objective_variant | probable_duplicate |
                                     conflicting_duplicate | related_not_duplicate
status                        TEXT   auto | pending_review | confirmed | rejected |
                                     urgent_review | resolved | skipped
source_layer                  TEXT   hash | high_band_auto | llm_verdict | human_manual
affected_student_count        INT nullable   deterministic SQL over source_item_stats.n;
                                             populated for conflicting_duplicate — the ranking key
created_at / updated_at       TIMESTAMPTZ
```

### `duplicate_cluster_members`

```text
id                     BIGSERIAL PK
duplicate_cluster_id   BIGINT indexed   -> duplicate_clusters.id
question_source_id     BIGINT indexed   -> source_questions.source_id
is_canonical           BOOLEAN default false
added_at               TIMESTAMPTZ
UNIQUE (duplicate_cluster_id, question_source_id)
```

### `duplicate_reviews` — the one irreproducible artefact in the Lab

```text
id                     BIGSERIAL PK
duplicate_cluster_id   BIGINT indexed   -> duplicate_clusters.id
decision               TEXT   same | valid_variant | not_duplicate | conflict | skip
reviewer_id            BIGINT           -> users.id (Lab operator accounts — never Production PII)
reviewed_at            TIMESTAMPTZ
previous_status        TEXT nullable
new_status             TEXT nullable
notes                  TEXT nullable
```

Append-only. A human decision never overwrites the AI verdict column; the two sit side by side, which
is what makes "how often was the model wrong" answerable later. Constitution III notes these rows are
the one thing in the Lab with no other source — durability is a go-live concern, not a local gate.

### `duplicate_eval_pairs` — the labelled set, reused by all three review modes

```text
id                                    BIGSERIAL PK
question_a_source_id                  BIGINT indexed
question_b_source_id                  BIGINT indexed
purpose                               TEXT  calibration | spot_check | uncertain_review
sampled_band                          TEXT  the similarity stratum it was drawn from
sim_score_at_sampling                 DOUBLE nullable
embedding_config_version_at_sampling  TEXT nullable
media_relation                        TEXT nullable
human_relation                        TEXT nullable   one of the seven relation values
human_same_learning_objective         BOOLEAN nullable
human_same_correct_answer             BOOLEAN nullable
human_confidence                      DOUBLE nullable
labelled_by                           BIGINT nullable  -> users.id
labelled_at                           TIMESTAMPTZ nullable
notes                                 TEXT nullable
created_at                            TIMESTAMPTZ

UNIQUE (question_a_source_id, question_b_source_id, purpose) · INDEX (purpose)
```

`human_relation` uses the **same seven values** as the verdict schema. A coarser labeling vocabulary
would make precision and recall incomputable against the model's output.

### `duplicate_eval_runs` — one row per calibration or benchmark, never overwritten

```text
id                              BIGSERIAL PK
run_kind                        TEXT   calibration | embedder_benchmark
embedder_model                  TEXT
embedder_dimension              INT
embedding_config_version        TEXT nullable
eval_pair_count                 INT
recall_at_20                    DOUBLE nullable   the decisive benchmark metric (§12.4)
precision_at_threshold          DOUBLE nullable
recall_at_threshold             DOUBLE nullable
threshold_low                   DOUBLE nullable   T_low
threshold_high                  DOUBLE nullable   T_high
projected_uncertain_band_count  INT nullable      projected over the full candidate pool
storage_mb                      DOUBLE nullable
time_per_1k_ms                  DOUBLE nullable
gate_passed                     BOOLEAN nullable  precision >= 0.90 AND recall >= 0.70
is_selected                     BOOLEAN default false
computed_at                     TIMESTAMPTZ
notes                           TEXT nullable
```

The `source_snapshots` pattern applied to calibration: a re-run produces a comparison, not a
replacement, so "did changing the model help?" stays answerable.

---

# 7. Implementation Plan — 11 Phases

Each phase has a goal, steps, files, and a single checkable acceptance criterion. The numbering is
continuous, and the phases are ordered by real dependencies, not by preference.

---

## Phase 1 — The Eight Tables

**Goal:** every P2 table exists in Postgres, owned by Laravel, before any derivation code runs.

**Steps:**
1. Eight migrations, one per table, in the dependency order of §6.2.
2. `vector(768)` via Laravel 13's native `$table->vector($col, 768)` — already proven by
   `lab_vector_probes` (P0), so no `pgvector/pgvector-php` package is added.
3. **Only the indexes each table needs to be written and read.** No GIN trigram index here: Phase 5
   earns it when a query needs it (constitution VII).
4. Eight Eloquent models following `SourceQuestion.php`'s shape — `protected $connection = 'pgsql'`,
   explicit casts, and relations wired through `source_id` per Decision 2.
5. A header comment on every migration stating the table is P2-owned and is not part of the P1
   mirror, so a later reader does not mistake it for something the ETL maintains.
6. Extend `NoPiiInLabSchemaTest` to cover the eight new tables. `duplicate_reviews.reviewer_id` and
   `duplicate_eval_pairs.labelled_by` reference the framework `users` table and are expected to pass;
   if the test's rules need a narrow, explicit exemption, that is a finding for §15, not a silent edit.

**Files:** `apps/lab/database/migrations/` (8 new) · `apps/lab/app/Models/SourceQuestionDerived.php`,
`SourceSectionDerived.php`, `DuplicateCandidate.php`, `DuplicateCluster.php`,
`DuplicateClusterMember.php`, `DuplicateReview.php`, `DuplicateEvalPair.php`, `DuplicateEvalRun.php`

**Acceptance criterion:** `php artisan migrate --env=testing` builds all eight against
`injazedu_lab_test`, `NoPiiInLabSchemaTest` passes over them, and a test asserting that a
`duplicate_cluster_members` row joins to a real `source_questions` row **through `source_id`**
succeeds — the Decision 2 defect caught by a test rather than by a reviewer.

---

## Phase 2 — The Arabic Normalizer and the Hash Core

**Goal:** the rules everything downstream depends on are proven correct before they touch a row.

**Steps:**
1. `App\Support\Dedup\ArabicNormalizer` with two methods:
   - `clean(string $raw): string` — HTML stripping (a no-op on this snapshot, §3.2), whitespace
     collapse, Unicode NFC. **Meaning preserved.**
   - `search(string $clean): string` — NFC, tatweel `ـ` removal, diacritic removal, punctuation
     normalization, Arabic↔Latin digit unification, selected Alef-form normalization, and option-label
     stripping where present.
2. `App\Support\Dedup\OptionsNormalizer` — builds the ordered normalized options string for Layer 1,
   consuming P1's existing `option_index`. **`OptionIndexDeriver` is reused, never re-derived**: the
   `options.order` tie problem is already solved and re-solving it differently would silently produce
   different hashes.
3. `App\Support\Dedup\DuplicateHasher` — `questionTextHash()`, `questionWithOptionsHash()`, and
   `mediaFingerprint(array $orderedPaths)`.
4. `normalizer_version` is a constant on the normalizer, written to every derived row. Changing a
   rule bumps it, which makes a stale hash visible instead of silently wrong — the same reasoning
   `embedding_config_version` exists for.
5. Unit tests: tatweel removal · diacritic removal · digit unification · **idempotence**
   (`search(search(x)) === search(x)`) · hash stability and sensitivity (identical input → identical
   hash; one changed option → different hash; options presented in a different order → **identical**
   hash, because `option_index` orders them) · and an explicit negative test asserting that **`ة` is
   never rewritten to `ه`**.

**Files:** `apps/lab/app/Support/Dedup/ArabicNormalizer.php`, `OptionsNormalizer.php`,
`DuplicateHasher.php` · `apps/lab/tests/Unit/Dedup/`

**Acceptance criterion:** the unit suite is green, including the `ة → ه` negative test — a test that
fails the moment someone adds that transform, which is the only durable way to keep a rule that is
tempting to "fix".

> **⚠️ Superseded by the spec, 2026-08-28.** `specs/006-p2-duplicate-intelligence/spec.md` FR-011,
> FR-012 and FR-139 to FR-143 govern this phase. Three changes: **(a)** `search()` also applies
> Unicode **case folding**, which this plan omits while quoting a distinct-stem count measured with
> it; **(b)** option-label stripping names **both** alphabets — 3,604 options begin with a Latin label
> against 1,200 Arabic, and 9.9% of the bank has no Arabic character (notes.md N10); **(c)** a fourth,
> **recall-only** `fuzzy()` form tolerates the `ة`/`ه` typo for *candidate matching only* and can
> never reach a hash or produce an `exact_duplicate`. That last change required narrowing
> constitution IV, approved by the operator — the constitution is now **v2.5.0**.

---

## Phase 3 — Text Layers, Hash Layers, and the First Clustering

**Goal:** every question carries its three layers and its hashes, and Layers 0–1 resolve the bank's
60.3% redundancy with no model involved and the media rule already in force.

**Steps:**
1. `App\Jobs\Dedup\DeriveQuestionTextLayers` — chunked and resumable via `ResumeCursor`, reads each
   question with its options and its `source_media` rows, applies Phase 2, upserts
   `source_question_derived` via `BatchUpsert`. Runs over all 29,142 rows (§3.2).
2. `App\Jobs\Dedup\DeriveSectionTextLayers` — the same for
   `source_sections WHERE has_stimulus = true`. Expected to process **zero rows** on this snapshot
   (Decision 3); it logs the count rather than assuming it.
3. `App\Jobs\Dedup\ClusterExactHashMatches` — groups by `question_with_options_hash` having a count
   above one, and **splits every group by `media_fingerprint` before clustering** (Decision 4). Two
   questions sharing a hash but carrying different images do not enter the same cluster; they are
   written to `duplicate_candidates` with `media_relation = 'different_media'` for a human instead.
   Canonical member is the lowest `question_source_id` — deterministic, per constitution IV.
4. A stem-only match (`question_text_hash` equal, `question_with_options_hash` differing) is **not**
   auto-clustered: differing options may be a real difference. It becomes a candidate with
   `hash_match_level = 'formatting'`, routed to the high band in Phase 9.
5. `php artisan lab:dedup --step=derive-text {--resume}` and `--step=hash-cluster`, recorded in
   `import_runs` under `kind = 'p2_derive_text'`.

**Files:** `apps/lab/app/Jobs/Dedup/DeriveQuestionTextLayers.php`, `DeriveSectionTextLayers.php`,
`ClusterExactHashMatches.php` · `apps/lab/app/Console/Commands/LabDedup.php`

**Acceptance criterion:** every one of the 29,142 questions has exactly one `source_question_derived`
row with a non-null `search_text`; re-running `--step=hash-cluster` produces an identical cluster
count (idempotent); and a Feature test proves that two questions with identical text and **different
attached images are not clustered together**.

---

## Phase 4 — Embeddings, Over the Distinct Texts Only

**Goal:** every distinct surviving text carries `stem_embedding` and `full_embedding`, with the
config version recorded and every truncation logged rather than silently accepted.

**Steps:**
1. `App\Support\AiService\EmbeddingClient` — a Laravel HTTP client against
   `config('lab.ai_service.base_url')` `POST /embed`. **Send raw text**: the Python service owns the
   mandatory prefix (`apps/ai-service/app/embedding.py`), and applying it on both sides would corrupt
   every vector while looking correct.
2. `App\Jobs\Dedup\EmbedQuestions` — selects **one representative per
   `question_with_options_hash`** among active questions (~11,416 rather than 28,747, §2.2), embeds
   stem and full, and writes the result to every member of the hash group. Identical text produces an
   identical vector; calling the model 17,331 extra times to confirm that is exactly the waste
   Principle VII forbids.
3. Store `embedding_config_version` from the response on every row, plus `stem_truncated` /
   `full_truncated` from `prompt_eval_count >= context_length`. A truncation is recorded through
   `ImportErrorRecorder` (new code `EMBEDDING_TRUNCATED`); a non-2xx or `zero_norm_vector` response is
   `EMBEDDING_FAILED` and the batch continues.
4. **Load the chat model before the embedding model** when both are needed in a session — the reverse
   order evicts the embedding runner on this 16 GB machine (measured, P0).
5. `php artisan lab:dedup --step=embed {--resume} {--chunk=}` over the database queue, recorded under
   `kind = 'p2_embed'` with `elapsed_seconds`, so the projection in §10 is checked against reality.

**Files:** `apps/lab/app/Support/AiService/EmbeddingClient.php` ·
`apps/lab/app/Jobs/Dedup/EmbedQuestions.php` · `apps/lab/app/Support/Import/ImportErrorCode.php` (two codes)

**Acceptance criterion:**

```sql
SELECT count(*) FROM source_question_derived d
  JOIN source_questions q ON q.source_id = d.question_source_id
 WHERE q.source_deleted_at IS NULL AND d.stem_embedding IS NULL;
```

returns **0**; every populated row shares one `embedding_config_version`; and the number of `/embed`
calls recorded in `import_runs` is close to 2 × 11,416, not 2 × 28,747 — the deduplication actually
saved the work it was supposed to save.

---

## Phase 5 — Candidate Generation: Trigram and Vector

**Goal:** produce the candidate pairs every later phase consumes, from both a cheap lexical pass and
the semantic pass, with the media rule enforced a second time at the point of insertion.

**Steps:**
1. A migration creating the trigram index — **earned here, not in Phase 1**:
   `CREATE INDEX ... ON source_question_derived USING gin (search_text gin_trgm_ops)` via
   `DB::statement()`, since Blueprint has no trigram helper.
2. `App\Support\Dedup\TrigramCandidateFinder` — `similarity()` above a floor, capped per question.
3. `App\Support\Dedup\VectorCandidateFinder` — cosine distance (`<=>`) over `stem_embedding`,
   top-K=20 per distinct text, **exact scan and no HNSW**: ~11,416 × 2 × 768 × 4 bytes ≈ 70 MB, well
   inside §13.4's "an exact scan is sufficient to begin with."
4. `App\Jobs\Dedup\GenerateCandidatePairs` — merges both sources into `duplicate_candidates`,
   canonicalising each pair so `question_a_source_id < question_b_source_id`, and computing
   `same_section` and `media_relation` **at insertion time**. A `different_media` pair is recorded
   (so a human and the model can still see it) but is permanently ineligible for auto-clustering in
   Phases 3 and 9 — the rule enforced twice, exactly as §17 demands for the passage case.
5. `php artisan lab:dedup --step=candidates {--resume}`, recorded under `kind = 'p2_candidates'`.

**Files:** `apps/lab/database/migrations/*_add_trgm_index_to_source_question_derived.php` ·
`apps/lab/app/Support/Dedup/TrigramCandidateFinder.php`, `VectorCandidateFinder.php` ·
`apps/lab/app/Jobs/Dedup/GenerateCandidatePairs.php`

**Acceptance criterion:** `EXPLAIN` on a trigram similarity query shows the GIN index in use rather
than a sequential scan; the candidate count is consistent with top-K=20 over 11,416 distinct texts
(≈114,000 undirected pairs, upper bound); and a test proves a `different_media` candidate is present
in the table **and** excluded from every auto-cluster path.

---

## Phase 6 — The 400-Pair Evaluation Set

**Goal:** a stratified, human-labelled set that makes the gate computable — sampled from Phase 5's
real, uncalibrated scores, which is what resolves the chicken-and-egg.

**Steps:**
1. `App\Support\Dedup\EvalSetSampler` — stratifies `duplicate_candidates` across similarity deciles
   (0.95–1.00 down to 0.50–0.60), with explicit quotas for: exact-hash pairs (a sanity floor),
   `different_media` pairs (so Decision 4's rule is measured, not assumed), same-section and
   cross-section pairs, and a random-pair negative control drawn from outside the candidate set
   entirely. No threshold is needed to stratify — only real scores, which Phase 5 has.
2. `php artisan lab:dedup --step=eval-sample --count=400` writes `duplicate_eval_pairs` rows with
   `purpose = 'calibration'` and `human_relation` null.
3. The **Filament labeling screen** (Decision 6), mode `calibration`: question A and B side by side
   with their options, correct answers, and attached images; the full seven-value relation taxonomy;
   `same_learning_objective`, `same_correct_answer` and a confidence field; keyboard shortcuts,
   because this screen is used ~1,900 times across the project.
4. A doubled subsample labelled by both a moderator and a trainer, so inter-rater agreement is
   measured rather than assumed (§13.3).
5. `php artisan lab:dedup --step=eval-report` generates `docs/reports/p2-eval-set.md` from the stored
   rows — a pure function over the table, following P1's generated-report precedent, never
   hand-edited.

**Files:** `apps/lab/app/Support/Dedup/EvalSetSampler.php` ·
`apps/lab/app/Filament/Resources/DuplicateEvalPairs/` ·
`apps/lab/app/Support/Dedup/EvalSetReportGenerator.php`

**Acceptance criterion:** exactly 400 rows with `purpose = 'calibration'`, every similarity decile
non-empty, and the `different_media` and negative-control quotas both filled; after labeling, every
row has a non-null `human_relation`.

> **⚠️ Superseded by the spec, 2026-08-28.** `spec.md` FR-050, FR-051 and FR-144 to FR-149 govern this
> phase. The set is drawn in **waves of 100** up to the same **400 ceiling**, expanded only when a 95%
> Wilson interval says the sample cannot decide — which **raises** the gate, since it must now clear
> an interval rather than a point estimate. Each wave is independently stratified, so stopping early
> yields a smaller ruler and never a biased one. Three quotas are added (`formatting`,
> `orthographic`, answer-key conflicts). An **optional AI pre-label** may assist, stored in separate
> `ai_*` columns and hidden by the screen until the human has committed their own label — the human
> label remains the sole ground truth. The stateless `/verdict` endpoint moves into this phase so the
> pre-label has something to call; the band guard and rationed dispatch stay in Phase 8.

**🔴 This phase blocks on human time — §8 item A.**

---

## Phase 7 — Calibration, the Gate, and the Conditional Benchmark

**Goal:** derive `T_low` and `T_high` from the labelled set, test §17's own gate, and — only on
failure — benchmark alternative embedders on the same fixed set.

**Steps (always):**
1. `App\Support\Dedup\ThresholdCalibrator` — a deterministic sweep over candidate thresholds against
   `human_relation`, with `exact_duplicate ∪ semantic_duplicate` as the positive class, taken
   literally from §17. **The gate is §17's own: precision ≥ 0.90 at recall ≥ 0.70.** The governing
   plan's pointer to "the target in §21" is a dangling reference to a section that does not exist
   (§15 item 1) and is not waited on.
2. `php artisan lab:dedup --step=calibrate` writes one `duplicate_eval_runs` row
   (`run_kind = 'calibration'`, `embedder_model = 'embeddinggemma:300m-qat-q4_0'`) carrying the
   precision, recall, both thresholds and `gate_passed`.
3. Project the thresholds across the **full** candidate pool to compute
   `projected_uncertain_band_count`. If it exceeds **8,000**, raise `T_low` and recompute — a logged,
   deliberate tightening, recorded as another row, never a silent adjustment.
4. Generate `docs/reports/p2-calibration.md`.

**Steps (only if `gate_passed = false`):**
5. 🔴 Human approval to pull `bge-m3` and `multilingual-e5-large` — a new dependency on this machine
   (§8 item B).
6. `App\Support\Dedup\EmbedderBenchmark` — re-embeds **only the ~800 questions in the eval set**, not
   the bank, for each alternative plus embeddinggemma truncated to 512 (Matryoshka). Computes
   Recall@20, Precision@T, storage and time per 1K. **Recall@20 decides** (§12.4): a pair that is
   never shortlisted cannot be rescued by any verdict.
7. `--step=benchmark-embedders` writes one `duplicate_eval_runs` row per model.
8. 🔴 If an alternative clears the gate and embeddinggemma does not, **stop and ask** (§8 item C):
   Principle I names the embedding contract explicitly. Approval means an ADR, then Phases 4 and 5 
   re-run in full before this phase re-calibrates. If nothing clears the gate, that is a program-level
   finding for §15 — the gate is not lowered to let the project proceed.

**Files:** `apps/lab/app/Support/Dedup/ThresholdCalibrator.php`, `EmbedderBenchmark.php`,
`CalibrationReportGenerator.php`

**Acceptance criterion:** `duplicate_eval_runs` holds exactly one `is_selected = true` row whose
precision ≥ 0.90 and recall ≥ 0.70 — **or** it explicitly records `gate_passed = false` alongside a
benchmark row set. There is no path through this phase that proceeds past a failed gate silently.

---

## Phase 8 — The Verdict Endpoint and the Uncertain Band

**Goal:** extend the running ai-service with a structured-output verdict endpoint, and spend the
model's time only where cheaper layers genuinely could not decide.

**Steps:**
1. `apps/ai-service/app/verdict.py` — a client calling Ollama `/api/generate` against
   `settings.chat_model` (`gemma4:e2b-it-qat`, already declared in `config.py` and so far unused),
   following the call shape `health.py::probe_chat` already establishes. Ollama's `format` parameter
   constrains generation to a JSON Schema, **and** the response is validated with pydantic before it
   is returned — schema-constrained generation is not a guarantee, and constitution IV forbids
   parsing prose.
2. A versioned prompt at `apps/ai-service/app/prompts/duplicate_verdict_v1.md`. Changing it creates
   `v2`; it never overwrites `v1`, so a change in quality is attributable to the model or the prompt.
   The prompt states Decision 4's rule explicitly — **different attached images means not a
   duplicate** — as the third enforcement of a rule already enforced twice in SQL.
3. Question and option text reaches the prompt as **delimited data, never as instructions**
   (constitution III). A question whose text contains something that reads like a directive is a
   question, not a command.
4. `POST /verdict` in `apps/ai-service/app/main.py`, returning §17's seven fields verbatim.
5. `apps/ai-service/tests/test_verdict_contract.py` — schema validation, the seven-value enum, a
   malformed-response rejection case, and a synthetic identical-text-different-image pair that must
   **not** come back as `exact_duplicate`.
6. Laravel side: `App\Support\AiService\VerdictClient` and `App\Jobs\Dedup\RequestLlmVerdict`,
   dispatched only for `band = 'uncertain' AND llm_verdict_relation IS NULL`.
7. `App\Support\Dedup\LlmBudgetGuard::assertInBand()` runs before every dispatch and throws otherwise
   — the counter in §11 proves the rationing held, and the guard is what makes it hold.
8. `php artisan lab:dedup --step=verdict {--resume}` over the database queue, overnight, targeting
   ≤5,000 pairs ≈ 6 hours, recorded under `kind = 'p2_verdict'`.

**Files:** `apps/ai-service/app/verdict.py`, `main.py`, `prompts/duplicate_verdict_v1.md`,
`tests/test_verdict_contract.py` · `apps/lab/app/Support/AiService/VerdictClient.php` ·
`apps/lab/app/Jobs/Dedup/RequestLlmVerdict.php` · `apps/lab/app/Support/Dedup/LlmBudgetGuard.php`

**Acceptance criterion:**

```sql
SELECT count(*) FROM duplicate_candidates
 WHERE llm_verdict_relation IS NOT NULL AND band <> 'uncertain';
```

returns **0**, and `import_runs` for `kind = 'p2_verdict'` records the pair count and elapsed time
against the ≤5,000-pair / ~6-hour target.

---

## Phase 9 — The High Band and Its Spot-Check

**Goal:** cluster what is confidently similar without spending a model call, and still check the work.

**Steps:**
1. `App\Jobs\Dedup\AutoClusterHighBand` — for `band = 'high' AND media_relation <> 'different_media'`,
   create `duplicate_clusters(relation_type = 'probable_duplicate', status = 'auto',
   source_layer = 'high_band_auto')` with members.
2. A stratified **5%** sample of the newly created clusters is written to `duplicate_eval_pairs` with
   `purpose = 'spot_check'`, reusing Phase 6's screen in its confirm/reject mode.
3. A rejection sets the cluster's `status` to `rejected` and writes a `duplicate_reviews` row. The
   cluster is not deleted — a rejected cluster is evidence about the threshold, and deleting it would
   destroy the only record that the auto path made a mistake.
4. `php artisan lab:dedup --step=auto-cluster`.

This phase and Phase 8 read disjoint bands of the same table and **run in parallel**.

**Files:** `apps/lab/app/Jobs/Dedup/AutoClusterHighBand.php`

**Acceptance criterion:** no cluster with `source_layer = 'high_band_auto'` contains a pair whose
`media_relation` is `different_media` (a test), and the spot-check sample is 5% (±1) of the clusters
that phase created.

---

## Phase 10 — The Conflicting-Duplicate Backlog and the Review Console

**Goal:** the program's most urgent deliverable — turn ~1,125 detected answer-key conflicts into a
ranked, workable backlog and an Arabic console a moderator can actually work in.

**Steps:**
1. Any verdict of `conflicting_duplicate`, from the model or a human, creates a cluster with
   `status = 'urgent_review'`.
2. `App\Support\Dedup\AffectedStudentCounter` — deterministic SQL summing
   `source_item_stats.n` (`scope = 'active'`) across a cluster's members into
   `affected_student_count`. **The LLM never computes this number** (constitution IV); it is the
   ranking key Decision 5 depends on, and P1 built the column for exactly this.
3. `DuplicateClusterResource` (Filament, Arabic) — the review screen:
   - The list is ordered by `status = 'urgent_review'` first, then `affected_student_count`
     descending. Never by `id`.
   - The pair view: question A and B side by side with options, derived correct answers, and attached
     images; the similarity scores; the AI verdict and its confidence; and the five buttons
     `[نفسه] [تنويعة صحيحة] [ليس تكرارًا] [تعارض!] [تخطٍّ]`.
   - **The P3 statistics row is absent**, not stubbed (Decision 6). `P3StatsLookup` reports
     unavailability and the row does not render.
   - Every action writes a `duplicate_reviews` row and updates the cluster's status. The AI verdict
     columns are never overwritten.
   - The backlog's full size is displayed, so a worked queue never looks finished when it is not.
4. `php artisan lab:dedup --step=conflict-report` generates
   `docs/reports/p2-conflicting-duplicates.md` — the top N clusters by affected students, each with
   both questions, both answer keys, the student count, and the trainer's decision where one exists.
   Generated from stored rows, regenerable, never hand-edited.
5. **The report is where the Lab stops.** A human carries the corrections into the Production admin.
   Nothing in this phase, or this project, opens a write path to `injazedu`.

**Files:** `apps/lab/app/Support/Dedup/AffectedStudentCounter.php`, `ConflictReportGenerator.php`,
`P3StatsLookup.php` · `apps/lab/app/Filament/Resources/DuplicateClusters/`

**Acceptance criterion:** a synthetic conflicting cluster with known `source_item_stats.n` values
produces a report row whose affected-student count equals the raw SQL sum exactly; and a Feature test
(not a manual click-through) shows a reviewer decision persisted with its author, its timestamp, and
the AI verdict still intact beside it.

---

## Phase 11 — Guards, Tests, and the Acceptance Run

**Goal:** every acceptance criterion in §13 becomes something that executes.

**Steps:**
1. The suite:

```text
[ ] ArabicNormalizerTest        normalization rules, idempotence, and the ة -> ه negative test
[ ] HashClusterTest             literal and formatting duplicates found with no model
[ ] MediaBoundaryTest           identical text + different image never auto-clusters (Ph 3, 5, 9)
[ ] EmbeddingCoverageTest       every active question embedded; one config version throughout
[ ] TruncationLoggedTest        no truncated embedding without its flag and its error row
[ ] VectorSearchTest            top-K returns the expected neighbours on a fixture
[ ] CalibrationGateTest         the gate result is always recorded; no silent pass
[ ] LlmBandProofTest            zero verdicts outside band = 'uncertain'
[ ] VerdictContractTest         the /verdict JSON schema holds (Python side)
[ ] HumanOverrideTest           a decision is stored, attributed, and does not overwrite the verdict
[ ] NoDeletionTest              source_questions count identical before and after a full run
[ ] ConflictReportTest          affected-student counts reproducible from raw rows
[ ] IdempotencyTest             a second full run changes no cluster count
```

2. An eleventh `lab:health` check: `/verdict` reachability, following the existing
   `AbstractHealthCheck` pattern used by `ChatModelCheck` and `EmbeddingModelCheck`.
3. The acceptance run: `php artisan test`, `php artisan lab:health` (11/11, exit 0), then the full
   `lab:dedup` pipeline twice, asserting the second run changes nothing.
4. Update `README.md` with the P2 section (the commands and the console), and add any new key to
   `apps/lab/.env.example` with **no value**.
5. Record P2's measured facts in `CLAUDE.md` **and** `AGENTS.md`, byte-identical — verify with
   `diff CLAUDE.md AGENTS.md`.
6. Confirm no runbook, ADR, or handover document was created. The one permitted exception is a
   conditional ADR **only if** Phase 7 forced an embedder switch — a decision that is architectural,
   durable, and expensive to reverse. Absent that switch, no ADR is written.

**Files:** `apps/lab/tests/Feature/Dedup/` · `apps/lab/tests/Unit/Dedup/` ·
`apps/lab/app/Support/Health/VerdictEndpointCheck.php` · `README.md` · `.env.example` ·
`CLAUDE.md` · `AGENTS.md`

**Acceptance criterion:** the full suite is green, `lab:health` passes 11/11 exit 0, and the second
consecutive pipeline run produces zero new clusters and zero new candidates.

---

## 7.1 One Spec, One Branch

P2 is delivered as **one Spec Kit feature** covering all eleven phases — not several increments
(constitution, "How Work Gets Done"):

```text
/speckit.specify  ->  /speckit.plan  ->  /speckit.tasks  ->  /speckit.implement
feature:  006-p2-duplicate-intelligence
branch:   p2/duplicate-intelligence          (already created)
```

The phases above are the implementation order inside that one spec. **Phases 1 and 2 are independent**
and may be built alongside each other — Phase 2 is pure functions with no database. Everything from
Phase 3 to Phase 7 is strictly sequential, because each consumes the previous phase's rows.
**Phases 8 and 9 run in parallel**: they read disjoint bands of the same table and never write the
same row. Phase 10 needs both.

```text
1 ─┬─► 3 ─► 4 ─► 5 ─► 6 ─(🔴 human)─► 7 ─┬─► 8 ─┬─► 10 ─► 11
2 ─┘                                      └─► 9 ─┘
                                          │
              a failed gate at 7 loops back through 4 and 5 with a new embedder
```

---

# 8. Steps on Your Side (Human)

Items a developer cannot carry out alone — a decision, an authorization, or domain expertise.
**Items A and F are blocking; F is also the largest time commitment in the project.**

| # | Item | Why | When |
|---|------|-----|------|
| **A** | 🔴 **Label the 400-pair evaluation set** (5–10 hrs; a moderator, with a trainer for the final verdict on disagreements) | The gate cannot be computed without it, and Phase 7 cannot start. This is the measurement the entire cascade is tuned against | After Phase 6 produces the sample — **blocks Phase 7** |
| **B** | 🔴 **Approve pulling `bge-m3` and `multilingual-e5-large`** via Ollama | A new dependency on this machine (Principle I). ~2–3 GB of weights | **Only if** Phase 7's gate fails |
| **C** | 🔴 **Decide whether to switch the embedding model** | Principle I names the embedding contract as expensive to reverse: a switch invalidates every stored vector and forces a full re-embed of the bank | **Only if** a benchmarked alternative clears the gate and embeddinggemma does not |
| **D** | 🟡 Confirm or reject the **5% spot-check** of the auto-clustered high band (small volume) | The zero-LLM path still needs a human check — §17's cascade table requires it | After Phase 9 |
| **E** | 🟡 Review uncertain-band verdicts flagged `review_required` (≤1,500 pairs, 12–25 hrs, roughly halved by active learning after the first 150) | An AI verdict is a recommendation, never a decision (constitution II) | Ongoing from Phase 8 |
| **F** | ⚪ ~~🔴 Commit trainer time to the conflicting-duplicate backlog~~ — **reclassified to configuration, 2026-08-28.** See the note below | **928** groups (not ~1,125) × 2–5 min ≈ **31–77 hours** (notes.md N2). The operator is a solo developer and commits no trainer time; the backlog is a **standing queue** at `daily_review_cap = 10`, and **blocks nothing** (spec FR-151) | Nothing. Set the cap if 10 is wrong |
| **G** | 🟡 Receive `docs/reports/p2-conflicting-duplicates.md` and correct the answer keys **in the Production admin** | The Lab cannot write to Production and never will. This is the human hand-off that closes the loop and actually fixes what students see | As soon as the report has entries |
| **H** | ⚪ Confirm the `ة → ه` rule, the **fuzzy fold map**, and the option-label alphabets **for both scripts** with a trainer | Cheap to confirm, and a normalization rule that quietly changes meaning would corrupt every hash downstream of it. An Arabic-only label list would miss the majority of labelled options (spec FR-139) | Before Phase 3 runs at scale |

> **⚠️ Item F superseded by the spec, 2026-08-28.** It required committing 31–77 trainer hours before
> Phase 10 could ship, but nothing downstream depended on the backlog being **worked** — the console
> ships whether or not one item has been reviewed. The constitution's gate policy names that shape
> exactly: *"any check whose only purpose is satisfying another document."* The backlog instead ships
> **tiered and standing**: four deterministic `priority_tier` values from the measured
> `affected_student_count` distribution (spec FR-150), a soft cap of 10/day, and AI permitted to rank
> and recommend but never to compute the impact number or resolve a conflict (FR-153, FR-154). The
> measurement carries the decision: the **top 100 of the 928 groups hold 50.4% of the backlog's
> 269,153 total affected-student exposure**, and the top 200 hold 67.3% — so ten working days at the
> cap covers half the measured harm, against 31–77 hours to clear everything (notes.md N10).

**Program-level items carried over from P1 §8 and still open** — G, H and J there (the taxonomy
authoring request, booking reviewer sessions, and the legal provenance file). **The taxonomy request
is now overdue**: §20 calls it "the most important scheduling note in the document" precisely because
it starts at P2 and costs 2–4 weeks of elapsed time before P5 can use it.

---

# 9. Deliverables

```text
php artisan lab:dedup            nine steps · idempotent · resumable · rationed
8 migrations + 8 Lab-owned tables            no PII column, proven by a test
A tested Arabic normalizer       three layers, meaning preserved, ة -> ه forbidden by test
A duplicate hash core            Layer 0 + Layer 1 + media_fingerprint
A pg_trgm GIN index              earned in Phase 5, not assumed in Phase 1
~22,832 embeddings               over 11,416 distinct texts, not 28,747 rows
duplicate_candidates             ~114,000 pairs with band, media_relation and verdict columns
A 400-pair labelled eval set     stratified, with inter-rater agreement measured
duplicate_eval_runs              calibration and benchmark rows, never overwritten
POST /verdict on ai-service      schema-validated, prompt-versioned v1
A provable LLM budget            the counter reads zero outside the uncertain band
duplicate_clusters + members     nothing deleted, canonical member deterministic
A ranked conflicting backlog     ordered by affected students from source_item_stats.n
An Arabic Filament review console        five actions, every decision attributed
docs/reports/p2-eval-set.md · p2-calibration.md · p2-conflicting-duplicates.md   all generated
A 13-item test suite · an 11th health check · README · .env.example · CLAUDE.md = AGENTS.md
```

---

# 10. Effort Estimate

| Phase | Days |
|-------|------|
| 1 — The eight tables | 0.75 |
| 2 — Arabic normalizer and hash core | 1.25 |
| 3 — Text layers, hashes, first clustering | 1.0 |
| 4 — Embeddings over the distinct texts | 1.0 |
| 5 — Candidate generation: trigram and vector | 1.25 |
| 6 — The 400-pair evaluation set | 1.5 |
| 7 — Calibration and the gate | 1.0 |
| 8 — The verdict endpoint and the uncertain band | 2.0 |
| 9 — The high band and its spot-check | 0.75 |
| 10 — The conflicting backlog and the review console | 2.0 |
| 11 — Guards, tests, acceptance run | 1.0 |
| **Total** | **~12.5 days** |
| *Conditional:* embedder benchmark + full re-embed, only if the gate fails | *+1.5–2.0* |

**Why this sits inside §17's 10–15 day range rather than at the top of it.** Two measurements took
work out: the passage track has no rows to process (Decision 3, ~1.5 days not spent), and the 60.3%
redundancy means the embedding phase runs over 11,416 items instead of 28,747. Two put work back:
the media boundary rule is real engineering §17 did not anticipate, enforced in three places
(Decision 4), and the conflicting-duplicate backlog needs ranking, a report generator, and a console
that can present ~1,125 items usefully rather than the queue §17 imagined (Decision 5).

This estimate **excludes** the elapsed time waiting on item A, and **excludes** the 17–35 hours of
review in items D and E, which run alongside Phases 8–10 rather than stacking onto them. Item F's
37–94 trainer hours are not development time at all and continue well past this project's close.

---

# 11. Go / No-Go Thresholds

The first block changes scope. The second block is **not accepted** at any value.

| Condition | Decision |
|-----------|----------|
| **Precision ≥ 0.90 at recall ≥ 0.70** on the 400-pair set, exact + semantic class | **Required before Phases 8 and 9 run over the whole bank.** §17's own numbers, used directly — the missing §21 is not waited on |
| Projected uncertain-band pairs **> 8,000** | **Not accepted.** Raise `T_low` and recompute; beyond this the batch exceeds 10 LLM hours and stops being an overnight job |
| No embedder clears the gate, including the benchmarked alternatives | The cascade's semantic layers are **not trusted at scale**. Layers 0–2 still ship (they need no model and already resolve 60.3% of the bank); the semantic track becomes a program-level open item, not a lowered threshold |
| Inter-rater agreement on the doubled subsample is poor | The gate is measured against an unreliable ruler. Reconcile with a trainer and re-label before calibrating — **do not** calibrate against labels the labellers disagree about |
| The LLM sees **any** pair outside `band = 'uncertain'` | **Not accepted at any value above zero** — proven by the Phase 8 counter |
| Any question deleted, under any relation type | **Not accepted, ever.** `source_questions` count asserted identical before and after a full run |
| Any write from the Lab reaches `injazedu` | **Not accepted** — the three layers stay green, or the project stops |
| Any embedding row missing `embedding_config_version` | **Not accepted** — an unversioned vector is a silently wrong comparison waiting to happen |
| A truncated embedding without its flag and its error row | **Not accepted** — truncation is invisible unless it is recorded (§12.2) |
| Two identical questions with different images auto-clustered | **Not accepted** — enforced at hashing and at candidate generation, and tested |
| An embedder switch adopted without an explicit human decision | **Not accepted** — Principle I |
| The pipeline is not idempotent | **Not accepted** — a second run against the same snapshot must change nothing |

---

# 12. Risks and Mitigations

| Risk | Mitigation |
|------|------------|
| **A new P2 table joined to `source_questions.id` instead of `source_id`** | The single most likely schema defect (Decision 2). Every P2 column is named `*_source_id`, and Phase 1's acceptance criterion is a test that joins through it |
| A normalization rule that quietly changes meaning | The `ة → ه` negative test, `normalizer_version` on every derived row, and §8 item H confirming the rule set with a trainer before it runs at scale |
| The embedder's Arabic quality is not good enough | Phase 7 measures before committing the whole bank; Recall@20 decides; alternatives are predefined and benchmarked on the same fixed 400 pairs |
| The prefix or the model changing and silently invalidating stored vectors | `embedding_config_version` on every row; the service owns the prefix so it cannot be applied twice; a changed value forces a visible re-embed |
| Candidate-pair explosion beyond the budget | The 8,000-pair ceiling is a hard threshold, and every layer logs what it excluded so a silent truncation cannot look like coverage |
| **False positives from image-bearing questions** | Decision 4, enforced three times: hashing, candidate generation, and the verdict prompt. The 83.8% conflict rate in image-bearing groups is the evidence that one enforcement would not be enough |
| **The conflicting backlog being larger than anyone budgeted for** | Decision 5 ranks it by affected students, so partial work is still the most valuable work; §8 item F makes the volume an explicit scheduling commitment rather than a surprise |
| Reviewers disagreeing | Inter-rater agreement measured on a doubled subsample; disagreement is a go/no-go condition, not a footnote |
| The model treating question text as instructions | Question and option text reaches the prompt as delimited data (constitution III), and the contract test includes a malformed-response rejection case |
| A verdict silently drifting from its schema | Ollama's `format` constraint **and** pydantic validation server-side; the prompt is versioned so a quality change is attributable to model or prompt |
| An overnight batch interrupted mid-run | `ResumeCursor` and `--resume`, reused from P1 unchanged, with `import_runs` recording elapsed time |
| Building the review console against a guessed P3 schema | Decision 6: the row is omitted, not faked. P3 adds it when P3 exists |
| Spending 1.5 days on a passage pipeline for zero rows | Decision 3: the table is built, the phase is not. The acceptance criterion asserts zero rows rather than pretending coverage |
| P2 drifting into P4 or P5 | §4.2, plus the guardrail: deciding what a question is *about* is P5's work |

---

# 13. Acceptance Criteria

```text
[ ] The three text layers exist for every question; raw_text is never modified.
[ ] Normalization preserves meaning: ة is never rewritten to ه, proven by a failing-if-removed test.
[ ] Literal and formatting duplicates are detected deterministically, with no LLM involved.
[ ] Two questions with identical text and different attached images are never auto-clustered,
    and the rule is enforced at hashing and at candidate generation, not only at the verdict.
[ ] Every active question has both embeddings, with the correct prefix and one config version.
[ ] Embedding calls number ~2 x 11,416, not ~2 x 28,747 — the hash layer saved the paid work.
[ ] Every truncation is flagged and logged; none is silently accepted.
[ ] Nearest-neighbour search over pgvector returns the expected neighbours on a fixture.
[ ] The 400-pair set is labelled, stratified across every band, with agreement measured.
[ ] Calibration records precision, recall and both thresholds; a failed gate is recorded,
    never bypassed.
[ ] The LLM saw only uncertain-band pairs — the counter reads exactly zero.
[ ] The verdict endpoint returns schema-validated structured output; the prompt is versioned v1.
[ ] A human can override any AI verdict, and the decision is stored with its author and time,
    beside the verdict rather than on top of it.
[ ] No source question is deleted: the row count is identical before and after a full run.
[ ] Every conflicting duplicate carries an affected-student count derived from source_item_stats.n
    by SQL, and the backlog is ordered by it.
[ ] docs/reports/p2-*.md are all generated and regenerate identically; none is hand-edited.
[ ] The pipeline is idempotent: a second run produces zero new clusters and zero new candidates.
[ ] lab:health passes 11/11, exit 0.
[ ] Not a single row was written to injazedu by the Lab.
[ ] No new runbook, ADR, or handover document exists — except an embedder-switch ADR, and only
    if Phase 7 forced that decision.
```

---

# 14. Handover to P3 and P4

## 14.1 What P3 Inherits — Nothing, and That Is the Point

P3 depends only on P1 and can start today, in parallel with this project. What it gains **after**
P2 is the intersection §18 calls the strongest signal in the program:

```text
r_pbis < 0                                   (P3 — the top performers get it wrong)
        ∩
a member of a conflicting_duplicate cluster  (P2 — two identical questions, two different keys)
        ⇓
a wrong answer key, named independently by behaviour and by text
```

P2 makes that join possible by storing the cluster; P3 makes it decisive by computing the
coefficient. Neither blocks the other, and the console's statistics row (Decision 6) appears when P3
ships.

## 14.2 What P4 Inherits Ready

```text
search_text                  the comparable representation of every question
duplicate_clusters           layer one of §19's three-layer audit, already grouped
answer-key conflicts         found, ranked by student impact, and partly arbitrated
The real distinct-item count 11,416, not 28,747 — the honest denominator for any bank-wide rate
Calibrated thresholds        T_low / T_high, with the evidence behind them in duplicate_eval_runs
A working /verdict endpoint  structured output, versioned prompts, and a rationing pattern to copy
```

## 14.3 Numbers That Are Not Re-derived

`duplicate_eval_runs` is the reference for every similarity threshold in the program. A later project
that needs `T_high` **reads it from there** rather than re-calibrating, or the program ends up with
several versions of the truth. The same rule P1 set for `source_snapshots.profiling_results`.

The distinct-text count (11,416) and the redundancy rate (60.3%) belong in `CLAUDE.md` alongside the
bank size, because every future estimate that starts from 28,747 will be roughly twice too large.

---

# 15. Open Items

| # | Item | Impact |
|---|------|--------|
| 1 | **The governing plan still ends at §20.** §21 (metrics), §34 (ordering), P6–P9 and Phase D are referenced but unwritten. §17 defers to "the target in §21" for its calibration target | **Does not block P2** — §17's own gate (precision ≥ 0.90 at recall ≥ 0.70) is used directly. Carried forward from P1 §15 item 1, where it was flagged as needing resolution **before P4** |
| 2 | **The conflicting-duplicate volume exceeds the program's whole human-review budget** — ~1,125 groups against §13.3's 30–60 hours program-wide | Decision 5 makes it workable by ranking, but §13.3's budget table is now known to be wrong for this project. It should be corrected with an `**Updated**` note once Phase 10 produces a real per-item time |
| 3 | `sections.description` is empty in all 3,316 rows | §8's passage track is inert (Decision 3). If a future snapshot carries stimulus text, the excerpt rule and a stimulus embedding are a small, well-scoped addition — not a rewrite |
| 4 | The 60.3% redundancy rate is itself a finding no project owns | It is the strongest existing evidence for the bank's quality problem, and P4 (§19) is where it should be interpreted. P2 measures it and does not draw the conclusion |
| 5 | `NoPiiInLabSchemaTest` may need a narrow exemption for `reviewer_id` / `labelled_by` | Both reference the Lab's own `users` table, not Production identities. If the test's current rules reject them, the exemption is written explicitly and reviewed — never worked around silently |

**Closed by this plan:** P1 §15 item 2 (`T_high` / `T_low` uncalibrated — calibrated in Phase 7) and
P1 §15 item 3 (whether the bank is sound enough for P2's criterion — the broken-question rate was
0.108%, far under the 2% threshold, so P2 proceeds as planned).

---

**End of the P2 plan.**
Next project: **P3 — Item Statistics** (§18), which needs only two tables from P1 and may already
have started, and then **P4 — Question Quality Audit** (§19), which is the first project to consume
P2's clusters.
Neither P4 nor any later project begins before the acceptance criteria in §13 above are satisfied.
