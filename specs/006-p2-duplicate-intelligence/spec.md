# Feature Specification: P2 — Arabic Normalization & Duplicate Intelligence

**Branch**: `p2/duplicate-intelligence` · **Created**: 2026-08-27 · **Status**: Draft
**Implements**: `docs/plans/project/2/p2-duplicate-intelligence.md` (v1.0, 2026-08-27) — all eleven
phases, as **one** spec (§7.1). Governed by §17 of
`docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (v2.0), with §8, §12.2,
§12.4, §13.2, §13.3 and §13.4 as supporting contracts.
**Predecessor**: `specs/005-p1-profiling-and-question-mirror` — the fifteen mirror tables, the
derivation core, the ETL primitives, `lab:health` 10/10.
**Contracts still in force**: `specs/004-handover-and-p1-readiness/contracts/source-access-and-stack.md`
(read-only source) and the embedding contract `eg300m-qat-q4_0/sim-v1/768/l2norm`.

> **P2 reads the mirror and writes beside it.** It performs no MySQL access at all, so neither
> allowlist in `config/lab.php` gains a table. Where the plan and the loaded mirror disagree, **the
> mirror wins** — four measurements already corrected the governing plan's estimates, and that is
> what P1 was built to make possible.

---

## Scope

One sentence: **find the bank's real duplication, prove the cheap layers did most of the work, spend
the model only where nothing cheaper could decide — and hand a ranked backlog of answer-key conflicts
to a human, without deleting one question or writing one row to Production.**

| Piece | Outcome |
|---|---|
| An Arabic normalizer | `raw_text → clean_text → search_text`, meaning preserved, `ة → ه` forbidden in every hash by a test that fails the moment anyone adds it. Option labels stripped in **both scripts** — the bank is ~10% non-Arabic. A named **recall-only** fuzzy form tolerates the `ة`/`ه` typo without ever asserting identity. |
| Layers 0 and 1 | SHA-256 over `search_text` and over `search_text` ⊹ ordered options. The bank's **60.3% redundancy** resolved with no model call and no similarity threshold. |
| The media boundary | Two questions with identical text and different attached images are **never** auto-clustered — enforced at hashing, at candidate generation, and again in the verdict prompt. |
| Eight Lab-owned tables | Derived text, vectors, candidate pairs, clusters, members, reviews, labelled eval pairs, eval runs. Every foreign key to the mirror goes through `source_id`. |
| Embeddings, priced honestly | `stem_embedding` + `full_embedding`, each deduplicated by its own hash — **~24,063 calls against 57,494**, a 58% saving (amended 2026-08-28, notes.md N1). |
| Layers 2 and 3 | A `pg_trgm` GIN index earned by a query that needs it, and pgvector top-K=20 by exact scan. No HNSW. |
| A measurable gate | A human-labelled set drawn in **waves of 100 up to a 400 ceiling**, each wave independently stratified, expanded only when a confidence interval says the sample cannot decide. Calibration records **precision ≥ 0.90 at recall ≥ 0.70** with its interval, or records that it failed. There is no silent pass. |
| A rationed verdict | `POST /verdict` on the running ai-service: schema-constrained, pydantic-validated, prompt-versioned `v1`. A counter proves the model saw nothing outside `band = 'uncertain'`. |
| A ranked backlog | 928 answer-key conflicts in four deterministic priority tiers, ordered by `affected_student_count` computed by SQL over `source_item_stats.n` — never by the model. A **standing queue** under a soft cap of 10/day, blocking nothing: the top 100 carry 50.4% of all measured student exposure. |
| An Arabic review console | Both questions side by side with their images, five actions, every decision attributed and timestamped **beside** the AI verdict, never on top of it. |
| `php artisan lab:dedup` | Eleven steps, idempotent, resumable, provably rationed. A second run changes nothing. |

**P2 is a detector and a queue, not an editor.** It finds duplication, ranks it, explains it, and
hands it to a person. Every action it recommends is carried out by a human, outside the Lab.

### Out of scope — writing any of this here is a defect

```text
Deleting any question, under any relation_type, ever  ← forbidden program-wide; question_result
                                                        references questions and history stays intact
Writing anything to Production MySQL                  ← the conflict report is an artefact a human acts on
Editing raw_text, or any mirror column, in place      ← P2 adds tables; it never mutates the mirror
Adding a column to any P1 mirror table                ← Decision 1; the mirror's contract is faithfulness
Any MySQL read at all                                 ← P2 reads the Lab mirror only; no allowlist changes
Discrimination (r_pbis), p-value, distractor analysis ← P3; P2 reads only source_item_stats.n
Modelling P3's item_statistics schema before it exists← Decision 6: the console row is omitted, not faked
Classification, taxonomy, subject tagging, coverage   ← P5
Question generation, rewriting, authoring help        ← P9
A passage-excerpt embedding pipeline                  ← Decision 3: zero stimulus rows in this snapshot
An HNSW index                                         ← constitution VII: indexes are earned; ~70 MB exact-scans fine
A new queue framework, or Horizon                     ← ADR-011: the database driver, already proven
Switching the embedding model without approval        ← Principle I names the embedding contract
Inventing the governing plan's missing §21            ← §17's own gate numbers are used directly
Refreshing the snapshot, or gating on its age         ← cancelled program-wide
A backup, dump schedule, or restore drill             ← cancelled program-wide
Any gate or criterion on a memory number              ← cancelled (constitution VII)
A new runbook, ADR, acceptance record, or handover
  document — except one conditional embedder ADR      ← constitution, documentation policy
```

**The guardrail, in this project's terms:** if you find yourself writing code that decides what a
question is *about*, you are in P5. If you find yourself writing a statement that removes a question,
you are nowhere in this program.

---

## What This Feature Inherits

| Needed here | State on arrival |
|---|---|
| `source_questions.raw_text` — original, unmodified | Delivered (P1) — the input to the three text layers |
| `source_question_options` with stable `option_index` and `is_correct_derived` | Delivered (P1) — **reused, never re-derived** |
| `source_media` — 5,582 images at question grain, 4 audio at section grain | Delivered (P1) — the basis of the media boundary rule |
| `source_item_stats` / `source_option_stats` (`n`, `n_correct`, `p_value`, `m1`, `m0`, `sd`) | Delivered (P1, ADR-022) — `n` is the backlog's ranking key |
| `answer_key_state` | Delivered (P1) — which questions are comparable at all |
| `source_sections.stimulus_raw` | Delivered (P1) — and **empty in all 3,316 rows** on this snapshot |
| `ImportRunRecorder` · `ResumeCursor` · `ImportErrorRecorder` · `BatchUpsert` | Delivered (P1) — reused unchanged; `import_runs.kind` is a plain `string(20)` |
| `OptionIndexDeriver` | Delivered (P1) — the `options.order` tie problem is already solved |
| PostgreSQL 17 · pgvector 0.8.6 · pg_trgm 1.6 | Delivered (P0) — installed, with **no index on any mirror table yet** |
| `apps/ai-service` `POST /embed`, health-checked | Delivered (P0/P1) — the service owns the mandatory prefix |
| `config('lab.embedding.*')` — config version, prefix template, dimension, models | Delivered (P0) |
| `database` queue driver (ADR-011) | Delivered (P0) — ready for P2's batches |
| `chat_model` = `gemma4:e2b-it-qat`, declared in `config.py` and so far unused | Delivered (P0) — Phase 8's generator |
| `php artisan lab:health` — 10 checks, exit 0 | Delivered (P0/P1) — **the instrument every phase is measured by**; P2 adds the eleventh |
| `NoPiiInLabSchemaTest` · `ReadOnlyGuardTest` · allowlist tests | Delivered (P0–P1) — extended here, never weakened |

**The snapshot is fixed at 2026-08-07** and is never refreshed. Every number below travels with that
date as context; nothing in this feature blocks on it.

---

## The Four Measurements That Shaped This Spec

Taken from the loaded mirror on 2026-08-27, against the fixed snapshot. They are inputs, not
findings to re-derive.

| Measurement | Value | What it changes here |
|---|---|---|
| Active questions | **28,747** (29,142 total, 395 soft-deleted) | The denominator for every rate below |
| **Distinct raw texts** | **11,416** | Embedding and candidate budgets are computed from **this**, not 28,747 |
| Literal duplicate groups | **4,689**, holding 22,020 questions | Layer 0 alone resolves 17,331 redundant rows at zero cost |
| Redundancy rate | **60.3%** | §13.2's pair estimate halves; the LLM band becomes affordable |
| `has_html` / inline `<img>` | **0** / **0** | HTML stripping is a no-op today — implemented defensively, priced at nothing |
| `source_media` images | **5,582** at question grain | The live boundary rule (Decision 4) |
| Sections with stimulus | **0 of 3,316** (`max(stimulus_length) = 0`) | §8's passage track is inert on this snapshot (Decision 3) |
| Audio items | **4** at section grain (18 questions flagged) | Excluded from every text path, as P1 already flags them |
| Same-text groups with differing answer keys | **928** after excluding image-bearing groups (1,024 total) | `conflicting_duplicate` is a **backlog**, not a trickle (Decision 5) |

**Amended 2026-08-28 (notes.md N2).** The project plan's group tallies did not reconcile; measured
directly against the mirror they now do — 4,558 groups with no image member plus 131 with one equals
the 4,689 total. The conflict counts came down with them: **928** non-image conflicting groups rather
than ~1,125, so §8 item F's commitment is **31–77 trainer hours**, not 37–94. Every figure in this
table is measured over `raw_text` and is therefore a **floor**: `search_text` collapses formatting
variants, so Phase 3 finds more groups and more conflicts. No acceptance criterion pins these as
expected values.

**The image finding, stated precisely**, because one decision rests entirely on it:

```text
Duplicate groups with no image member :  4,558 groups, 928 conflicting  (20.4%)
Duplicate groups with an image member :    131 groups,  96 conflicting  (73.3%)
                                         ─────────────
                                           4,689 total — reconciled 2026-08-28
```

A group whose members carry different images conflicts at **3.6×** the base rate. The
overwhelmingly likely reading is not that these are broken questions — it is that **the image is part
of the question**, and two items sharing a stem while pointing at different diagrams are different
items with correctly different answers.

---

## The Six Settled Decisions

Recorded here because they are the answers to the questions a reader of §17 would otherwise ask.
They are settled and are not reopened during implementation.

1. **New sibling tables, never `ALTER` on the mirror.** Derived text, hashes and vectors live in
   `source_question_derived`, keyed one-to-one to the question — the precedent `source_item_stats`
   already set. This keeps the whole project inside Principle I's "decide with judgement" half.
2. **Every P2 foreign key references `source_id`, never the surrogate `id`.** P1 wired every relation
   through `source_id`; a table that joins to `source_questions.id` would return rows and be silently
   wrong. It is the single most likely defect in this schema, and Phase 1 catches it with a test.
3. **The stimulus track is built minimally and declared inert.** `source_section_derived` exists and
   its population step runs, because the Production column exists and may be filled later. It gets no
   embedding, no excerpt builder, and no share of the estimate. Its acceptance criterion asserts
   **zero rows**, not coverage. The *structural* rule survives as `same_section` on every candidate.
4. **The image boundary replaces the passage boundary as the blocking rule.** §17's mandatory
   "different passage ⇒ not a duplicate" has no passages to apply to. The rule is preserved in force
   and re-grounded on media, enforced **three times**: at hashing, at candidate generation, and in the
   verdict prompt.
5. **`conflicting_duplicate` is a prioritized standing backlog, not a queue to empty.** 928 groups ×
   2–5 min ≈ 31–77 trainer hours, against §13.3's 30–60 hours **program-wide**. The escalation ships
   ranked by `affected_student_count`, split into four deterministic priority tiers, and worked by
   rank — never presented as a finite task, with the full backlog size always visible so partial work
   never looks finished. **Amended 2026-08-28**: the operator is a solo developer with no trainer time
   to commit, so this is now stated as a requirement rather than an expectation — **FR-151: nothing
   blocks on the backlog's remaining size.** The measurement makes the design pay for itself: the top
   100 of the 928 groups carry **50.4% of all measured student exposure**, and the top 200 carry
   67.3%, so a cap of 10/day covers half the measured harm in ten working days (notes.md N10).
6. **One command, one labeling screen with three modes, no new frameworks.** `lab:dedup` follows
   `lab:import`'s shape; the labeling screen serves `calibration`, `spot_check` and
   `uncertain_review`. **The P3 statistics row is omitted, not faked** — `P3StatsLookup` reports
   unavailability and the row does not render.

---

## Clarifications

### Session 2026-08-27

- Q: When the high band or the LLM verdict produces pairs `(A,B)` and `(B,C)`, is that one cluster of three or two clusters of two — and may a question belong to more than one cluster? → A: Transitive closure **within each source layer** (union-find over that layer's qualifying pairs); a question belongs to at most one cluster per layer, so `affected_student_count` never double-counts; a component exceeding a size guard is flagged for human review instead of being merged silently, because A~B and B~C both clearing `T_high` does not make A~C a duplicate.
- Q: What happens when a verdict call fails — non-2xx, timeout, or a response that fails validation? → A: Record it on the candidate row (attempt count + last error) and in the errors table, and continue the batch; retry up to a bounded number of attempts across runs; past that mark the pair **terminally failed** so it is never re-dispatched, and surface it in the console as a countable review item. Leaving the verdict null would re-dispatch the pair on every run, breaking idempotency and silently re-spending the rationed budget.
- Q: How do the five review actions map onto the seven cluster statuses, and may a human change a cluster's `relation_type`? → A: A decision updates **both**: `same`→`confirmed`; `valid_variant`→`confirmed` + `same_objective_variant`; `not_duplicate`→`rejected`; `conflict`→`urgent_review` + `conflicting_duplicate`; `skip`→`skipped`; a second decision on an `urgent_review` cluster sets `resolved`. The cluster carries the Lab's current best answer, while the AI verdict stays untouched on the candidate row — so comparing the two is what makes model accuracy answerable.
- Q: The plan says human labels use "the same seven values as the verdict schema" but lists a different seven for `duplicate_clusters.relation_type` — one vocabulary or two? → A: **Two deliberately distinct enums.** The verdict and the human label share §17's seven exactly, so precision and recall stay computable. `relation_type` is its own seven: it adds `probable_duplicate`, writable **only** by the high-band auto path, and drops `not_related`, because no cluster is ever created for unrelated questions. `probable_duplicate` means "the threshold said so and nobody looked" — a statement the model must not be able to emit.
- Q: Constitution VI requires review queues to carry a daily cap, which the spec omitted — hard, soft, or convention only? → A: A configurable **soft** cap on the ongoing queues (uncertain review and the conflict backlog): at the cap the console says so and shows the remaining backlog, but the reviewer may continue deliberately. The 400-pair calibration set is **exempt**, because it is bounded and blocks Phase 7 — capping it would delay the whole project. This meets the constitution's intent (a schedulable, visible human budget) without a single-operator local tool refusing to let its own operator work.

### Session 2026-08-28 — three operator decisions

Taken by the operator, not asked by the spec. Each was checked against the constitution, the FRs and
the acceptance criteria before it was written in; the one that touched a constitutional invariant was
put back to the operator rather than absorbed. Measurements supporting all three are in notes.md N10.

- Q: Should the strict normalizer fold `ة` and `ه` together? → A: **No, and that is unchanged** — but `ة`/`ه` variation is treated as a possible Arabic typo in the **fuzzy candidate-matching layer only** (FR-141), and a match caused only by that fold can never become `exact_duplicate` automatically (FR-142). This required narrowing constitution IV, whose text-layering bullet forbade the transform without qualification; the operator approved the narrowing on 2026-08-28 and the constitution is now **v2.5.0**. The amendment scopes the prohibition to the strict layers and to every hash, cluster key and identity decision, and admits a named recall-only form — it narrows the rule to the property it was protecting rather than eroding it.
- Q: Option-label stripping was specified as "where present" with no list. Which labels? → A: **Both scripts**, because the bank carries English/STEP/IELTS courses beside the Arabic ones — measured, 3,604 options begin with a Latin label against 1,200 Arabic (FR-139). Stripping is anchored to the leading marker only and never touches a letter inside the text. Case folding was also missing from FR-011 while the embedding budget was measured with it, so it is now explicit (FR-140).
- Q: Must the 400 calibration labels be produced before calibration can run at all? → A: **No — progressive waves** of 100, expanding to 200, 300 and 400 only when the current sample cannot support a reliable decision (FR-144). Human labels remain the **sole** ground truth; AI may pre-label as a separate, non-authoritative suggestion the screen hides until the human has committed (FR-147, FR-148). The stopping rule is a Wilson confidence interval rather than a judgement call, which **strengthens** the gate: it now passes only when the interval clears the threshold, where FR-060 passed on a point estimate.
- Q: Must the ~928-item conflict backlog be cleared before the project can continue? → A: **No.** It is a **standing queue** (FR-151). The operator is a solo developer and cannot commit 31–77 trainer hours; the previous blocking human gate F protected no engineering property and is exactly what the constitution's gate policy calls a procedural gate. The backlog stays ranked by measured impact, gains deterministic priority tiers (FR-150) and a small soft `daily_review_cap` of 10 (FR-152). AI may rank, triage and recommend with confidence, but the measured impact stays deterministic SQL and no model may resolve a conflict (FR-153, FR-154). **Measured justification**: the top 100 of 928 groups carry 50.4% of all affected-student exposure, so ten working days at the cap covers half the measured harm.

### Session 2026-08-29 — gate H (T003), against a 35-row sample of the real mirror

Human gate H (spec Human Gates, row H) closes here. Before this session `config('lab.dedup')` carried
the fold map and both label alphabets (T001b), but three points inside FR-011's ordered transform
list were still open: the execution order between option-label stripping and Alef-form
normalization, the exact scope of "selected Alef-form normalization," and how literally "punctuation
normalization" should be taken. A throwaway preview of `search()`/`fuzzy()` was run against 35 real
rows pulled read-only from `injazedu_lab` — question stems and options carrying genuine `ة`/`ه`
spelling variance, Arabic- and Latin-labelled options, and adversarial negative cases — and the
operator confirmed or corrected each open point directly against that sample.

- Q: Does the fold-rule and label-alphabet confirmation asked for in FR-139/FR-141 hold as specified? → A: **Yes, unchanged.** `ة → ه` stays prohibited in `clean_text`, `search_text`, both strict hashes, every cluster key and every identity/`exact_duplicate` decision (FR-012). The recall-only fuzzy fold may tolerate `ة`/`ه` only, may never by itself produce `exact_duplicate` (FR-142), and `fuzzy_fold_map` gets **no further entries** — an additional fold is out of scope unless it is justified by a measured yield on real data and lands with its own isolation test (FR-141, FR-143). Both option-label alphabets (Arabic and Latin, including lowercase Latin) are confirmed as specified, stripped only when they are a leading option marker (FR-139).
- Q: FR-011 lists "selected Alef-form normalization" and "option-label stripping" as two items in one ordered transform list — which runs first, and does the Alef-form step touch every Hamza-Alef letter? → A: **Option-label stripping runs first**, against the four Hamza-Alef label forms (`أ إ آ ا`) exactly as they appear in `clean_text`, before anything folds them. Only *after* a leading label (if any) is removed does Alef-form normalization run over what remains, and it folds **`أ`/`إ`/`آ` → `ا` only** — `ى` (alef maksura) is never folded, in either the strict or the fuzzy layer. Reversing the order would let a label's own hamza already be folded to bare `ا` before the stripper's Arabic alphabet — which lists all four forms precisely because they are still distinct at that point — ever sees it.
- Q: FR-011's "punctuation normalization" was previewed as a blanket strip-and-collapse of all punctuation to a space — is that the rule? → A: **No — narrower.** Strip-and-collapse applies only to punctuation that carries no meaning inside the normalized search layer (a trailing sentence period, decorative quote marks, a dash or colon used purely as list-item formatting). Punctuation and symbols that are load-bearing for technical or linguistic meaning **MUST be preserved**: a decimal point inside a number (`3.14`), a percent or degree sign, a unit slash (`km/h`), mathematical operators and signed numbers (`-5`, `±`), and an apostrophe inside a contraction (`don't`) all stay in `search_text` rather than collapsing to a space or disappearing. This is now FR-155, tested by FR-155's own test (T025) alongside the existing tatweel/diacritic/digit/Alef/case-folding/idempotence suite — English and scientific-notation cases are required, not just Arabic ones, because the ambiguity is sharpest in STEP/IELTS and science-course content.

---

## User Scenarios & Testing

### US1 — The bank's real duplication is found without a model (Priority: P1)

The operator runs the normalization and hash steps. Every question gains three text layers and two
hashes; identical and formatting-only duplicates collapse into clusters deterministically, with the
canonical member chosen by rule rather than by chance. Roughly 4,689 groups holding 22,020 questions
resolve before a single embedding is computed.

**Why this priority**: it is the governing principle of the whole project stated as an executable
step — *cheap layers run first, and skipping one to "just ask the model" is a defect*. It is also the
only slice that survives every go/no-go failure: if no embedder clears the gate, Layers 0–2 still ship
and still resolve 60.3% of the bank. Everything downstream is priced against its output.

**Independent test**: run `lab:dedup --step=derive-text` then `--step=hash-cluster` on a clean Lab
database and find 29,142 derived rows, a stable cluster count, and a second run that changes nothing.

**Acceptance Scenarios**:

1. **Given** a question whose text contains a tatweel, diacritics, Arabic-Indic digits and irregular
   whitespace, **When** the normalizer runs, **Then** `search_text` has them normalized, `clean_text`
   preserves meaning, and `raw_text` is byte-identical to the mirror's value.
2. **Given** any `search_text`, **When** the normalizer is applied to its own output, **Then** the
   result is identical — normalization is idempotent.
3. **Given** a normalizer that rewrites `ة` to `ه`, **When** the unit suite runs, **Then** it
   **fails**, naming the rule.
4. **Given** two questions with the same stem and the same options presented in a different input
   order, **When** their hashes are computed, **Then** `question_with_options_hash` is **identical**,
   because `option_index` orders them; **When** one option's text differs, **Then** the hash differs.
5. **Given** the full derive step, **When** it completes, **Then** every one of the 29,142 questions —
   soft-deleted rows included — has exactly one `source_question_derived` row with a non-null
   `search_text` and a recorded `normalizer_version`.
6. **Given** a group of questions sharing `question_with_options_hash`, **When** clustering runs,
   **Then** one cluster is created with `relation_type = 'exact_duplicate'`,
   `source_layer = 'hash'`, and the **lowest** `question_source_id` as canonical member.
7. **Given** two questions whose `question_text_hash` matches but whose
   `question_with_options_hash` differs, **When** clustering runs, **Then** they are **not**
   auto-clustered; a candidate is recorded with `hash_match_level = 'formatting'`.
8. **Given** a completed hash-cluster step, **When** it is run again, **Then** the cluster count,
   the member count and the canonical member of every cluster are identical.
9. **Given** the section derive step on this snapshot, **When** it runs, **Then** it processes and
   writes **zero** rows and **logs that count** rather than assuming it.

---

### US2 — Identical text with a different image is never called a duplicate (Priority: P2)

Two questions can share every character of their stem and point at two different diagrams. The system
records them as a candidate a human can see — with both images side by side — and permanently refuses
to auto-cluster them or escalate them as a conflict.

**Why this priority**: it is the one false-positive trap the data proves is real. Image-bearing groups
conflict at 83.8% against a 24.4% base rate, and an auto-cluster there would tell a trainer that a
correct answer key is wrong. §17 demands its passage equivalent be enforced in the blocking *and* in
the verdict; this is that rule, applied to the medium the bank actually uses.

**Independent test**: seed two questions with identical text and different `source_media` rows, run
the hash, candidate and auto-cluster steps, and assert the pair appears in `duplicate_candidates` with
`media_relation = 'different_media'` and in no cluster produced by any automatic path.

**Acceptance Scenarios**:

1. **Given** a question with attached images, **When** its derived row is written, **Then**
   `media_fingerprint` is a hash over the **ordered** attached image paths, computed in the same pass
   as the text hashes.
2. **Given** a question with no attached media, **When** its derived row is written, **Then**
   `media_fingerprint` is null and its `media_relation` in any pair is `no_media`.
3. **Given** a hash group whose members carry differing `media_fingerprint` values, **When**
   clustering runs, **Then** the group is **split by fingerprint before clustering** and the
   cross-fingerprint pairs become candidates, not members.
4. **Given** candidate generation, **When** a pair is inserted, **Then** `media_relation` is computed
   **at insertion time** from both fingerprints — the rule enforced a second time, independently.
5. **Given** a `different_media` pair, **When** the high-band auto-cluster step runs, **Then** the
   pair is excluded whatever its similarity score.
6. **Given** a `different_media` pair, **When** any path would escalate it, **Then** it is **never**
   marked `conflicting_duplicate` by an automatic step.
7. **Given** the verdict prompt, **When** it is rendered, **Then** it states the rule explicitly, and
   a synthetic identical-text/different-image pair must **not** return `exact_duplicate`.
8. **Given** the review console, **When** a `different_media` pair is opened, **Then** both attached
   images are shown side by side so the human sees what the rule saw.

---

### US3 — Semantic candidates, inside a budget computed from the measurement (Priority: P3)

Every distinct surviving text is embedded once and the vector is shared across its hash group. A
trigram index and a top-K=20 vector scan produce the candidate pairs every later phase consumes, each
carrying its scores, its section relation and its media relation.

**Why this priority**: it is the paid layer, and the whole argument of the cascade is that it must be
paid for once, over 11,416 items rather than 28,747. A pipeline that embeds every row would spend
17,331 extra model calls to rediscover what `GROUP BY` already found.

**Independent test**: run `--step=embed` and compare the recorded `/embed` call count against
2 × 11,416; run `--step=candidates` and confirm `EXPLAIN` shows the GIN index in use.

**Acceptance Scenarios**:

1. **Given** the embed step, **When** it selects work, **Then** it embeds one representative per
   `question_text_hash` for the stem and one per `question_with_options_hash` for the full text,
   among active questions, writing each vector to every member of **its own** group.
2. **Given** a completed embed step, **When** the active questions are counted, **Then** **zero**
   have a null `stem_embedding`, and every populated row carries the **same**
   `embedding_config_version`.
3. **Given** the embedding client, **When** it calls the service, **Then** it sends **raw text** — the
   mandatory prefix is applied once, by the service, and never on both sides.
4. **Given** a response whose `prompt_eval_count` reaches the context length, **When** it is stored,
   **Then** the row's truncation flag is set **and** an error row with code `EMBEDDING_TRUNCATED` is
   recorded — no truncation is silently accepted.
5. **Given** a non-2xx response or a zero-norm vector, **When** it is received, **Then**
   `EMBEDDING_FAILED` is recorded and the batch **continues**.
6. **Given** the trigram index migration, **When** it runs, **Then** the index exists on
   `search_text`, and it is created in the candidate phase that needs it — not in the schema phase.
7. **Given** candidate generation, **When** a pair is written, **Then** it is canonicalised so that
   `question_a_source_id < question_b_source_id`, and a UNIQUE constraint makes a re-run an update
   rather than a duplicate.
8. **Given** the vector search, **When** it runs, **Then** it uses cosine distance over
   `stem_embedding` at top-K=20 by **exact scan**, with no HNSW index created.
9. **Given** a fixture with known neighbours, **When** nearest-neighbour search runs, **Then** the
   expected neighbours are returned in the expected order.
10. **Given** every candidate row, **When** it is written, **Then** `same_section`, `media_relation`
    and `embedding_config_version_at_generation` are populated and `band` is still null.

---

### US4 — Nothing runs over the whole bank until the gate has been measured (Priority: P4)

A stratified sample is drawn in **waves** from real, uncalibrated similarity scores and labelled by a
human against the same seven-value taxonomy the model uses. Thresholds are swept deterministically
against those labels after each wave, and the result — pass, fail, or "expand, the sample cannot
decide" — is recorded as a row, never as a judgement.

**Why this priority**: it is the only thing standing between a plausible-looking similarity number and
tens of thousands of wrong recommendations. §17 makes it a hard gate: **precision ≥ 0.90 at recall
≥ 0.70** on the (exact + semantic) class before Phases 8 and 9 run over the bank. Sampling from real
scores is also what resolves the chicken-and-egg — no threshold is needed to stratify.

**Why waves** (2026-08-28): the operator labels this set personally. Committing 5–10 hours before
knowing whether 1.5 would have settled it is the wrong shape for a single-developer project, and the
confidence-interval stopping rule (FR-144) makes the early stop *safer* than the old fixed 400, not
looser — the gate now has to clear an interval rather than a point estimate.

**Independent test**: run `--step=eval-sample --wave=1`, confirm every similarity decile and every
quota is non-empty **within that wave**, label it through the screen, then run `--step=calibrate` and
find one `duplicate_eval_runs` row carrying both thresholds, both confidence intervals, an explicit
`gate_passed` and an explicit `expansion_decision`.

**Acceptance Scenarios**:

1. **Given** the candidate table, **When** the sampler runs for wave 1, **Then** exactly the
   configured wave size (100) of rows with `purpose = 'calibration'` and `sample_wave = 1` are
   written, stratified across similarity deciles from 0.95–1.00 down to 0.50–0.60, with **every
   decile non-empty within the wave**.
2. **Given** the same sampler run, **When** the quotas are checked, **Then** exact-hash pairs,
   `formatting` pairs, `orthographic` pairs, `different_media` pairs, answer-key-conflict pairs,
   same-section and cross-section pairs, and a **random-pair negative control drawn from outside the
   candidate set** are each represented **within that wave**.
2b. **Given** a completed wave whose gate decision is ambiguous under FR-144, **When** the next wave
   is drawn, **Then** it independently satisfies every decile and quota again, and the calibration is
   recomputed on the **cumulative** set.
2c. **Given** an AI pre-label is enabled, **When** a pair is opened, **Then** the suggestion is not
   retrievable until the human label is recorded; it is stored only in `ai_*` columns; and it never
   enters `human_relation` or the positive class.
3. **Given** the labeling screen in `calibration` mode, **When** a pair is opened, **Then** both
   questions appear side by side with their options, their derived correct answers and their attached
   images, and the labeller may record a relation from the full **seven-value** taxonomy plus
   `same_learning_objective`, `same_correct_answer` and a confidence.
4. **Given** a labelled set, **When** it is inspected, **Then** every row has a non-null
   `human_relation`, an attributed labeller and a timestamp.
5. **Given** a doubled subsample labelled by two people, **When** agreement is computed, **Then**
   inter-rater agreement is **measured and recorded**, not assumed.
6. **Given** poor inter-rater agreement, **When** calibration is attempted, **Then** it is **not**
   run against labels the labellers disagree about — reconciliation and re-labelling come first.
7. **Given** the cumulative labelled set, **When** the calibrator runs, **Then** it sweeps thresholds
   deterministically with `exact_duplicate ∪ semantic_duplicate` as the positive class and writes one
   `duplicate_eval_runs` row per wave holding precision, recall, both 95% confidence intervals, the
   positive-class count, `threshold_low`, `threshold_high`, `gate_passed` and `expansion_decision`.
7b. **Given** a wave whose Wilson lower bound does not clear the gate, or fewer than 30 positives, or
   an unfilled stratum, **When** the expansion rule is evaluated, **Then** it records `expand` and no
   threshold is adopted; **Given** all four conditions hold, **Then** it records `stop_pass`.
7c. **Given** a wave whose Wilson **upper** bound of precision is below 0.90, **When** the rule is
   evaluated, **Then** it records `stop_fail` and the embedder fork is taken **without** labelling the
   remaining waves.
8. **Given** the calibrated thresholds, **When** they are projected across the **full** candidate
   pool, **Then** `projected_uncertain_band_count` is recorded; **When** it exceeds **8,000**,
   `T_low` is raised and the recompute is written as **another row**, never as a silent adjustment.
9. **Given** a failed gate, **When** the phase ends, **Then** `gate_passed = false` is recorded and
   there is **no path** that proceeds past it silently.
10. **Given** a failed gate and human approval, **When** the benchmark runs, **Then** it re-embeds
    **only the eval set's questions** for each alternative plus the 512-dimension truncation, writes
    one row per model, and **Recall@20 decides**.
11. **Given** an alternative that clears the gate where the incumbent does not, **When** the decision
    is reached, **Then** the work **stops and asks** — adopting it requires an explicit human
    decision and an ADR, after which the embed and candidate phases re-run in full.
12. **Given** a re-run of calibration, **When** it completes, **Then** it produces a **new** row for
    comparison; no prior run is overwritten, and exactly one row carries `is_selected = true`.

---

### US5 — The model is spent only where nothing cheaper could decide (Priority: P5)

Candidates are banded. Below `T_low` they are dropped. At or above `T_high` they are auto-clustered
with a 5% human spot-check and no model call. Only the band between reaches the LLM, which returns a
schema-validated structured verdict — and a counter proves it saw nothing else.

**Why this priority**: it is what makes the project finite. §13.2's honest arithmetic says running
every candidate through a model is ~33 continuous hours; the band brings it to an overnight batch.
The counter is not a metric — it is the proof that the rationing held.

**Independent test**: run `--step=verdict` and assert that the count of candidates with a non-null
verdict and a band other than `uncertain` is exactly **zero**; run `--step=auto-cluster` and confirm
the spot-check sample is 5% of the clusters that step created.

**Acceptance Scenarios**:

1. **Given** calibrated thresholds, **When** banding runs, **Then** every candidate carries a `band`
   of `exact`, `high`, `uncertain` or `low`.
2. **Given** any candidate outside `band = 'uncertain'`, **When** a verdict dispatch is attempted,
   **Then** the budget guard **throws** before the call is made.
3. **Given** a completed verdict step, **When** the proof query runs, **Then** the number of
   candidates with a verdict and `band <> 'uncertain'` is **0**.
4. **Given** the verdict endpoint, **When** it returns, **Then** the response carries §17's seven
   fields verbatim, is constrained by a JSON Schema at generation **and** validated server-side, and
   a malformed response is **rejected rather than parsed**.
5. **Given** question and option text, **When** it reaches the prompt, **Then** it is delimited as
   **data**; text that reads like a directive is treated as a question, not a command.
6. **Given** a prompt change, **When** it is made, **Then** it creates `v2` and never overwrites
   `v1`, and every stored verdict records the prompt version that produced it.
7. **Given** the verdict step, **When** it completes, **Then** the run records the pair count and
   elapsed time against the ≤5,000-pair / ~6-hour target.
8. **Given** an interrupted verdict batch, **When** it is resumed, **Then** no pair is re-judged and
   none is skipped.
9. **Given** `band = 'high'` candidates whose `media_relation` is not `different_media`, **When**
   auto-clustering runs, **Then** clusters are created with `relation_type = 'probable_duplicate'`,
   `status = 'auto'` and `source_layer = 'high_band_auto'`, with **no model call**.
10. **Given** the clusters that step created, **When** the spot-check sample is drawn, **Then** it is
    5% (±1) of them, written with `purpose = 'spot_check'`, and worked through the same screen in
    confirm/reject mode.
11. **Given** a rejected spot-check, **When** it is recorded, **Then** the cluster's status becomes
    `rejected` and a review row is written — the cluster is **not deleted**, because a rejected
    cluster is the only evidence the auto path made a mistake.
12. **Given** high-band pairs `(A,B)` and `(B,C)`, **When** auto-clustering runs, **Then** one
    cluster `{A, B, C}` is created rather than two overlapping pairs; **When** the pairs are
    processed in the reverse order, **Then** the cluster, its members and its canonical member are
    **identical**.
13. **Given** a chain whose closure exceeds the configured size guard, **When** clustering runs,
    **Then** no single cluster is written for it — the component is recorded and flagged for human
    review with its size and the pairs that chained it.

---

### US6 — A wrong answer key reaching 4,000 students is worked before one reaching two (Priority: P6)

Every detected answer-key conflict becomes a cluster in urgent review, carrying a count of the
students who actually answered its questions, summed by SQL from the mirror's own statistics. The
trainer works the list by that rank, and a regenerable report carries the top of it to the people who
can fix it in Production.

**Why this priority**: §17 calls this the most important immediate deliverable in the whole program,
and the mirror says it is 928 items — far past any budget that assumed a trickle. Ranking is the
entire design: it is what makes the first hour of trainer time the most valuable hour, and it is only
possible because P1 built `source_item_stats.n` for exactly this. Measured 2026-08-28, the ranking
earns its keep decisively — the **top 100 of 928 groups carry 50.4% of the backlog's 269,153 total
affected-student exposure**, and the top 25 alone carry 21.1%. That is why the backlog can be a
standing queue worked at 10/day without anything downstream waiting on it (FR-151).

**Independent test**: seed a synthetic conflicting cluster with known `n` values and assert the
report's affected-student count equals the raw SQL sum exactly.

**Acceptance Scenarios**:

1. **Given** a `conflicting_duplicate` verdict from the model **or** from a human, **When** it is
   recorded, **Then** a cluster is created with `status = 'urgent_review'`.
2. **Given** such a cluster, **When** its rank is computed, **Then** `affected_student_count` is a
   deterministic SQL sum of `source_item_stats.n` at the active scope across its members — and the
   model is **never** asked for that number.
2b. **Given** the conflicting population, **When** tiers are assigned, **Then** `priority_tier` comes
   from deterministic SQL over the measured `affected_student_count` distribution at the configured
   percentiles, the computed cut values are logged for the run, and no model has touched it.
3. **Given** the backlog list, **When** it is displayed, **Then** it is ordered by `urgent_review`
   first, then by `priority_tier`, then by `affected_student_count` descending — **never by `id`**.
4. **Given** a worked backlog, **When** the console is viewed, **Then** the **full** remaining count
   is displayed **per tier**, so a partially worked list never looks finished.
5. **Given** the daily cap is reached, **When** the reviewer continues, **Then** the console states
   the cap is reached and shows what remains, and still serves the next item — it never blocks.
5b. **Given** an entirely unworked backlog, **When** the full acceptance run executes, **Then** it
   passes — no phase, gate, report or criterion blocks on the backlog's remaining size (FR-151).
5c. **Given** AI triage output on a conflict, **When** the cluster is inspected, **Then** the
   recommendation is labelled with its confidence and prompt version, `affected_student_count`,
   `priority_tier` and `status` are unchanged by it, and the cluster is still in `urgent_review`
   until a **human** review row moves it.
6. **Given** the report step, **When** it runs, **Then** it generates the top-N clusters by student
   impact, each with both questions, both answer keys, the count, and the trainer's decision where
   one exists.
7. **Given** the same stored rows, **When** the report is regenerated, **Then** the output is
   identical — it is a pure function over the table and is never hand-edited.
8. **Given** the report, **When** it is delivered, **Then** it is the point at which the Lab stops: a
   human carries the correction into the Production admin, and **no** write path to `injazedu` is
   opened by this or any other phase.
9. **Given** an image-bearing pair, **When** the escalation path runs, **Then** it is never
   auto-escalated as a conflict (US2), because a different diagram is a different question.

---

### US7 — A human decision sits beside the AI verdict, never on top of it (Priority: P7)

A moderator opens a pair in Arabic, sees both questions with their options, correct answers, images
and similarity scores, sees the AI verdict labelled as a recommendation with its confidence and prompt
version, and settles it with one of five buttons. The decision is stored, attributed and timestamped —
and the verdict columns are untouched.

**Why this priority**: it is what makes "how often was the model wrong?" answerable later, and it is
the constitutional requirement that AI suggests and a human decides. It also closes the loop for
Phases 6 and 9, which reuse the same screen in two other modes.

**Independent test**: a Feature test — not a manual click-through — that records a decision and then
asserts the review row's author, timestamp and previous/new status alongside an unchanged verdict.

**Acceptance Scenarios**:

1. **Given** a cluster, **When** the review screen renders, **Then** it is Arabic with correct RTL,
   shows both questions side by side with options, derived correct answers and attached images, and
   shows the similarity scores, the AI verdict and its confidence.
2. **Given** any AI output on screen, **When** it is displayed, **Then** it is labelled as a
   recommendation with its confidence and prompt version, visually distinct from measured values.
3. **Given** the five actions, **When** any is used, **Then** a review row is written with the
   decision, the reviewer, the timestamp, the previous status and the new status.
4. **Given** a recorded human decision, **When** the candidate row is inspected, **Then** every
   `llm_*` column is **unchanged** — the review table is append-only and never overwrites a verdict.
5. **Given** each of the five actions, **When** it is used, **Then** the cluster's status and
   relation type change exactly as FR-128's map states, and the review row records the transition
   it made.
6. **Given** a cluster already at `urgent_review`, **When** a trainer records a decision on it,
   **Then** its status becomes `resolved` and it leaves the backlog — with the count it was ranked
   by still readable.
7. **Given** P3 not yet existing, **When** the screen renders, **Then** the statistics row is
   **absent** — no placeholder dashes, no guessed schema.
8. **Given** the reviewer identity, **When** it is stored, **Then** it references the Lab's **own**
   operator accounts and no Production identity, and no PII column exists in any P2 table.
9. **Given** any displayed count, **When** it is shown, **Then** it carries its sample size and the
   snapshot date it came from, and clicking it reaches the underlying questions.
10. **Given** the screen's three modes, **When** each is opened, **Then** `calibration` offers the
    seven-value taxonomy, `spot_check` offers confirm/reject, and `uncertain_review` offers the five
    production actions — with keyboard shortcuts, because the screen is used ~1,900 times.

---

### Edge Cases Worth Naming

- **A question whose `search_text` normalizes to an empty string** (punctuation or whitespace only).
  It must not join a single mega-cluster of empty text: an empty `search_text` is recorded, excluded
  from hash clustering and from candidate generation, and logged as an anomaly.
- **A question with `answer_key_state` other than a single valid key** (the 34 multi-key rows, the 31
  with no correct option). It is still normalized, hashed and clustered — but `same_correct_answer`
  and the conflict determination are undefined for it, so it is never auto-escalated as
  `conflicting_duplicate`; it is flagged for a human instead.
- **A soft-deleted question.** It gets a derived row like every other, and is excluded from every
  embedding, candidate and cluster step. A deleted question is history; it is not a duplicate of
  anything.
- **The 18 questions flagged `requires_media_review` for audio.** Excluded from the text paths, as P1
  already flags them; they are not silently compared as though their text were the whole item.
- **A hash group larger than any batch.** Clustering must not hold a whole group in memory to find
  its lowest member; the canonical member is derivable by SQL.
- **A chain that is not a duplicate.** `(A,B)` and `(B,C)` both clear `T_high` while `(A,C)` does
  not. Closure still merges them — that is the accepted trade — but a component that grows past the
  size guard is flagged rather than written, so a runaway merge surfaces as a review item instead of
  a 400-member cluster nobody notices.
- **Two questions differing only by an option's `points` value** (not its text). The options hash is
  built from normalized option **text** in `option_index` order; a points-only difference does not
  change the hash but *may* change `is_correct_derived` — which is precisely a `conflicting_duplicate`.
- **A candidate proposed by trigram but not by vector, or the reverse.** Both sources merge into one
  row keyed by the canonical pair; the second writer updates the missing score rather than inserting a
  duplicate.
- **An embedding config version change mid-run.** Rows carrying a different
  `embedding_config_version` are not comparable; a mixed population fails the coverage assertion
  rather than producing quietly wrong distances.
- **A pair the model can never judge** — malformed text, or a response that fails validation every
  time. It exhausts its retry budget, is marked terminally failed, and stops being dispatched. The
  danger it avoids is the quiet one: a null verdict looks identical to "not yet judged", so without a
  terminal state the pair is re-sent on every run forever.
- **A verdict arriving for a pair whose band was recalibrated since dispatch.** The proof query is
  evaluated against the band at the time it runs, so a recalibration that would invalidate the proof
  must re-band and re-verify rather than leave a stale non-zero count.
- **The stimulus column being filled by a future snapshot.** The section derive step already exists
  and would begin producing rows; nothing silently hashes an unnormalized passage.
- **The eval set's negative control landing inside the candidate set** after a re-run of candidate
  generation. The control is drawn from outside the candidate set **at sampling time** and its
  provenance is recorded, so a later re-run does not retroactively invalidate it.
- **A cluster whose canonical member is itself soft-deleted** after clustering. Canonical selection is
  deterministic over the group as it stood; the console shows the deletion rather than silently
  re-picking.

---

## Requirements

### The eight tables and the foreign-key convention

- **FR-001**: All P2 artefacts MUST live in **new, Lab-owned tables**. No P1 mirror table may gain a
  column, and no mirror column may be modified.
- **FR-002**: The feature MUST create eight tables: derived question text and vectors, derived section
  text, candidate pairs, clusters, cluster members, reviews, labelled evaluation pairs, and evaluation
  runs.
- **FR-003**: Every column referencing the mirror MUST be named `*_source_id` and MUST reference
  `source_id`, never the Lab surrogate `id`. Columns named `*_id` reference a P2 table's surrogate id.
- **FR-004**: A test MUST prove that a cluster-member row joins to a real question **through
  `source_id`** — the Decision 2 defect caught by a test rather than by a reviewer.
- **FR-005**: The 768-dimension vector columns MUST use the framework's native vector column type; no
  additional vector package is added.
- **FR-006**: Migrations MUST carry a header stating the table is P2-owned and is **not** part of the
  P1 mirror, so a later reader does not mistake it for something the ETL maintains.
- **FR-007**: `NoPiiInLabSchemaTest` MUST pass over all eight tables. **Amended 2026-08-28 (notes.md
  N7)**: the test scans `information_schema` across every non-framework table, so it covers the eight
  the moment they exist and needs **no edit** — `reviewer_id` and `labelled_by` are not on its
  forbidden list, which names `user_id`, not `*_id`. This requirement is a verification, and **no
  exemption is written**.
- **FR-008**: Phase 1 MUST create **only the indexes each table needs to be written and read**. The
  trigram index is created by the phase whose query requires it (FR-042).
- **FR-009**: No P2 table may appear on either allowlist in the source configuration, because P2
  performs no MySQL read or write of any kind.

### The Arabic normalizer and the hash core

- **FR-010**: The system MUST derive `clean_text` from `raw_text` by **technical cleanup only** —
  HTML stripping, whitespace collapse, Unicode NFC — with meaning preserved.
- **FR-011**: The system MUST derive `search_text` from `clean_text` by applying, **in this order**:
  option-label stripping (FR-139), tatweel removal, diacritic removal, punctuation normalization
  (FR-155), Arabic↔Latin digit unification, selected Alef-form normalization, and **Unicode-aware
  case folding** (FR-140). **Amended 2026-08-28**: the alphabets, the delimiters and the anchoring
  rule are now named in FR-139 rather than left as "where present", because 3,604 options carry a
  **Latin** label against 1,200 Arabic ones — an Arabic-only implementation would miss the majority
  case. **Amended 2026-08-29 (gate H, T003)**: option-label stripping is now pinned as the **first**
  transform, not the last — the label alphabet's four Hamza-Alef forms (`أ إ آ ا`) must still be
  distinct when the stripper runs, and Alef-form normalization would otherwise fold them first.
  "Selected Alef-form normalization" is pinned to **`أ`/`إ`/`آ` → `ا` only**; `ى` (alef maksura) is
  never folded, in the strict path or the fuzzy one.
- **FR-012**: The **strict** normalizer MUST NOT apply any meaning-changing transformation.
  Specifically, `ة` MUST NEVER be rewritten to `ه` in `clean_text`, in `search_text`, or in **any
  hash, cluster key or identity decision derived from them**, and a negative test MUST fail the
  moment that transform is added to the strict path. **Amended 2026-08-28**: the prohibition is
  scoped rather than blanket, because FR-141 admits one **explicitly-named recall-only** fold that
  can never assert identity. Constitution IV was narrowed to match (v2.5.0). Widening FR-141 beyond a
  recall aid, or letting its output reach a hash, is the violation this wording exists to catch.
- **FR-013**: Normalization MUST be idempotent: applying `search` to its own output MUST return an
  identical string.
- **FR-014**: The HTML-stripping branch MUST be implemented even though the snapshot contains no
  markup, so a later import carrying markup cannot silently produce a hash over raw tags.
- **FR-015**: `question_text_hash` MUST be SHA-256 over `search_text`. `question_with_options_hash`
  MUST be SHA-256 over `search_text` joined with the normalized options in `option_index` order.
- **FR-016**: The options string MUST be built by consuming P1's existing `option_index`.
  `OptionIndexDeriver`'s ordering MUST be reused and MUST NEVER be re-derived, because re-solving the
  `options.order` tie differently would silently produce different hashes.
- **FR-017**: `media_fingerprint` MUST be a hash over the **ordered** attached image paths, and null
  when the question carries no attached image.
- **FR-018**: A `normalizer_version` constant MUST be written to every derived row, so a rule change
  makes a stale hash visible rather than silently wrong.
- **FR-019**: Hashing MUST be sensitive to a changed option's text and **insensitive** to the input
  order in which options are presented.

### Text layers and the first clustering

- **FR-020**: Every question in the mirror — all 29,142, soft-deleted rows included — MUST receive
  exactly one derived row with a non-null `search_text`.
- **FR-021**: The section derive step MUST run over sections flagged as carrying a stimulus, MUST
  **log the processed count**, and MUST be expected to write zero rows on this snapshot.
- **FR-022**: Clustering MUST group by `question_with_options_hash` where the group holds more than
  one member, restricted to non-deleted questions.
- **FR-023**: Every hash group MUST be **split by `media_fingerprint` before clustering**. Members
  with differing fingerprints MUST NOT enter the same cluster.
- **FR-024**: The canonical member of a cluster MUST be the **lowest** `question_source_id` in the
  cluster as it stands after closure (FR-116), chosen deterministically and stable across runs.
- **FR-025**: A stem-only match — text hash equal, options hash differing — MUST NOT be
  auto-clustered. It MUST become a candidate with `hash_match_level = 'formatting'`, routed to the
  high band.
- **FR-026**: Every derivation, embedding, candidate and cluster step MUST filter to non-deleted
  questions. Only the derive step processes soft-deleted rows.
- **FR-027**: A question whose `search_text` normalizes to empty MUST be recorded, excluded from hash
  clustering and candidate generation, and logged as an anomaly rather than clustered.
- **FR-028**: Re-running the hash-cluster step MUST produce an identical cluster count, member count
  and canonical member for every cluster.

### The media boundary rule

- **FR-029**: Two questions with identical `search_text` whose attached image sets differ MUST NEVER
  be auto-clustered by any step.
- **FR-030**: Such a pair MUST NEVER be escalated as `conflicting_duplicate` by an automatic step.
- **FR-031**: Such a pair MUST be recorded as a candidate with `media_relation = 'different_media'`,
  so a human and the model can both still see it.
- **FR-032**: `media_relation` MUST be computed at **candidate insertion time** from both
  fingerprints — a second, independent enforcement of the rule after FR-023.
- **FR-033**: The verdict prompt MUST state the rule explicitly, as a **third** line of defence, and a
  contract test MUST prove a synthetic identical-text/different-image pair does not return
  `exact_duplicate`.
- **FR-034**: The review console MUST show both attached images side by side for any pair whose media
  relation is `different_media`.

### Embeddings

- **FR-035**: The system MUST produce `stem_embedding` (the question alone) and `full_embedding` (the
  question with its options) for every active question.
- **FR-036**: The embed step MUST deduplicate **each embedding by its own key** and write the
  resulting vector to every member of that group: `stem_embedding` by `question_text_hash`,
  `full_embedding` by `question_with_options_hash`. **Amended 2026-08-28 (notes.md N1)**: the two
  grains are different sizes — ~11,094 and ~12,969 distinct values respectively — so the call count is
  **~24,063**, not the 2 × 11,416 the project plan states. 11,416 is the distinct *raw* text count and
  is the key for neither embedding. The measured saving against 2 × 28,747 = 57,494 is **58%**, and
  the cascade's argument is unchanged; only the arithmetic was wrong. Sharing one key across both
  embeddings is a defect in either direction.
- **FR-037**: The client MUST send **raw text**. The mandatory prefix, the 768-dimension output and
  the L2 normalization are the service's contract; applying the prefix on both sides would corrupt
  every vector while looking correct.
- **FR-038**: Every populated row MUST carry `embedding_config_version` from the response, and all
  populated rows MUST share **one** value.
- **FR-039**: A truncated embedding MUST set its truncation flag **and** record an error row with a
  dedicated code. Truncation is invisible unless it is recorded.
- **FR-040**: A failed or zero-norm embedding MUST record a dedicated error code and the batch MUST
  continue. A zero-norm vector is an **error**, never something to normalize.
- **FR-041**: Where both models are needed in one session, the chat model MUST be loaded **before**
  the embedding model — the reverse order evicts the embedding runner on this machine.
- **FR-042**: After the embed step, **zero** active questions may have a null `stem_embedding`.

### Candidate generation

- **FR-043**: A trigram GIN index MUST be created on `search_text`, in the phase whose query needs it,
  and a query plan MUST show it in use rather than a sequential scan.
- **FR-044**: Lexical candidates MUST be produced by trigram similarity above a floor, capped per
  question.
- **FR-045**: Semantic candidates MUST be produced by cosine distance over `stem_embedding` at
  top-K=20 per distinct text, by **exact scan**. No HNSW index may be created.
- **FR-046**: Both sources MUST merge into one candidate row per pair, canonicalised so that
  `question_a_source_id < question_b_source_id`, with a UNIQUE constraint on the ordered pair.
- **FR-047**: A second writer for an existing pair MUST update the missing score rather than insert a
  duplicate.
- **FR-048**: Every candidate MUST record `same_section`, `media_relation` and the embedding config
  version at generation time; `band` MUST remain null until calibration sets it.
- **FR-049**: Every layer MUST **log what it excluded**, so a silent truncation can never look like
  coverage.

### The evaluation set

- **FR-050**: The sampler MUST draw the evaluation set in **waves** whose sizes come from
  configuration (`[100, 100, 100, 100]` on arrival), writing `purpose = 'calibration'` and the wave
  number. **Every wave MUST independently satisfy the full decile stratification** (0.95–1.00 down to
  0.50–0.60, every decile non-empty) **and every quota in FR-051**. This is the property that makes
  early stopping safe: a set stopped at 100 is a *smaller* ruler, never a *biased* one. **Amended
  2026-08-28**: the previous "exactly 400" is now the cumulative ceiling (FR-146), not the first
  draw — a solo operator cannot commit 5–10 hours of labelling before knowing whether 1.5 hours would
  have settled it.
- **FR-051**: Each wave MUST fill explicit quotas for exact-hash pairs, `different_media` pairs,
  same-section and cross-section pairs, **`formatting` pairs (same stem, different options)**,
  **`orthographic` pairs (FR-142)**, **answer-key-conflict pairs**, and a **random-pair negative
  control drawn from outside the candidate set entirely**. **Amended 2026-08-28**: the three added
  quotas are the hard cases the gate exists to measure — and the `orthographic` quota is what makes
  the new fuzzy form (FR-141) *measured on the eval set* rather than assumed to help.
- **FR-052**: Stratification MUST require no threshold — it operates on the real, uncalibrated scores
  the candidate phase already produced.
- **FR-053**: The labeling screen MUST present both questions side by side with options, derived
  correct answers and attached images, and MUST offer the **full seven-value relation taxonomy** plus
  `same_learning_objective`, `same_correct_answer` and a confidence.
- **FR-054**: `human_relation` MUST use the **same seven values** the verdict schema uses (FR-132). A
  coarser labeling vocabulary would make precision and recall incomputable against the model's output.
- **FR-055**: The screen MUST offer keyboard shortcuts, because it is used roughly 1,900 times across
  the project.
- **FR-056**: A doubled subsample MUST be labelled by two people and inter-rater agreement MUST be
  computed and recorded. **Amended 2026-08-28**: the `label_round = 2` labeller works **blind** of any
  AI suggestion (FR-148), so where round 1 was AI-assisted this same measurement doubles as the
  anchoring measurement — at no additional cost.
- **FR-057**: Every calibration row MUST end with a non-null `human_relation`, an attributed labeller
  and a timestamp.
- **FR-058**: The evaluation-set report MUST be **generated from the stored rows** as a pure function
  over the table, regenerate identically, and never be hand-edited.

### Calibration, the gate, and the conditional benchmark

- **FR-059**: The calibrator MUST sweep thresholds deterministically against `human_relation`, with
  `exact_duplicate ∪ semantic_duplicate` as the positive class.
- **FR-060**: The gate is **precision ≥ 0.90 at recall ≥ 0.70**, taken from §17 directly. The
  governing plan's pointer to "the target in §21" is a dangling reference to an unwritten section and
  MUST NOT be waited on.
- **FR-061**: Calibration MUST write one evaluation-run row **per wave** holding precision, recall,
  both 95% confidence intervals, the positive-class count, `threshold_low`, `threshold_high`, the
  wave number, an explicit `gate_passed` and an explicit `expansion_decision` of
  `expand` / `stop_pass` / `stop_fail` (FR-144, FR-145). A failed gate MUST be **recorded**, and there
  MUST be no path that proceeds past it silently.
- **FR-062**: The thresholds MUST be projected across the **full** candidate pool to compute
  `projected_uncertain_band_count`.
- **FR-063**: If the projected uncertain band exceeds **8,000**, `T_low` MUST be raised and the result
  recomputed, recorded as **another row** — a logged, deliberate tightening, never a silent
  adjustment.
- **FR-064**: Calibration MUST NOT run against labels whose inter-rater agreement is poor;
  reconciliation and re-labelling come first.
- **FR-065**: Evaluation runs MUST NEVER be overwritten. A re-run produces a comparison row so "did
  changing the model help?" stays answerable, and exactly one row carries the selected flag.
- **FR-066**: The calibration report MUST be generated from the stored rows and regenerate
  identically.
- **FR-067**: If and only if the gate fails, the system MUST support benchmarking predefined
  alternatives plus a 512-dimension truncation of the incumbent, re-embedding **only the evaluation
  set's questions** — never the bank — and writing one row per model.
- **FR-068**: **Recall@20 decides** the benchmark: a pair that is never shortlisted cannot be rescued
  by any verdict.
- **FR-069**: Pulling any alternative model onto this machine MUST require explicit human approval,
  because it is a new dependency.
- **FR-070**: Adopting a different embedder MUST require an explicit human decision and an ADR,
  after which the embed and candidate phases re-run in full before re-calibration. An embedder switch
  adopted without that decision is not accepted at any value.
- **FR-071**: If no embedder clears the gate, the semantic track becomes a program-level open item.
  The gate MUST NOT be lowered to let the project proceed; Layers 0–2 still ship.

### The verdict endpoint and the rationed band

- **FR-072**: Every candidate MUST be assigned a band of `exact`, `high`, `uncertain` or `low` from
  the calibrated thresholds.
- **FR-073**: The verdict endpoint MUST return §17's seven fields verbatim: relation,
  `same_learning_objective`, `same_correct_answer`, confidence, issues, `recommended_action`,
  `review_required`.
- **FR-074**: Generation MUST be constrained by a JSON Schema **and** the response MUST be validated
  server-side before it is returned. Schema-constrained generation is not a guarantee, and
  regex-parsing prose is forbidden.
- **FR-075**: The prompt MUST be versioned `v1`. A change creates `v2` and never overwrites `v1`, and
  every stored verdict records the prompt version that produced it.
- **FR-076**: Question and option text MUST reach the prompt as **delimited data, never as
  instructions**. Text that reads like a directive is a question, not a command.
- **FR-077**: A verdict MUST be dispatched only for candidates where `band = 'uncertain'`, no verdict
  exists yet, and the pair is not terminally failed (FR-124).
- **FR-078**: A budget guard MUST run before **every** verdict dispatch — that is, every call whose
  result is written to a `llm_*` column on a candidate row — and MUST throw for any pair outside the
  uncertain band. **Amended 2026-08-28**: the guard is scoped in wording to the dispatch path it was
  always about, because FR-147's evaluation pre-label is a **second, separately budgeted and
  separately counted** path that writes only `duplicate_eval_pairs.ai_*` (FR-149). The guard is not
  weakened and its ceiling is not raised; a pre-label that touched a candidate row would still throw.
- **FR-079**: The count of candidates carrying a verdict whose band is not `uncertain` MUST be
  exactly **0**. This is not accepted at any value above zero.
- **FR-080**: The verdict run MUST record its pair count and elapsed time against the ≤5,000-pair /
  ~6-hour target, and MUST be resumable — an interrupted batch re-judges nothing and skips nothing.
- **FR-081**: Each of the seven verdict fields MUST be stored in its **own column**, so a verdict is
  queryable rather than buried in a JSON blob.
- **FR-082**: A contract test on the service MUST cover schema validation, the seven-value enum, a
  malformed-response rejection, and the identical-text/different-image case.

### The high band and its spot-check

- **FR-083**: High-band candidates whose media relation is not `different_media` MUST be
  auto-clustered as `probable_duplicate` with `status = 'auto'` and `source_layer = 'high_band_auto'`,
  with **zero** model calls.
- **FR-084**: A stratified **5% (±1)** sample of the clusters that step created MUST be written for
  spot-check and worked through the labeling screen in confirm/reject mode.
- **FR-085**: A rejected spot-check MUST set the cluster's status to `rejected` and write a review
  row. The cluster MUST NOT be deleted — it is the only record that the auto path made a mistake.
- **FR-086**: The verdict step and the auto-cluster step read disjoint bands of the same table and
  MUST be able to run in parallel without writing the same row.

### Cluster shape and membership

*Added by the 2026-08-27 clarification. Numbered from the end of the list so the 115 requirements
above keep the numbers any later artefact already refers to.*

- **FR-116**: Clusters derived from **pairs** — the high band and the LLM verdict — MUST be formed by
  **transitive closure over that layer's qualifying pairs**. A question MUST belong to at most **one**
  cluster per `source_layer`. Hash clusters are already closed by construction, because hash equality
  is transitive within a media fingerprint.
- **FR-117**: Closure MUST be computed only over pairs that already passed the media boundary rule. A
  `different_media` pair MUST NEVER join two components, so the rule cannot be defeated by chaining.
- **FR-118**: Closure MUST be deterministic and idempotent: the same pair set MUST produce the same
  components, the same canonical member and the same member count on every run, **independent of the
  order pairs were processed in**.
- **FR-119**: A component whose size exceeds a configured guard MUST NOT be written as a single
  cluster. It MUST be recorded and flagged for human review, carrying its size and the pairs that
  chained it, so a runaway merge is **visible rather than silent**. The guard is a configuration
  value tuned by measurement — it is not a gate, and exceeding it stops the merge, not the pipeline.
- **FR-120**: Because a question belongs to at most one cluster per layer, `affected_student_count`
  (FR-088) sums each member's statistics **exactly once** and needs no cross-cluster deduplication. A
  question appearing in two clusters of the same layer is a defect, and a test MUST catch it.
- **FR-121**: Clusters from **different** layers MAY overlap — a question may sit in a hash cluster
  and in a verdict cluster. The console MUST make a question's other cluster memberships visible
  rather than presenting each cluster in isolation.

### Verdict failure handling

- **FR-122**: A failed verdict call — non-2xx, timeout, or a response that fails validation — MUST
  record an error row with a dedicated code and MUST NOT stop the batch.
- **FR-123**: The candidate row MUST carry a **verdict attempt count** and the **last error**, so a
  failure is visible on the pair itself and not only in a log.
- **FR-124**: A pair MUST be retried at most a bounded number of attempts **across runs**. Past that
  it MUST be marked **terminally failed** and MUST NEVER be re-dispatched — otherwise a permanently
  failing pair re-spends the rationed budget on every run and no run is ever idempotent.
- **FR-125**: Terminally failed pairs MUST be surfaced in the console as a **countable** review item,
  so they are neither invisible nor mistaken for pairs the model judged.
- **FR-126**: A terminally failed pair MUST NOT be auto-clustered or auto-escalated. **An absent
  verdict is not a verdict**, and it must never be read as one.

### Cluster status transitions

- **FR-127**: Before any human input, a cluster's status MUST be `auto` when it was produced without a
  human in the loop (hash clusters and high-band auto-clusters), and `pending_review` when it awaits
  one — a verdict cluster flagged `review_required`.
- **FR-128**: The five review actions MUST map onto status and relation type as follows, and a test
  MUST assert the map:

  | Action | New status | Relation type |
  |---|---|---|
  | `same` | `confirmed` | unchanged |
  | `valid_variant` | `confirmed` | `same_objective_variant` |
  | `not_duplicate` | `rejected` | unchanged |
  | `conflict` | `urgent_review` | `conflicting_duplicate` |
  | `skip` | `skipped` | unchanged |

- **FR-129**: A decision recorded on a cluster already at `urgent_review` MUST set `resolved` — this
  is the trainer's arbitration of a conflict, and it is what removes the item from the backlog.
- **FR-130**: A human decision MAY change the cluster's `relation_type`, because the cluster carries
  the Lab's **current** best answer rather than its first guess. It MUST NEVER change any `llm_*`
  column on the candidate row (FR-097).
- **FR-131**: Every transition MUST write `previous_status` and `new_status` on the review row, and
  the model's verdict MUST remain queryable **beside** the post-review relation type, so "how often
  was the model wrong?" is answerable from stored data rather than reconstructed.

### The two relation vocabularies

- **FR-132**: The **verdict and human-label** vocabulary is §17's seven values exactly —
  `exact_duplicate`, `formatting_duplicate`, `semantic_duplicate`, `same_objective_variant`,
  `related_not_duplicate`, `conflicting_duplicate`, `not_related` — and the model, the labeling screen
  and the calibration positive class all draw from this one set, or the gate is incomputable.
- **FR-133**: The **cluster** vocabulary is a separate seven values: the above minus `not_related`,
  plus `probable_duplicate`. `probable_duplicate` MUST be writable **only** by the high-band auto
  path — it asserts that a threshold was cleared and nobody looked, which is not something a model
  may claim. No cluster may ever be created with `not_related`.
- **FR-134**: The two vocabularies MUST NOT be collapsed into one enum, and a test MUST assert that
  no verdict or human label carries `probable_duplicate` and no cluster carries `not_related`.

### The daily review cap

- **FR-135**: A **configurable daily review cap** MUST apply to the ongoing queues — uncertain-band
  review and the conflicting-duplicate backlog.
- **FR-136**: The cap MUST be **soft**: on reaching it the console states that today's cap is reached
  and shows the remaining backlog size, and the reviewer MAY continue deliberately. It never blocks.
- **FR-137**: The calibration set MUST be **exempt** from the cap, in every wave. It is a bounded task
  that blocks calibration, and capping it would delay the project rather than pace it.
- **FR-138**: The count reviewed today MUST be displayed beside the cap, so the weekly commitment in
  the human gates is **measurable rather than asserted**.

### Option labels, case folding, and the fuzzy recall form

*Added by the 2026-08-28 operator decisions. Numbered from the end so FR-001 to FR-138 keep the
numbers every other artefact already refers to.*

- **FR-139**: Option-label stripping MUST remove **only a leading option marker** and MUST NEVER
  remove a letter occurring naturally inside the text. The match MUST be anchored to the start of the
  string, MUST consume exactly one label token, and MUST require a delimiter after it:

  ```text
  alphabets   Arabic   أ إ آ ا ب ج د هـ ه
              Latin    A B C D E   a b c d e
              digits   0–9 · ١ ٢ ٣ ٤ ٥
  delimiters  .  )  -  :  ،  ,   and the ( ) -wrapped forms of each label
  anchor      ^\s*  LABEL  \s*  DELIMITER  \s+          — nothing else matches
  ```

  Both alphabets are required, not optional: **3,604 active options begin with a Latin label against
  1,200 with an Arabic one**, and 2,844 of the 28,747 active questions (9.9%) contain no Arabic
  character at all, because the bank carries English/STEP/IELTS courses beside the Arabic ones
  (measured 2026-08-28, notes.md N10). The alphabets and delimiters are configuration, so a course
  type using a marker not listed here costs an edit rather than a code change.

- **FR-140**: `search_text` MUST apply **Unicode-aware case folding**. This is not optional polish:
  the ~11,094 distinct-stem figure the entire ~24,063 embedding budget rests on was measured **with**
  lower-casing (notes.md N1), so omitting it would make the budget wrong; and with 9.9% of the bank
  non-Arabic, two English questions differing only in capitalisation would otherwise hash as
  different items.

- **FR-141**: A **fourth, explicitly-named recall form** MAY be derived from `search_text` by a
  configured character-fold map. Only `ة → ه` ships enabled. This form MUST NOT feed `clean_text`,
  `search_text`, `question_text_hash`, `question_with_options_hash`, `media_fingerprint`, any cluster
  key, or any identity decision. It exists to **propose candidates**, and nothing else. Constitution
  IV (v2.5.0) admits exactly this and no more.

- **FR-142**: A pair equal under the fuzzy form but **not** equal under `question_text_hash` MUST be
  recorded as a candidate with `hash_match_level = 'orthographic'`. Such a pair MUST NEVER be
  auto-clustered by any step, MUST NEVER be assigned `relation_type = 'exact_duplicate'` by any
  automatic path, and MUST route to the high band for a verdict or a human decision — the same
  treatment FR-025 gives a `formatting` match.

- **FR-143**: A test MUST prove both properties independently: (a) `question_text_hash` and
  `question_with_options_hash` are **byte-identical** with the fold enabled and disabled, so the fold
  provably cannot reach the strict path; and (b) an orthographic-only pair appears in no cluster
  produced by any automatic step.

### Progressive calibration and the AI pre-label

- **FR-144**: After each wave the calibrator MUST compute the gate on the **cumulative** labelled set
  and MUST expand to the next wave unless **all four** hold:

  ```text
  1. every similarity decile and every quota is non-empty at the cumulative n
  2. inter-rater agreement on the doubled subsample is acceptable        (FR-064)
  3. the positive class holds at least 30 pairs
  4. the 95% Wilson lower bound of precision >= 0.90 AND of recall >= 0.70
  ```

  Condition 3 borrows the constitution's own `n >= 30` full-metrics threshold (VI). Condition 4 is
  what "the sample cannot support a reliable decision" means arithmetically, and it **raises** the
  bar: FR-060's gate was a point estimate, and a point estimate of 0.90 precision over ~40 positives
  carries a 95% interval of roughly [0.76, 0.97]. A gate cleared on the interval at n=100 is stronger
  evidence than one cleared on the point estimate at n=400.

- **FR-145**: If the 95% Wilson **upper** bound of precision is below 0.90 at the current wave, the
  gate MUST be recorded as failed and the embedder fork taken **without** labelling the remaining
  waves. Labelling 300 more pairs to confirm an already-decisive failure spends the scarcest resource
  in the project on the one path where it changes nothing.

- **FR-146**: **400 cumulative labels is the ceiling and it never moves.** At 400 the expansion
  stops, the gate decision is taken on the point estimate, and the confidence interval is recorded
  beside it whichever way the decision goes.

- **FR-147**: An AI **pre-label** MAY be produced for an evaluation pair as a suggestion. It MUST be
  stored in dedicated `ai_*` columns on the evaluation pair, and MUST NEVER be written to
  `human_relation`, MUST NEVER be written to any `llm_*` column on a candidate row, and MUST NEVER
  enter the calibration positive class. **The human label remains the sole ground truth.**

- **FR-148**: The labeling screen MUST NOT reveal the AI suggestion until the labeller has recorded
  their own label. The row MUST record whether the suggestion was shown and whether the human revised
  after seeing it. The `label_round = 2` labeller MUST work **blind**, so FR-056's existing
  inter-rater agreement between an assisted and an unassisted labeller **measures the anchoring
  effect** rather than leaving it assumed.

- **FR-149**: The pre-label call path MUST be separately budgeted and separately counted, with a
  ceiling equal to the evaluation-set size. It MUST NOT write any candidate row, so **FR-079's
  out-of-band counter continues to read exactly zero** — the rationing proof is preserved, not
  relaxed.

### Backlog triage

- **FR-150**: Every conflicting cluster MUST carry a `priority_tier` computed by **deterministic
  SQL** from the measured `affected_student_count` distribution, at percentile cut points held in
  configuration (0.50 / 0.75 / 0.90 on arrival). The **computed cut values** MUST be logged with each
  run, because the boundary is a measurement of the current population, not a constant. The tier MUST
  NEVER be assigned, adjusted or overridden by a model.

- **FR-151**: The conflict backlog is a **standing queue**. No phase, gate, report, acceptance
  criterion or test may block on its remaining size, and no artefact may present it as a task to be
  emptied. A full acceptance run MUST pass with **zero** conflicts reviewed.

- **FR-152**: `daily_review_cap` MUST default to **10** and MUST remain soft (FR-136). The console
  MUST display per-tier remaining counts beside today's reviewed count, so the operator sees which
  tier the cap is being spent on.

- **FR-153**: AI triage output MUST be advisory: stored in clearly named `ai_triage_*` columns,
  displayed as a labelled recommendation with its confidence and prompt version, visually distinct
  from measured values (FR-095). It MUST NEVER write `affected_student_count`, `priority_tier`,
  `status` or `relation_type`. **Only a human review row moves a conflict out of `urgent_review`**
  (FR-129) — a model may not resolve a conflict that exists because two humans disagreed about a
  correct answer.

- **FR-154**: An AI triage call MUST be **on demand, on one cluster, and human-initiated**. No batch
  pass over the backlog is permitted, because most of the 928 conflicts come from the hash layer,
  carry no verdict, and would constitute a new unbounded model path outside the uncertain band. Where
  a verdict already exists, its stored `llm_recommended_action` and `llm_confidence` are **displayed
  rather than recomputed**. No AI path in this feature opens a write path to Production (FR-092).

### Punctuation preservation (gate H, 2026-08-29)

*Numbered from the end, same convention as FR-139 – FR-154.*

- **FR-155**: `search_text`'s punctuation-normalization step (FR-011) MUST NOT strip or collapse
  punctuation and symbols that are **load-bearing for technical or linguistic meaning**. At minimum
  this covers: a decimal point inside a number (`3.14`), a percent or degree sign (`%`, `°`), a unit
  or fraction slash (`km/h`, `1/2`), mathematical/comparison operators and signed numbers
  (`+ - × ÷ = < > ±`), and an apostrophe inside a contraction or possessive (`don't`). Only
  punctuation that is purely decorative or structural in the normalized layer — a trailing sentence
  period, quote marks, a dash or colon used as list-item formatting — MAY strip and collapse to a
  single space. The unit test suite (T025) MUST cover representative English/scientific examples
  alongside Arabic ones, because STEP/IELTS and science-course content is where the ambiguity
  between "formatting punctuation" and "meaningful symbol" is sharpest (notes.md N10; spec
  Clarifications, session 2026-08-29).

### The conflicting-duplicate backlog

- **FR-087**: Any `conflicting_duplicate` verdict, from the model or from a human, MUST create a
  cluster with `status = 'urgent_review'`.
- **FR-088**: `affected_student_count` MUST be a deterministic SQL sum of `source_item_stats.n` at the
  active scope across the cluster's members, each counted exactly once (FR-120). **The model never
  computes this number.**
- **FR-089**: The backlog MUST be ordered by `urgent_review` first, then by `priority_tier`, then by
  `affected_student_count` descending — **never by `id`** (amended 2026-08-28, FR-150).
- **FR-090**: The full remaining backlog size MUST be displayed **per tier**, so a partially worked
  list never looks finished and the operator can see which tier the work is being spent on.
- **FR-091**: The conflict report MUST be generated from stored rows: the top N clusters by student
  impact, each with both questions, both answer keys, the affected count, its tier, and the trainer's
  decision where one exists. It MUST also state the **measured concentration** — the share of total
  affected-student exposure the reported top N accounts for — so partial coverage is reported as
  impact covered rather than as rows remaining. It MUST regenerate identically and never be
  hand-edited.
- **FR-092**: The report is where the Lab stops. A human carries corrections into the Production
  admin; **no phase in this feature opens a write path to the source**.
- **FR-093**: A question whose answer key is not a single valid key MUST NOT be auto-escalated as a
  conflict; it is flagged for a human instead.

### The review console

- **FR-094**: The console MUST be Arabic with correct RTL, showing both questions side by side with
  options, derived correct answers, attached images, similarity scores, and the AI verdict with its
  confidence.
- **FR-095**: AI output MUST be labelled as a recommendation, carrying its confidence and prompt
  version, and MUST be visually distinct from measured values.
- **FR-096**: The five review actions MUST each write a review row carrying the decision, the
  reviewer, the timestamp, the previous status and the new status.
- **FR-097**: A human decision MUST NEVER overwrite a verdict column on the candidate row. It may
  change the cluster's `relation_type` (FR-130); the model's own output is untouched, which is what
  makes "how often was the model wrong?" answerable later.
- **FR-098**: The P3 statistics row MUST be **absent**, not stubbed. The lookup reports unavailability
  and the row does not render; P2 does not model P3's schema before P3 exists.
- **FR-099**: Reviewer and labeller identities MUST reference the Lab's own operator accounts and no
  Production identity.
- **FR-100**: Every displayed statistic MUST carry its sample size and the snapshot date, and every
  count MUST reach the underlying questions in one click.
- **FR-101**: The labeling screen MUST serve all three purposes — the seven-value taxonomy for
  calibration, confirm/reject for spot-check, and the five production actions for uncertain review.

### The command, the guarantees, and the wrap-up

- **FR-102**: One command MUST drive the pipeline, taking a step selector, a resume flag and a chunk
  size, dispatching to job classes and reusing P1's run recorder, resume cursor, error recorder and
  batch upsert **unchanged**.
- **FR-103**: The steps MUST be: derive-text, hash-cluster, embed, candidates, eval-sample,
  eval-report, calibrate, verdict, auto-cluster, conflict-report — plus benchmark-embedders, which
  runs only on a failed gate.
- **FR-104**: Invoked with no step, the command MUST run the unconditional steps in dependency order
  and MUST **stop with a clear message** at the first step whose human input is missing, rather than
  proceeding on absent labels.
- **FR-105**: Every run MUST be recorded with its kind, its counts and its elapsed time, so the plan's
  projections are checked against reality.
- **FR-106**: Every step MUST be **idempotent**: a second run against the same snapshot produces zero
  new clusters and zero new candidates.
- **FR-107**: Every long step MUST be **resumable**: interrupted and resumed, it loses no row and
  duplicates none.
- **FR-108**: **No source question may be deleted.** The mirror's question count MUST be identical
  before and after a full run.
- **FR-109**: **No row may be written by the Lab to the source.** The three read-only layers stay
  green, or the project stops.
- **FR-110**: An eleventh health check MUST verify the verdict endpoint's reachability, following the
  existing health-check pattern, and `lab:health` MUST pass **11/11, exit 0**.
- **FR-111**: The acceptance run MUST execute the test suite, the health check, and then the full
  pipeline **twice**, asserting the second run changes nothing.
- **FR-112**: The generated reports MUST all regenerate identically from stored rows; none is
  hand-edited.
- **FR-113**: `README.md` MUST gain the P2 section (its commands and its console), and every new
  environment key MUST be listed in the template **with no value**.
- **FR-114**: The distinct-text count (11,416) and the redundancy rate (60.3%) MUST be recorded in
  the project instruction files, byte-identical between them, because every future estimate starting
  from 28,747 is roughly twice too large.
- **FR-115**: No new runbook, ADR, acceptance record or handover document may be produced. The one
  permitted exception is an embedder-switch ADR, and only if the gate forced that decision.

---

## Key Entities

- **Derived question text** — one row per question holding the three-layer text, the two hashes, the
  media fingerprint, both vectors, the normalizer and embedding versions, and the truncation flags.
  The mirror's faithfulness is preserved by keeping all of this **beside** it.
- **Derived section text** — the same shape for a passage. Built because the Production column exists;
  empty on this snapshot, and asserted to be so rather than assumed.
- **Candidate pair** — an undirected, canonically ordered pair proposed by a paid layer, carrying its
  trigram and cosine scores, its hash match level, its section and media relations, its band, the
  seven verdict fields as seven columns, and its verdict attempt count and last error. The unit of
  work for everything after Phase 5.
- **Cluster** — a group of questions judged to be the same item, with a deterministic canonical
  member, a relation type, a status, the layer that produced it, and — for conflicts — the count of
  students it reached. It is a **transitive component within one layer**, not a pair: a question
  belongs to at most one cluster per layer, though it may appear in clusters of different layers.
  **Never a deletion.**
- **Cluster member** — one question's membership in one cluster, with a canonical flag. Unique per
  (cluster, question).
- **Review** — one human decision on one cluster: the decision, the reviewer, the time, the status
  transition, and a note. Append-only, and **the one artefact in the Lab with no other source**.
- **Evaluation pair** — a sampled pair drawn for one of three purposes, carrying the band and score it
  was sampled at, and the human's label against the seven-value taxonomy. The ruler the whole cascade
  is measured with.
- **Evaluation run** — one calibration or benchmark: the embedder and dimension, the pair count, the
  metrics, both thresholds, the projected band size, whether the gate passed, and whether it was
  selected. Never overwritten, so a model change stays comparable.
- **The cascade** — five layers of increasing cost, where each layer only ever sees what the layer
  below could not settle. Skipping one is a defect, not a shortcut.
- **The band** — the calibrated partition of candidates into dropped, auto-clustered, model-judged and
  exact. It is what makes the project finite rather than open-ended.

---

## Success Criteria

- **SC-001**: The three text layers exist for every question, and `raw_text` is never modified.
- **SC-002**: Normalization preserves meaning in the strict path: `ة` is never rewritten to `ه` in
  `clean_text`, `search_text` or either hash — proven by a test that fails if the rule is removed and
  by a second test showing both hashes are **byte-identical** with the fuzzy fold on and off — and
  `search(search(x)) = search(x)`. An orthographic-only match never becomes `exact_duplicate` by any
  automatic path.
- **SC-003**: Literal and formatting duplicates are detected deterministically, with no model
  involved, and the canonical member of every cluster is the same on every run.
- **SC-004**: Two questions with identical text and different attached images are never
  auto-clustered, and the rule is enforced at hashing **and** at candidate generation — not only at
  the verdict.
- **SC-005**: Every active question has both embeddings, with the service-applied prefix and **one**
  embedding config version across every populated row.
- **SC-006**: Embedding calls number approximately **24,063** — ~11,094 stem plus ~12,969 full —
  against 57,494 undeduplicated. The hash layer saved **58%** of the paid work, and the measured count
  is recorded rather than assumed (amended 2026-08-28, notes.md N1).
- **SC-007**: Every truncation is flagged and logged; none is silently accepted.
- **SC-008**: Nearest-neighbour search returns the expected neighbours on a fixture, and the trigram
  query plan shows the index in use.
- **SC-009**: The evaluation set is labelled **wave by wave**, each wave independently stratified
  across every decile with every quota filled; the four-condition expansion rule fired at each wave
  and its decision is recorded as a row; and inter-rater agreement is measured rather than assumed.
  The cumulative set never exceeds 400.
- **SC-010**: Calibration records precision, recall and both thresholds; a failed gate is recorded and
  never bypassed; and no evaluation run is overwritten by a later one.
- **SC-011**: The projected uncertain band is at or below 8,000, and any tightening that achieved
  that is recorded as its own row.
- **SC-012**: The verdict path saw only uncertain-band pairs — the count of candidate rows carrying a
  verdict whose band is not `uncertain` reads exactly **zero**. The evaluation pre-label path is
  counted separately and never exceeds the evaluation-set size.
- **SC-013**: The verdict endpoint returns schema-validated structured output, the prompt is versioned
  `v1`, and a malformed response is rejected rather than parsed.
- **SC-014**: A human can override any AI verdict, and the decision is stored with its author and
  time **beside** the verdict rather than on top of it.
- **SC-015**: The high band is clustered with zero model calls, and its spot-check sample is 5% (±1)
  of the clusters that step created.
- **SC-016**: No source question is deleted: the mirror's question count is identical before and after
  a full run.
- **SC-017**: Every conflicting duplicate carries an affected-student count derived from
  `source_item_stats.n` by SQL **and a `priority_tier` derived from the measured distribution of that
  count**, with the computed percentile cut values logged for the run; the backlog is ordered by
  tier then count; and the full remaining size is visible per tier.
- **SC-018**: A synthetic conflicting cluster with known statistics produces a report count equal to
  the raw SQL sum exactly.
- **SC-019**: All three generated reports regenerate identically from stored rows; none is
  hand-edited.
- **SC-020**: The pipeline is idempotent: a second full run produces zero new clusters and zero new
  candidates.
- **SC-021**: Any long step interrupted mid-run and resumed loses no row and duplicates none.
- **SC-022**: No column in any of the eight tables can hold personal data, proven against the schema.
- **SC-023**: Zero rows are written by the Lab to the source database, and the three read-only layers
  remain green.
- **SC-024**: `lab:health` passes **11/11**, exit 0, at the end of every phase.
- **SC-025**: The full test suite is green, including the thirteen named guard tests.
- **SC-026**: Clusters are per-layer transitive components: no question appears in two clusters of the
  same source layer, closure is independent of processing order, no `different_media` pair joins two
  components, and every component past the size guard is flagged rather than written.
- **SC-027**: Each of the five review actions produces exactly the status and relation type FR-128's
  map states; a decision on an `urgent_review` cluster sets `resolved`; and every transition is
  recorded with its previous and new status beside an unchanged model verdict.
- **SC-028**: A verdict that fails every attempt is marked terminally failed, is never re-dispatched
  on a later run, is countable in the console, and is never read as a verdict by any clustering or
  escalation path.
- **SC-029**: The ongoing review queues carry a configurable daily cap that informs rather than
  blocks, display today's count beside it, and exempt the calibration set.
- **SC-030**: `README.md` documents the P2 commands and console; every new environment key is listed
  with no value; the distinct-text count and redundancy rate are recorded byte-identically in both
  instruction files; and no new runbook, ADR, acceptance record or handover document exists — except
  an embedder-switch ADR, and only if the gate forced that decision.
- **SC-031**: Option-label stripping removes a leading marker in **both** scripts — `أ.`, `B)`, `c-`,
  `٣.` — and never a letter inside the text: `دمشق` and `A cat sat on the mat` survive intact.
  `search_text` is case-folded, so two English questions differing only in capitalisation share a
  hash.
- **SC-032**: No AI value ever becomes ground truth: no `ai_*` value on an evaluation pair appears in
  `human_relation` or in the calibration positive class, proven by test; and the AI suggestion is not
  retrievable by the screen until the human label is recorded.
- **SC-033**: No AI value ever becomes a measured number: `affected_student_count`, `priority_tier`
  and `status` are unchanged by any AI triage output, proven by test, and no conflict leaves
  `urgent_review` without a human review row.
- **SC-034**: The backlog blocks nothing. The full acceptance run passes with **zero** conflicts
  reviewed, and no gate, report or criterion references its remaining size as a condition.
- **SC-035**: Punctuation that is load-bearing for meaning survives `search_text` unchanged — a
  decimal point, a percent/degree sign, a unit slash, signed numbers and mathematical operators, and
  an apostrophe inside a contraction — proven by tests covering both English/scientific and Arabic
  examples (FR-155), while purely decorative punctuation still strips and collapses.

---

## Assumptions

Recorded so they are visible rather than implied. Measurements are from the loaded mirror on
2026-08-27 against the fixed 2026-08-07 snapshot unless noted.

- **The snapshot is fixed and never refreshed.** Nothing here blocks on its age; the date travels with
  every number as context.
- **P1's numbers are inputs, not findings to re-derive.** The distinct-text count, the redundancy
  rate, the image counts and the conflict estimate are read from the mirror, and a mismatch against
  the same snapshot is a bug in this feature, not drift in the bank.
- **The plan's group-level tallies were wrong and are now measured.** §2.2's breakdown did not sum
  (4,602 + 136 = 4,738 against a stated 4,689). Re-measured 2026-08-28: **4,558 + 131 = 4,689**, with
  **928** non-image and 96 image-bearing conflicting groups (notes.md N2). These supersede the plan.
  They are still measured over `raw_text` and are a floor — Phase 3 records the post-normalization
  values, which will be larger.
- **The two relation vocabularies are deliberately distinct** — confirmed by clarification and now
  binding as FR-132 to FR-134, no longer an assumption.
- **`purpose = 'uncertain_review'` rows are created from verdicts flagged `review_required`**, which
  is the population §8 item E budgets at ≤1,500 pairs. No other step writes that purpose.
- **The bank is not Arabic-only.** 2,844 of 28,747 active questions (9.9%) contain no Arabic
  character, because InjazEdu carries English/STEP/IELTS courses. Every text rule — label stripping,
  case folding, the fuzzy fold's inapplicability to Latin script — is written for both (notes.md N10).
- **Both new folds are small-yield, and that is stated rather than discovered.** Measured at the stem
  grain, the `ة → ه` fold collapses ~12 more distinct stems and case folding ~5, out of ~11,097. The
  fuzzy form earns its place as a *labelled, measured recall aid* — its `orthographic` eval quota
  (FR-051) exists precisely so Phase 7 can retire it if the labels say it adds nothing.
- **The conflict backlog's impact is highly concentrated, and the triage design rests on that.** Top
  25 groups = 21.1% of exposure, top 50 = 34.1%, top 100 = 50.4%, top 200 = 67.3%. **Zero** of the 928
  groups have zero measured impact, so no "no-impact" tier is built.
- **The command exposes eleven steps, not the nine the plan's deliverables list mentions**; the
  step list in FR-103 is the authoritative one.
- **P2 performs no MySQL access.** It reads the Lab mirror and writes Lab tables, so neither allowlist
  changes and `SourceReader` is not involved. The three read-only layers stay in force and are
  re-asserted, not re-implemented.
- **The passage track is inert but built.** Building the table costs a migration and a job; discovering
  later that a re-import filled the column and that nothing normalizes it would cost a schema change
  with data in it.
- **The structural section rule survives Decision 3.** `same_section` is recorded on every candidate
  and shown to a reviewer; what it cannot do on this snapshot is justify an auto-exclusion, because a
  shared section with no shared text is not evidence of anything.
- **HTML cleaning is priced at zero.** It is a few lines inside a class that is built anyway, and it
  protects a contract rather than serving a current row.
- **Deleted questions are derived but never compared**, matching P1's "copy everything, filter at
  analysis" rule.
- **Vector storage is ~70 MB** (11,416 × 2 × 768 × 4 bytes), comfortably inside the range where an
  exact scan is the right default and an index is not yet earned.
- **The 5,000-pair figure is a target and the 8,000-pair figure is a ceiling.** The first is what an
  overnight batch comfortably absorbs; the second is where the batch stops being an overnight job and
  the thresholds must tighten.
- **No ADR is written by default.** The only candidate is an embedder switch — architectural, durable
  and expensive to reverse — and only if the gate forces it.
- **The existing chat model is the verdict generator.** It is already declared in the service's
  configuration and is not a new dependency; the alternative embedders are, which is why they need
  approval.
- **The review console reuses the existing panel and queue driver.** No new framework, no Horizon.
- **`lab:health` at 10/10 is the arrival condition and 11/11 is the exit condition.** Every phase is
  checked against it.

---

## Dependencies

- `005-p1-profiling-and-question-mirror` accepted: the fifteen mirror tables loaded, the derivation
  core tested, the ETL primitives reusable, `lab:health` 10/10 exit 0.
- The mirror actually populated in `injazedu_lab` — ~673 MB with statistics loaded. **It is not
  re-imported for this feature, and no destructive database command is run against it.**
- The ai-service running with the embedding contract `eg300m-qat-q4_0/sim-v1/768/l2norm` live and
  health-checked, and the chat model available for the verdict endpoint.
- PostgreSQL 17 with pgvector and pg_trgm available; the trigram extension enabled before FR-043's
  index is created.
- **Human time, and it is blocking once** (amended 2026-08-28): labelling **wave 1, 100 pairs**
  (1.5–2.5 hours) blocks calibration; further waves are drawn only when FR-144's rule requires them.
  The conflict backlog needs **no** time commitment before anything ships — it is a standing queue
  (FR-151).
- Human approval, conditionally: to pull alternative embedder weights onto this machine, and to adopt
  a different embedder if one clears the gate where the incumbent does not.
- Confirmation from a trainer of the fold rules and the **option-label alphabets for both scripts**
  (FR-139), before normalization runs at scale.
- No connection to the production site, in this or any other feature.

---

## Human Gates

Items a developer cannot carry out alone. **A is the only blocking gate** (amended 2026-08-28 — F was
reclassified; see below).

| # | Gate | Blocks |
|---|---|---|
| **A** | 🔴 Label **wave 1 — 100 pairs** (1.5–2.5 hrs; a moderator, with a trainer for disagreements). Expand to 200/300/400 **only** when FR-144's rule says the sample cannot decide | Calibration, and therefore everything downstream of it |
| **B** | 🔴 Approve pulling alternative embedder weights onto this machine (~2–3 GB) | Only on a failed gate |
| **C** | 🔴 Decide whether to switch the embedding model — a switch invalidates every stored vector | Only if an alternative clears the gate and the incumbent does not |
| **D** | 🟡 Confirm or reject the 5% spot-check of the auto-clustered high band | Nothing; the check is the point |
| **E** | 🟡 Review uncertain-band verdicts flagged `review_required` (≤1,500 pairs, 12–25 hrs) | Ongoing alongside the later phases |
| **F** | ⚪ **Configuration, not a gate** (reclassified 2026-08-28). The operator's decision is recorded: no trainer commitment; the 928-item backlog is a **standing queue** worked by rank under `daily_review_cap = 10`. **Blocks nothing** (FR-151) | Nothing |
| **G** | 🟡 Receive the conflict report and correct the answer keys **in the Production admin** | The Lab cannot write to Production and never will |
| **H** | ✅ **Closed 2026-08-29.** Fold rules and both **option-label alphabets** (FR-139) confirmed against a 35-row real-mirror sample; the label-stripping/Alef-normalization order (FR-011) and punctuation preservation (FR-155) were pinned in the same session | Was: before normalization runs at scale |

**Why F stopped being a gate.** It required committing 31–77 trainer hours before the console could
ship, but nothing downstream depended on the backlog being *worked* — the console ships whether or
not one item has been reviewed. The constitution's gate policy names this case exactly: *"Procedural
gates are not gates and are not written … any check whose only purpose is satisfying another
document."* The engineering properties it appeared to protect — deterministic ranking, the full size
always visible, no Production write path — are protected by FR-088 to FR-092 and FR-150 to FR-154,
which are tested. Nothing was weakened by removing it; a scheduling promise was.

**Carried over and now overdue**: the taxonomy authoring request from P1 §8. It starts at P2 and costs
2–4 weeks of elapsed time before P5 can use it.

---

## Handoff to P3 and P4

```text
P3 receives: nothing — it depends only on P1 and may already have started, in parallel.
             What it gains after this feature is the intersection: a conflicting_duplicate cluster
             whose members also carry r_pbis < 0 is two independent methods naming the same wrong
             answer key. P2 stores the cluster; P3 computes the coefficient; neither blocks the
             other, and the console's statistics row appears when P3 ships, with no rework to
             anything P2 stored.

P4 receives: search_text — the comparable representation of every question
             duplicate_clusters — layer one of the three-layer audit, already grouped
             answer-key conflicts — found, ranked by student impact, and partly arbitrated
             the honest denominator — 11,416 distinct items, not 28,747 rows
             calibrated thresholds — T_low / T_high with the evidence behind them
             a working verdict endpoint — structured output, versioned prompts, and a rationing
             pattern to copy

Numbers that are not re-derived: duplicate_eval_runs is the reference for every similarity threshold
in the program. A later project that needs T_high reads it from there rather than re-calibrating, or
the program ends up with several versions of the truth — the same rule P1 set for its profiling
results.
```

---

## Open Items Carried Forward

| # | Item | Impact |
|---|---|---|
| 1 | The governing plan still ends at §20; §21 (metrics) is referenced but unwritten | **Does not block P2** — §17's own gate is used directly (FR-060). Needs resolution before P4 |
| 2 | The conflict volume exceeds the program's whole human-review budget (928 groups against 30–60 hrs program-wide) | **Resolved for P2, 2026-08-28**: the backlog is a standing queue that blocks nothing (FR-151), tiered and worked by rank at 10/day. The top 100 items carry 50.4% of measured exposure. The program's budget table should still be corrected once a real per-item time exists — but P2 no longer waits on it |
| 3 | `sections.description` is empty in all 3,316 rows | The passage track is inert. A future snapshot with stimulus text makes the excerpt rule a small, well-scoped addition — not a rewrite |
| 4 | The 60.3% redundancy rate is a finding no project owns | P2 measures it and does not draw the conclusion; P4 is where it should be interpreted |
| 5 | The PII schema test may need a narrow exemption for the reviewer and labeller columns | Both reference the Lab's own operator accounts. If the test's rules reject them, the exemption is written explicitly — never worked around silently |
