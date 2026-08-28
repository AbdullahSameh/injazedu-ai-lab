# Tasks: P2 — Arabic Normalization & Duplicate Intelligence

**Feature**: `006-p2-duplicate-intelligence` · **Branch**: `p2/duplicate-intelligence`
**Input**: [spec.md](./spec.md) (154 FRs, 2 clarification sessions) · [plan.md](./plan.md) (eleven
groups, no open questions) · [notes.md](./notes.md) (ten Phase 0 findings) ·
[data-model.md](./data-model.md) · [contracts/verdict.md](./contracts/verdict.md)

**Tests are included and are not optional here.** Constitution V requires them for exactly this kind
of work — the deterministic core, the guardrails, and the eval runs — and the spec names thirteen
suites plus a gate that is only meaningful if it executes.

**Format**: `- [ ] TID [P?] [Story?] Description with file path` · `[P]` = different file, no
dependency on an incomplete task · `[OPERATOR]` = needs the human, not the developer.

---

## Shape

Phases follow **dependency order**, which for this feature is also close to priority order. Two
deviations, both deliberate:

1. **US2 (the media boundary) is an invariant, not a vertical slice.** The spec requires it enforced
   in three places. Phase 4 delivers the fingerprint and the first enforcement; the second and third
   land inside Phases 5 and 7 as `[US2]`-labelled tasks, because they live in code those phases
   create. The shared boundary test grows with them.
2. **The labeling screen is built in US4's phase, not US7's.** US4's independent test is "label the
   set through the screen", so the screen is what makes that story testable. US7's phase builds the
   *cluster review console*, which is a different screen serving different modes.

```text
Setup ─► Foundational ─► US1 ─► US2 ─► US3 ─► US4 ─(🔴 human)─► US5 ─┬─► US6 ─► US7 ─► Wrap-up
        (schema + rules)  hash   media  embed   eval + gate          └── US5's two halves
                          layer  rule   + cand                            run in parallel
```

**The MVP is Phase 3 (US1).** It resolves 60.3% of the bank with no model, no threshold and no human
— and it is the slice that survives every go/no-go failure downstream.

---

## Phase 1: Setup

- [X] T001 `apps/lab/config/lab.php` — add a `dedup` block: `trgm_floor`, `top_k` (**20**),
      `chunk_size`, `closure_size_guard` (initial value, tuned in T092 — **not** applied to hash
      clusters), `daily_review_cap` (**10**, FR-152), `verdict_max_attempts`,
      `uncertain_band_ceiling` (**8000**), `verdict_target_pairs` (**5000**), and the three
      `docs/reports/p2-*.md` paths.
- [X] T001b `config/lab.php` — the **normalization** keys (FR-139 – FR-141): `option_label_alphabets`
      (Arabic `أ إ آ ا ب ج د هـ ه` · Latin `A–E`, `a–e` · digits `0–9`, `١–٥`),
      `option_label_delimiters` (`. ) - : ، ,` and the `( )`-wrapped forms), `fuzzy_fold_enabled`
      (**true**) and `fuzzy_fold_map` (**`ة → ه` only** on arrival — `ى/ي` is deliberately not
      shipped). Both alphabets are required: 3,604 options begin with a **Latin** label against 1,200
      Arabic, and 9.9% of the bank has no Arabic character (notes.md N10).
- [X] T001c `config/lab.php` — the **calibration** keys (FR-144 – FR-146): `eval_wave_sizes`
      (`[100, 100, 100, 100]`), `eval_ci_confidence` (**0.95**), `eval_positive_class_floor`
      (**30**), `eval_cumulative_ceiling` (**400**), `ai_prelabel_enabled` (**false**); and the
      **triage** keys (FR-150): `conflict_tier_percentiles` (`[0.50, 0.75, 0.90]`).
- [X] T002 Record the arrival baseline: run `php artisan lab:health` and confirm **10/10, exit 0**.
      This is the instrument every phase below is checked against; a phase that breaks it is not done.
      **Confirmed 2026-08-29: 10/10, exit 0** (re-confirmed after the T001 config change).
- [X] T003 [OPERATOR] **Human gate H** — confirm with a trainer, **before T036 runs at scale**: the
      `ة → ه` prohibition in the strict path, the **`fuzzy_fold_map`** (which typo tolerances belong
      in the recall layer), and the **option-label alphabets for both scripts** (T001b). A
      normalization rule that quietly changes meaning corrupts every hash downstream of it; an
      Arabic-only label list would miss the majority of labelled options.
      **Approved 2026-08-29** against a 35-row real-mirror validation sample. Decisions recorded in
      spec.md Clarifications (session 2026-08-29), FR-011 (amended), FR-155 (new), SC-035 (new), and
      the Human Gates table (row H closed):
      - `ة → ه` stays prohibited in every strict layer, hash, cluster key and identity decision;
        tolerated only in the recall-only fuzzy layer, which can never assert `exact_duplicate` alone.
      - `fuzzy_fold_map` ships with **`ة → ه` only** and is closed to further entries without a
        measured yield and a dedicated isolation test.
      - Both option-label alphabets confirmed as specified; stripping fires only on a leading marker.
      - **New**: option-label stripping runs *before* Alef-form normalization in `search()`.
      - **New**: Alef-form normalization is scoped to `أ`/`إ`/`آ` → `ا` only — `ى` is never folded.
      - **New** (FR-155): punctuation normalization preserves load-bearing punctuation/symbols
        (decimals, %, °, unit slashes, operators, contraction apostrophes) and only strips/collapses
        purely decorative punctuation; T025/T025c amended to test both the positive and negative
        cases, including English/scientific examples.

**Checkpoint**: config in place, baseline recorded, the one cheap human confirmation requested.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: the eight tables and the four pure rules. **⚠️ No user story can begin until this is
done.** Groups A and B in the plan — independent of each other, so the migrations and the rules can
be written side by side.

**Every migration in this phase carries a header stating the table is P2-owned and is NOT part of the
P1 mirror** (FR-006), and every `*_source_id` column references `source_id`, never the surrogate `id`
(FR-003).

### The eight migrations — dependency order (data-model.md §11)

- [ ] T004 `apps/lab/database/migrations/2026_08_28_100000_create_source_question_derived_table.php`
      — per data-model.md §2: both hashes, **`fuzzy_text_hash`** and `media_fingerprint` indexed,
      `stem_embedding` and `full_embedding` as `$table->vector($col, 768)`, `normalizer_version`,
      **`fuzzy_rules_version`** (separate on purpose — the strict hashes do not depend on the fold),
      the two truncation flags, UNIQUE on `question_source_id`. **No trigram index here** — Phase 5
      earns it (FR-008), and `fuzzy_text_hash` gets a **btree, not a GIN** (FR-141, plan.md).
- [ ] T005 [P] `..._100100_create_source_section_derived_table.php` — data-model.md §3. No embedding
      column: adding one before a passage exists is speculation.
- [ ] T006 [P] `..._100200_create_duplicate_candidates_table.php` — data-model.md §4, including
      `verdict_attempts`, `verdict_last_error` and `verdict_failed` (FR-123, FR-124), UNIQUE on the
      canonical ordered pair, INDEX on `band` and on (`band`, `verdict_failed`). `hash_match_level`
      admits **`exact` | `formatting` | `orthographic` | NULL** (FR-142).
- [ ] T007 [P] `..._100300_create_duplicate_clusters_table.php` — data-model.md §5, with
      `member_count`, **`priority_tier`** (FR-150) and the five **`ai_triage_*`** columns (FR-153),
      and INDEX (`status`, `priority_tier`, `affected_student_count` DESC) so the backlog can never
      fall back to ordering by `id` (FR-089).
- [ ] T008 `..._100400_create_duplicate_cluster_members_table.php` — UNIQUE (`duplicate_cluster_id`,
      `question_source_id`). Depends on T007.
- [ ] T009 `..._100500_create_duplicate_reviews_table.php` — data-model.md §7, including
      `previous_relation_type` / `new_relation_type` (FR-130, FR-131). Depends on T007.
- [ ] T010 [P] `..._100600_create_duplicate_eval_pairs_table.php` — data-model.md §8, with
      `label_round` in the UNIQUE key (`a`, `b`, `purpose`, `label_round`) so the doubled subsample is
      a second row (FR-056); **`sample_wave` as a separate indexed column and NOT in the key**
      (FR-050); and the six `ai_*` / revision columns (FR-147, FR-148). **`sample_wave` and
      `label_round` are orthogonal axes** — conflating them would make waves 2 and 3 read as second
      and third labellers and silently corrupt inter-rater agreement (data-model.md §8).
- [ ] T011 [P] `..._100700_create_duplicate_eval_runs_table.php` — data-model.md §10, including
      `inter_rater_agreement`, `gate_passed`, **`sample_wave`**, **`positive_class_count`**, both
      **precision and recall 95% CI bounds**, and **`expansion_decision`** (FR-061, FR-144, FR-145).
- [ ] T012 Run `php artisan migrate --env=testing` against `injazedu_lab_test` and confirm all eight
      build. **Never** `migrate:fresh`/`refresh`/`reset` against `injazedu_lab` (constitution III).

### The eight models

- [ ] T013 [P] `apps/lab/app/Models/SourceQuestionDerived.php` — `$connection = 'pgsql'`, vector and
      timestamp casts, `belongsTo(SourceQuestion, 'question_source_id', 'source_id')`.
- [ ] T014 [P] `apps/lab/app/Models/SourceSectionDerived.php`
- [ ] T015 [P] `apps/lab/app/Models/DuplicateCandidate.php` — `llm_issues` cast to array.
- [ ] T016 [P] `apps/lab/app/Models/DuplicateCluster.php` — `hasMany` members, `hasMany` reviews.
- [ ] T017 [P] `apps/lab/app/Models/DuplicateClusterMember.php`
- [ ] T018 [P] `apps/lab/app/Models/DuplicateReview.php`
- [ ] T019 [P] `apps/lab/app/Models/DuplicateEvalPair.php`
- [ ] T020 [P] `apps/lab/app/Models/DuplicateEvalRun.php`

### Schema guardrails

- [ ] T021 `apps/lab/tests/Feature/Dedup/ForeignKeyThroughSourceIdTest.php` — a
      `duplicate_cluster_members` row joins to a real `source_questions` row **through `source_id`**,
      and a join through the surrogate `id` returns the wrong row or none. **This is the single most
      likely defect in the feature** (data-model.md §1), caught by a test rather than a reviewer.
- [ ] T022 `apps/lab/tests/Feature/NoPiiInLabSchemaTest.php` — **verify it passes over the eight new
      tables with no edit** (notes.md N7). Its scan is already table-agnostic and `reviewer_id` /
      `labelled_by` are not on its forbidden list, which names `user_id`, not `*_id`. **Do not edit
      this file** and do not add an exemption; if it fails, that is a finding, not a licence to relax
      a security assertion.
- [X] T023 [P] `apps/lab/tests/Feature/Dedup/RelationVocabularyTest.php` — no verdict or human label
      may carry `probable_duplicate`, and no cluster may carry `not_related` (FR-134). The two
      vocabularies stay separate.

### The four pure rules — no database, testable before they touch a row

- [X] T024 [P] `apps/lab/app/Support/Dedup/ArabicNormalizer.php` — `clean()` (HTML strip, whitespace
      collapse, NFC — meaning preserved), `search()` in the **gate-H-pinned order** (2026-08-29):
      option-label stripping via T024b **first**, then tatweel, diacritics, punctuation normalization
      that **preserves meaningful punctuation/symbols** (FR-155), Arabic↔Latin digits, Alef-form
      normalization scoped to **`أ`/`إ`/`آ` → `ا` only** (never `ى`), and **Unicode case folding**
      per FR-140 last — and `fuzzy()` (FR-141) — a **recall-only** fold of `search_text` driven by
      `config('lab.dedup.fuzzy_fold_map')`, which ships with **`ة → ه` only** and gets no further
      entries without a measured yield and its own isolation test. Two version constants: `VERSION` →
      `normalizer_version` and `FUZZY_VERSION` → `fuzzy_rules_version`, **separate on purpose** so
      disabling the fold makes no strict hash look stale (FR-010 – FR-014, FR-018, FR-140, FR-141,
      FR-155).
- [X] T024b [P] `apps/lab/app/Support/Dedup/OptionLabelStripper.php` — leading-anchored,
      single-token, delimiter-terminated stripping over **both** alphabets from `config` (FR-139),
      run against `clean_text` **before** T024's Alef-form normalization — the four Hamza-Alef label
      forms (`أ إ آ ا`) must still be distinguishable when it runs. Regex anchored
      `^\s* LABEL \s* DELIM \s+`; **never** a letter inside the text.
- [X] T025 `apps/lab/tests/Unit/Dedup/ArabicNormalizerTest.php` — tatweel · diacritics · digit
      unification · Alef forms (`أ/إ/آ → ا`, and `ى` proven **untouched**) · **case folding** ·
      **idempotence** (`search(search(x)) === search(x)`) · the HTML branch on synthetic markup · the
      **explicit negative test that `ة` is never rewritten to `ه` in `search()`**, which must fail the
      moment anyone adds that transform to the strict path (FR-012) · and **punctuation preservation**
      (FR-155, SC-035): `3.14`, `50%`, `36.5°C`, `km/h`, `±5`, `don't` keep their load-bearing
      punctuation/symbols in `search_text`, while a trailing period or decorative quote strips and
      collapses — English/scientific cases required alongside Arabic ones.
- [X] T025b `apps/lab/tests/Unit/Dedup/FuzzyFoldIsolationTest.php` — **the test that keeps the
      carve-out honest** (FR-143a): `questionTextHash()` and `questionWithOptionsHash()` are
      **byte-identical** with `fuzzy_fold_enabled` true and false, over a fixture containing `ة` and
      `ه` variants. If the fold ever reaches a strict hash, this fails. Constitution IV v2.5.0 permits
      the fold **only** on that condition.
- [X] T025c `apps/lab/tests/Unit/Dedup/OptionLabelStripperTest.php` — `أ. المدينة` → `المدينة` ·
      `B) Paris` → `Paris` · `c- cat` → `cat` · `٣. ثلاثة` → `ثلاثة`; and the negatives that matter:
      **`دمشق`, `A cat sat on the mat`, `B.F. Skinner`, `Vitamin A`, and `A root plus derivations`
      survive untouched** — a mid-string or non-leading label-shaped token, and a delimiter with no
      trailing whitespace, must never strip (FR-139, SC-031; validated against the gate H sample,
      spec Clarifications session 2026-08-29).
- [X] T026 [P] `apps/lab/app/Support/Dedup/OptionsNormalizer.php` — the ordered normalized options
      string, consuming P1's existing `option_index`. **`OptionIndexDeriver` is reused, never
      re-derived** (FR-016): the `options.order` tie is already solved and re-solving it differently
      would silently produce different hashes.
- [X] T027 `apps/lab/tests/Unit/Dedup/OptionsNormalizerTest.php` — options presented in a different
      input order produce an **identical** string; a changed option text produces a different one.
- [X] T028 [P] `apps/lab/app/Support/Dedup/DuplicateHasher.php` — `questionTextHash()`,
      `questionWithOptionsHash()`, `fuzzyTextHash()`, `mediaFingerprint(array $orderedPaths)`. The
      fingerprint hashes an **ordered list** and folds a NULL path in as the empty string (notes.md
      N5, FR-017). `fuzzyTextHash()` is the **only** consumer of `ArabicNormalizer::fuzzy()`, and its
      output is never an input to the other two.
- [X] T029 `apps/lab/tests/Unit/Dedup/DuplicateHasherTest.php` — hash stability and sensitivity
      (FR-019); a two-image question fingerprints differently from a one-image question; a NULL path
      is defined rather than `sha256(null)`.
- [X] T030 [P] `apps/lab/app/Support/Dedup/ClusterClosure.php` — union-find over a pair list,
      returning components with the **lowest** member as canonical. Pure, no database (plan.md
      decision 4).
- [X] T031 `apps/lab/tests/Unit/Dedup/ClusterClosureTest.php` — `(A,B)` + `(B,C)` yields one component
      `{A,B,C}`; **shuffling the input pairs changes nothing** (FR-118); a component past the size
      guard is reported rather than returned as a cluster (FR-119).
- [X] T031b [P] `apps/lab/app/Support/Dedup/WilsonInterval.php` — a pure two-sided Wilson score
      interval for a binomial proportion at `config('lab.dedup.eval_ci_confidence')`. No database, no
      dependency. This is the arithmetic FR-144 and FR-145 are stated in.
- [X] T031c [P] `apps/lab/app/Support/Dedup/CalibrationExpansionRule.php` — a pure function returning
      `expand` | `stop_pass` | `stop_fail` from the four conditions of FR-144 plus FR-145's decisive
      failure. Kept out of `ThresholdCalibrator` deliberately: **the stopping rule is the one place a
      progressive design can silently become a weaker gate**, so it is testable without a row.
- [X] T031d `apps/lab/tests/Unit/Dedup/WilsonIntervalTest.php` and
      `CalibrationExpansionRuleTest.php` — the interval matches hand-computed values at
      known (successes, n); the rule returns `expand` when the lower bound straddles 0.90, `expand`
      when positives < 30 or a stratum is empty **even if the point estimate passes**, `stop_pass`
      only when all four conditions hold, and `stop_fail` when the **upper** bound is below 0.90.

### The command skeleton and the error codes

- [X] T032 `apps/lab/app/Support/Import/ImportErrorCode.php` — add `EMBEDDING_TRUNCATED`,
      `EMBEDDING_FAILED` and `VERDICT_FAILED`, each with a `severity()` and a `description()` arm. The
      enum's own docblock says a second list of these strings anywhere is a defect.
- [X] T033 `apps/lab/app/Console/Commands/LabDedup.php` — the skeleton following `LabImport`'s shape:
      `{--step=} {--resume} {--chunk=} {--count=}`, the eleven-step registry (FR-103), and
      **`SourceSnapshot::latestRun()` resolution plus `ran_via` recording** — both `import_runs`
      columns are NOT NULL with no default and the project plan mentions neither (notes.md N6).
- [X] T034 `LabDedup` — with no `--step`, run the unconditional steps in dependency order and **stop
      with a clear message** at the first step whose human input is missing (FR-104). It must never
      calibrate against absent labels.

**Checkpoint**: eight tables exist and are proven joinable through `source_id`; the four rules are
green under unit test before a single row is written; `lab:health` still 10/10.

---

## Phase 3: US1 — The bank's real duplication is found without a model (Priority: P1) 🎯 MVP

**Goal**: every question carries three text layers and two hashes; identical and formatting-only
duplicates collapse deterministically, with the canonical member chosen by rule. ~4,689 groups holding
22,020 questions resolve before a single embedding is computed.

**Independent test**: `lab:dedup --step=derive-text` then `--step=hash-cluster` on a clean database
→ 29,142 derived rows, a stable cluster count, and a second run that changes nothing.

- [ ] T035 [US1] `apps/lab/app/Jobs/Dedup/DeriveQuestionTextLayers.php` — chunked and resumable via
      `ResumeCursor`; reads each question with its options **and its `source_media` rows**; applies
      Phase 2's rules; upserts through `BatchUpsert`. Writes `fuzzy_text_hash` and
      `fuzzy_rules_version` **in the same pass** as the strict hashes, for the same reason the media
      fingerprint is (T045): a second pass would let them drift. Runs over **all 29,142** rows
      including soft-deleted ones (FR-020, FR-026).
- [ ] T036 [US1] `LabDedup --step=derive-text` — wired to T035, recorded in `import_runs` under
      `kind = 'p2_derive_text'`. **Gated on T003.**
- [ ] T037 [P] [US1] `apps/lab/app/Jobs/Dedup/DeriveSectionTextLayers.php` — the same for
      `source_sections WHERE has_stimulus = true`. Expected to process **zero** rows on this snapshot;
      it **logs the count** rather than assuming it (FR-021).
- [ ] T038 [US1] Empty-`search_text` handling in T035 — a question normalizing to empty is recorded,
      **excluded** from hash clustering and candidate generation, and logged as an anomaly rather than
      joining one mega-cluster (FR-027). Measured: zero instances today, so this is defensive.
- [ ] T039 [US1] `apps/lab/app/Jobs/Dedup/ClusterExactHashMatches.php` — group by
      `question_with_options_hash` having count > 1 over non-deleted questions; create clusters with
      `relation_type = 'exact_duplicate'`, `status = 'auto'`, `source_layer = 'hash'`; canonical member
      is the **lowest** `question_source_id`, derivable by SQL rather than by holding a whole group in
      memory (FR-022, FR-024).
- [ ] T040 [US1] `ClusterExactHashMatches` — a stem-only match (text hash equal, options hash
      differing) is **not** auto-clustered; it becomes a candidate with `hash_match_level = 'formatting'`
      routed to the high band (FR-025).
- [ ] T040b [US1] `ClusterExactHashMatches` — an **orthographic** match (`fuzzy_text_hash` equal,
      `question_text_hash` differing) is **not** auto-clustered either: it becomes a candidate with
      `hash_match_level = 'orthographic'` routed to the high band, exactly like `formatting`
      (FR-142). **No automatic path may promote it**, and none may assign it
      `relation_type = 'exact_duplicate'`. Skip the whole branch when `fuzzy_fold_enabled` is false.
      Log the count produced — measured expectation is small (~12 stem groups, notes.md N10), and a
      wildly larger number means the fold map is wrong, not that the bank changed.
- [ ] T041 [US1] `LabDedup --step=hash-cluster` — wired to T039/T040/T040b.
- [ ] T042 [P] [US1] `apps/lab/tests/Feature/Dedup/HashClusterTest.php` — literal and formatting
      duplicates are found with **no model**; the canonical member is the lowest id and is stable; a
      stem-only match does not auto-cluster; and **an orthographic-only pair appears in no cluster
      produced by any automatic step and carries no `exact_duplicate` relation** (FR-143b, SC-002).
- [ ] T043 [P] [US1] `apps/lab/tests/Validation/Dedup/DerivedCoverageTest.php` (MirrorValidation,
      read-only) — every one of the 29,142 questions has exactly one derived row with a non-null
      `search_text` and a recorded `normalizer_version`.
- [ ] T044 [US1] `apps/lab/tests/Feature/Dedup/HashClusterIdempotencyTest.php` — a second
      `--step=hash-cluster` produces an identical cluster count, member count and canonical member
      (FR-028).

**Checkpoint**: 🎯 **MVP.** The bank's 60.3% redundancy is resolved deterministically. If every
downstream gate failed tomorrow, this alone would still be worth shipping.

---

## Phase 4: US2 — Identical text with a different image is never a duplicate (Priority: P2)

**Goal**: the fingerprint exists and the hash layer already refuses to merge across it. Image-bearing
groups conflict at **73.3% against a 20.4% base rate — 3.6×** (notes.md N2), so this is the
false-positive trap the data proves is real.

**Independent test**: seed two questions with identical text and different `source_media` rows; run
hash-cluster; assert the pair is a candidate with `media_relation = 'different_media'` and is in no
cluster.

**Enforcement 2 of 3 is T057; enforcement 3 of 3 is T077.** They live in code later phases create.

- [ ] T045 [US2] `DeriveQuestionTextLayers` — compute `media_fingerprint` **in the same pass as the
      hashes** from `source_media WHERE type='image' AND attach_level='question'`, ordered by
      `source_id`. A second pass would be free to write and would let the two drift. **Video is
      excluded** and NULL when the question carries no image (FR-017, FR-032, plan.md decision 3).
- [ ] T046 [US2] `ClusterExactHashMatches` — **split every hash group by `media_fingerprint` before
      clustering** (FR-023). Members with differing fingerprints never enter the same cluster; the
      cross-fingerprint pairs are written to `duplicate_candidates` with
      `media_relation = 'different_media'` for a human instead (FR-029 – FR-031).
- [ ] T047 [P] [US2] `apps/lab/tests/Feature/Dedup/MediaBoundaryTest.php` — the shared boundary suite,
      **grown in T058 and T078**: identical text + different images is a candidate, is in no
      hash cluster, and carries `different_media`. A question with no media fingerprints NULL and
      relates as `no_media`.
- [ ] T048 [P] [US2] `apps/lab/tests/Feature/Dedup/MediaFingerprintOrderTest.php` — the four
      two-image questions are why the fingerprint hashes a list: two images in a different attachment
      order still fingerprint identically, and a genuinely different image set does not.

**Checkpoint**: the rule holds at the hash layer and has a suite ready to grow twice more.

---

## Phase 5: US3 — Semantic candidates inside a measured budget (Priority: P3)

**Goal**: every distinct surviving text is embedded **once** and shared across its group; a trigram
index and a top-K=20 vector scan produce the candidate pairs every later phase consumes.

**Independent test**: `--step=embed` → recorded call count ≈ **24,063**; `--step=candidates` →
`EXPLAIN` shows the GIN index in use.

### Embeddings — two dedup keys, not one (notes.md N1)

- [ ] T049 [P] [US3] `apps/lab/app/Support/AiService/EmbeddingClient.php` — HTTP client against
      `config('lab.ai_service.base_url')` `POST /embed`. **Sends raw text** — the service owns the
      mandatory prefix, and applying it on both sides corrupts every vector while looking correct
      (FR-037).
- [ ] T050 [US3] `apps/lab/app/Jobs/Dedup/EmbedQuestions.php` — **each embedding deduplicates by its
      own key**: `stem_embedding` by `question_text_hash` (~11,094 distinct), `full_embedding` by
      `question_with_options_hash` (~12,969). Write each vector to every member of **its own** group.
      **Do not share one key across both** — the text hash would give two questions with different
      options the same full vector, and the options hash would recompute 1,875 identical stem vectors
      (FR-036, notes.md N1).
- [ ] T051 [US3] `EmbedQuestions` — store `embedding_config_version` **from the response** on every
      row, plus the two truncation flags from the response's own `truncated` field. Filter to
      `source_deleted_at IS NULL` (FR-026, FR-038).
- [ ] T052 [US3] `EmbedQuestions` — a truncation sets its flag **and** records `EMBEDDING_TRUNCATED`
      through `ImportErrorRecorder`; a 502 `zero_norm_vector` or 503 `ollama_unreachable` records
      `EMBEDDING_FAILED` and the batch **continues**. A 422 is a caller bug and throws rather than
      consuming an error row (FR-039, FR-040, notes.md N4).
- [ ] T053 [US3] `LabDedup --step=embed` — over the database queue, `kind = 'p2_embed'`, recording
      `elapsed_seconds`. **Load the chat model before the embedding model** whenever both are needed
      (FR-041).
- [ ] T054 [US3] **Measure the first full chunk before considering a batch endpoint** (notes.md N3,
      plan.md decision 2). `/embed` takes one text per call, so this is ~24,063 round trips. Record
      the measured rate in `import_runs`; add a batched variant to the service **only if** the number
      demands it — "we will need it later" is not a justification.
- [ ] T055 [P] [US3] `apps/lab/tests/Validation/Dedup/EmbeddingCoverageTest.php` (MirrorValidation)
      — **zero** active questions have a null `stem_embedding`, every populated row shares **one**
      `embedding_config_version`, and the recorded call count is ≈24,063 rather than 57,494 (FR-042,
      SC-006).
- [ ] T056 [P] [US3] `apps/lab/tests/Feature/Dedup/TruncationLoggedTest.php` — no truncated embedding
      exists without both its flag and its error row.

### Candidate generation

- [ ] T057 [US2] `apps/lab/app/Jobs/Dedup/GenerateCandidatePairs.php` — **enforcement 2 of 3**:
      compute `media_relation` **at insertion time** from both fingerprints, independently of the hash
      layer's split. A `different_media` pair is recorded but is permanently ineligible for every
      auto-cluster path (FR-032).
- [ ] T058 [US2] Extend `MediaBoundaryTest` — a `different_media` candidate is present in the table
      **and** excluded from auto-clustering.
- [ ] T059 [US3] `apps/lab/database/migrations/2026_08_28_100800_add_trgm_index_to_source_question_derived.php`
      — `CREATE INDEX ... USING gin (search_text gin_trgm_ops)` via `DB::statement()`, since Blueprint
      has no trigram helper. **Earned here, not assumed in Phase 2** (FR-043, constitution VII). The
      migration must **not** issue `CREATE EXTENSION` — `pg_trgm 1.6` is already present in both
      databases (notes.md N8).
- [ ] T060 [P] [US3] `apps/lab/app/Support/Dedup/TrigramCandidateFinder.php` — `similarity()` above
      `config('lab.dedup.trgm_floor')`, capped per question (FR-044).
- [ ] T061 [P] [US3] `apps/lab/app/Support/Dedup/VectorCandidateFinder.php` — cosine distance (`<=>`)
      over `stem_embedding`, top-K=20 per distinct text, **exact scan**. No HNSW index may be created
      (FR-045).
- [ ] T062 [US3] `GenerateCandidatePairs` — merge both sources into one row per pair, canonicalised so
      `question_a_source_id < question_b_source_id`; a second writer **updates the missing score**
      rather than inserting a duplicate (FR-046, FR-047).
- [ ] T063 [US3] `GenerateCandidatePairs` — record `same_section`, `media_relation` and
      `embedding_config_version_at_generation`; leave `band` NULL until calibration (FR-048).
- [ ] T064 [US3] Every layer **logs what it excluded** — the trigram floor, the top-K cut, the
      media split — so a silent truncation can never look like coverage (FR-049).
- [ ] T065 [US3] `LabDedup --step=candidates`, `kind = 'p2_candidates'`.
- [ ] T066 [P] [US3] `apps/lab/tests/Feature/Dedup/VectorSearchTest.php` — top-K returns the expected
      neighbours in the expected order on a fixture.
- [ ] T067 [P] [US3] `apps/lab/tests/Validation/Dedup/TrigramIndexUsedTest.php` (MirrorValidation) —
      `EXPLAIN` on a trigram similarity query shows the **GIN index**, not a sequential scan.

**Checkpoint**: candidates exist with real, uncalibrated scores — which is what makes the next phase's
stratified sample possible without a threshold.

---

## Phase 6: US4 — Nothing runs over the bank until the gate is measured (Priority: P4)

**Goal**: a stratified sample drawn from real scores **in waves**, labelled against the same seven
values the model uses, then a deterministic threshold sweep that records **pass, fail, or expand** as
a row per wave.

**Independent test**: `--step=eval-sample --wave=1` → every decile and every quota non-empty
**within the wave**; label through the screen; `--step=calibrate` → one run row with both thresholds,
both confidence intervals, an explicit `gate_passed` and an explicit `expansion_decision`.

**Why waves** (operator decision, 2026-08-28): the operator labels this set personally. The stopping
rule is a confidence interval, not a feeling, and it **raises** the gate — FR-060 passed on a point
estimate, FR-144 passes only when the interval clears. See spec Clarifications, session 2026-08-28.

### The sample

- [ ] T068 [US4] `apps/lab/app/Support/Dedup/EvalSetSampler.php` — stratify `duplicate_candidates`
      across similarity deciles from 0.95–1.00 down to 0.50–0.60, **every decile non-empty**, with
      explicit quotas for exact-hash pairs, **`formatting` pairs**, **`orthographic` pairs**,
      `different_media` pairs, **answer-key-conflict pairs**, same-section and cross-section pairs,
      and a **random-pair negative control drawn from outside the candidate set entirely**.
      No threshold is needed to stratify — only the real scores Phase 5 produced (FR-050 – FR-052).
      **The `orthographic` quota is what makes the new fuzzy fold measured rather than assumed** — if
      the labels say it contributes nothing, `fuzzy_fold_enabled` goes false on evidence.
- [ ] T068b [US4] `EvalSetSampler` — **every wave independently satisfies every decile and every
      quota** (FR-050), and excludes pairs already drawn by an earlier wave. This is the property that
      makes stopping at 100 safe: a smaller ruler, never a biased one. A wave that cannot fill a
      stratum **says so and fails loudly** rather than quietly drawing a skewed sample.
- [ ] T069 [US4] `EvalSetSampler` — record the negative control's provenance **at sampling time**, so
      a later re-run of candidate generation cannot retroactively invalidate it (spec Edge Cases).
- [ ] T070 [US4] `LabDedup --step=eval-sample --wave=N` — writes `purpose = 'calibration'`,
      `sample_wave = N`, `label_round = 1`, `human_relation` NULL. Wave size comes from
      `config('lab.dedup.eval_wave_sizes')`; the cumulative total may never exceed **400** (FR-146).

### The verdict endpoint, service side — moved here from Phase 7 (2026-08-28)

**Build T086 – T091 now, before labelling.** They keep their Phase 7 numbers so every existing
cross-reference stays valid; only their position moved. The endpoint takes two questions and returns
a verdict — it needs **no thresholds**, so it never depended on calibration. Building it here is what
makes the optional AI pre-label (T073b) possible at all.

**The band guard did not move.** `VerdictClient`, `LlmBudgetGuard` and the band-gated dispatch
(T092 – T097) stay in Phase 7, where the band exists. **FR-079's counter still reads exactly zero**,
because the pre-label writes `duplicate_eval_pairs.ai_*` and never a candidate row (FR-149).

The eleventh health check (T124) also stays in Phase 10: 10/10 is the **arrival** baseline every
intermediate phase is measured against, and 11/11 is the exit condition.

### The labeling screen (built here because US4's test needs it)

- [ ] T071 [US4] `apps/lab/app/Filament/Resources/DuplicateEvalPairs/` — question A and B side by
      side with options, derived correct answers **and attached images**; the full **seven-value**
      taxonomy plus `same_learning_objective`, `same_correct_answer` and a confidence (FR-053,
      FR-054). The labelling workflow stays **inside the Filament panel** — no separate tool.
- [ ] T072 [US4] The screen's `calibration` and `spot_check` modes, with **keyboard shortcuts** — it
      is used roughly 1,900 times across the project (FR-055, FR-101). The third mode arrives in T099.
- [ ] T073 [US4] `apps/lab/lang/{ar,en}/dedup.php` — the screen's Arabic strings, RTL correct.
      Technical identifiers stay English (constitution VI).
- [ ] T073b [US4] The **optional AI pre-label**, off by default (`ai_prelabel_enabled`). Calls the
      Phase 6 `/verdict` endpoint once per eval pair and stores the result **only** in
      `duplicate_eval_pairs.ai_*` (FR-147). It **never** writes `human_relation`, never writes any
      `llm_*` column on a candidate row, and never enters the positive class. The path is separately
      counted with a ceiling equal to the eval-set size (FR-149).
- [ ] T073c [US4] **Blind-first in the screen** (FR-148) — the suggestion is **not retrievable** until
      `human_relation` is recorded; then it is revealed, `ai_suggestion_shown` is set, and a change of
      mind sets `human_relation_revised`. Separate storage protects the *data*; hiding the suggestion
      protects the *human*, which is the ground truth the whole gate rests on.
- [ ] T073d [US4] The `label_round = 2` labeller works **blind** regardless of the setting (FR-056),
      so the existing inter-rater agreement doubles as the **anchoring measurement** at no extra cost.
- [ ] T074 [OPERATOR] 🔴 **Human gate A — label wave 1: 100 pairs** (1.5–2.5 hrs; a moderator, with a
      trainer for disagreements). **This blocks T076 and everything after it.** A doubled subsample is
      labelled by a second person as `label_round = 2` (FR-056, FR-057).
      **Expand only when FR-144's rule says to.** After each wave, T076b returns `expand`,
      `stop_pass` or `stop_fail`; on `expand`, draw wave N+1 (T070) and label it. The cumulative
      ceiling is **400** and never moves (FR-146). On `stop_fail` — the 95% upper bound of precision
      below 0.90 — **stop labelling** and go to T083; confirming a decisive failure with 300 more
      labels spends the project's scarcest resource on the one path where it changes nothing.

### Calibration and the gate

- [ ] T075 [US4] `apps/lab/app/Support/Dedup/ThresholdCalibrator.php` — a deterministic sweep against
      `human_relation` with `exact_duplicate ∪ semantic_duplicate` as the positive class. **The gate is
      §17's own: precision ≥ 0.90 at recall ≥ 0.70.** The program plan's pointer to "the target in
      §21" is a dangling reference to an unwritten section and is **not** waited on (FR-059, FR-060).
- [ ] T076 [US4] `ThresholdCalibrator` — compute and record inter-rater agreement from the two label
      rounds, and **refuse to calibrate** when agreement is poor: reconciliation and re-labelling come
      first (FR-064). Calibrating against a ruler the labellers disagree about is the failure this
      prevents.
- [ ] T076b [US4] `ThresholdCalibrator` — compute over the **cumulative** labelled set, call
      `WilsonInterval` (T031b) for precision and recall, count the positive class, and apply
      `CalibrationExpansionRule` (T031c) to produce `expand` | `stop_pass` | `stop_fail` (FR-144,
      FR-145). **No threshold is adopted on an `expand`**, and none on a `stop_fail`.
- [ ] T077 [US4] `LabDedup --step=calibrate` — write **one** `duplicate_eval_runs` row **per wave**
      with precision, recall, both 95% CIs, `positive_class_count`, `sample_wave`, both thresholds, an
      explicit `gate_passed` and an explicit `expansion_decision`. A failed gate is **recorded**, and
      there is no path past it (FR-061). Runs are never overwritten; exactly one carries `is_selected`
      (FR-065) — the row that actually settled the decision.
- [ ] T077b [US4] `LabDedup --step=calibrate` — when the decision is `expand`, print what is missing
      (which bound straddles, which stratum is thin, how many positives short) and **stop**, so the
      operator knows what the next 100 labels are buying. At the **400** ceiling the expansion stops
      and the decision is taken on the point estimate, with the interval recorded beside it either way
      (FR-146).
- [ ] T078 [US4] Band assignment — set `band` on every candidate to `exact` / `high` / `uncertain` /
      `low` from the calibrated thresholds (FR-072).
- [ ] T079 [US4] Project the thresholds across the **full** candidate pool into
      `projected_uncertain_band_count`; if it exceeds **8,000**, raise `T_low`, recompute, and write
      **another row** — a logged, deliberate tightening, never a silent adjustment (FR-062, FR-063).
- [ ] T080 [P] [US4] `apps/lab/app/Support/Dedup/EvalSetReportGenerator.php` and
      `CalibrationReportGenerator.php` → `docs/reports/p2-eval-set.md` and `p2-calibration.md`,
      **pure functions over the stored rows**, regenerating identically, never hand-edited (FR-058,
      FR-066).
- [ ] T081 [P] [US4] `apps/lab/tests/Unit/Dedup/ThresholdCalibratorTest.php` — the sweep is
      deterministic on a fixture; precision and recall match hand-computed values.
- [ ] T082 [P] [US4] `apps/lab/tests/Feature/Dedup/CalibrationGateTest.php` — the gate result is
      **always** recorded and a failed gate cannot be bypassed (FR-061); a re-run adds a row rather
      than replacing one; an `expand` decision adopts **no** threshold; and the cumulative labelled
      count never exceeds 400.
- [ ] T082b [P] [US4] `apps/lab/tests/Feature/Dedup/GroundTruthSeparationTest.php` — **SC-032**: no
      `ai_*` value on an evaluation pair ever appears in `human_relation` or in the calibration
      positive class; the suggestion is not retrievable by the screen before `human_relation` is
      recorded; and the pre-label call count never exceeds the eval-set size (FR-147 – FR-149).

### The conditional benchmark — only on a failed gate

- [ ] T083 [OPERATOR] 🔴 **Human gates B and C** — approve pulling `bge-m3` and
      `multilingual-e5-large` (~2–3 GB, a new dependency on this machine), and decide whether to
      switch the embedding contract. **Only reached when T077 records `expansion_decision =
      'stop_fail'`, or `gate_passed = false` at the 400 ceiling.** An `expand` is not a failed gate —
      it is a request for the next 100 labels.
- [ ] T084 [US4] `apps/lab/app/Support/Dedup/EmbedderBenchmark.php` — re-embed **only the eval set's
      questions**, never the bank, for each alternative plus the 512-dimension Matryoshka truncation.
      **Recall@20 decides**: a pair never shortlisted cannot be rescued by any verdict (FR-067,
      FR-068).
- [ ] T085 [US4] `LabDedup --step=benchmark-embedders` — one row per model. If an alternative clears
      the gate and the incumbent does not, **stop and ask**; adoption means an ADR, then T050 and
      Phase 5 **re-run in full** before re-calibrating (FR-069, FR-070). If nothing clears the gate,
      the semantic track becomes a program-level open item and **the gate is not lowered** (FR-071).

**Checkpoint**: the thresholds exist and are backed by human labels **whose sample size the interval
justifies**, or the gate is recorded as failed and the fork in plan.md is taken. Nothing has run over
the whole bank yet, and the operator has spent between 1.5 and 10 labelling hours rather than always
10.

---

## Phase 7: US5 — The model is spent only where nothing cheaper could decide (Priority: P5)

**Goal**: the uncertain band reaches the LLM and nothing else does — proven by a counter that reads
zero — while the high band is clustered with no model call at all.

**Independent test**: candidates with a verdict and `band <> 'uncertain'` count **exactly 0**; the
spot-check sample is 5% (±1) of the clusters the auto step created.

**T086–T095 (the verdict) and T096–T099 (the high band) read disjoint bands and may be built in
parallel.**

### The verdict endpoint (contracts/verdict.md) — ⬆️ **built in Phase 6**, listed here for the contract

**T086 – T091 moved to Phase 6 on 2026-08-28** so the optional AI pre-label has an endpoint to call.
They keep these numbers. Their content is unchanged and is repeated here rather than duplicated —
build them in Phase 6 and tick them there.

- [ ] T086 [P] [US5] `apps/ai-service/app/prompts/duplicate_verdict_v1.md` — the versioned prompt.
      Question and option text is a **delimited data field**, and the system prompt states that
      instructions inside it are ignored (FR-076, constitution III).
- [ ] T087 [US2] `duplicate_verdict_v1.md` — **enforcement 3 of 3**: the prompt states Decision 4's
      rule explicitly, *different attached images means not a duplicate* (FR-033).
- [ ] T088 [US5] `apps/ai-service/app/verdict.py` — call Ollama `/api/generate` against
      `settings.chat_model`, following `health.py::probe_chat`'s call shape. Ollama's `format`
      constrains generation to the JSON Schema **and** pydantic validates the parsed response before
      it is returned — **both, always** (FR-074, contracts/verdict.md §5).
- [ ] T089 [US5] `apps/ai-service/app/main.py` — `POST /verdict`, the sixth endpoint, returning §17's
      seven fields verbatim plus the echoed `prompt_version` and the `model` that produced it
      (FR-073). An unknown `prompt_version` is a **422, never a fallback to the newest**.
- [ ] T090 [US5] `main.py` — the `invalid_verdict` **502** error code, distinct from 503
      `ollama_unreachable`, so the caller can tell "the model returned nonsense" from "the runtime is
      down" and decide whether retrying is worthwhile (contracts/verdict.md §4).
- [ ] T091 [P] [US5] `apps/ai-service/tests/test_verdict_contract.py` — schema validation, the
      seven-value enum, **`probable_duplicate` rejected if returned**, a malformed-response rejection,
      an instruction-shaped stem that must still return a verdict, and a synthetic
      identical-text/different-image pair that must **not** come back as `exact_duplicate` (FR-082).

### The Laravel side and the rationing

- [ ] T092 [US5] `apps/lab/app/Support/Dedup/LlmBudgetGuard.php` — `assertInBand()` **throws** for any
      pair outside `band = 'uncertain'`, and runs before **every** dispatch (FR-078).
- [ ] T093 [US5] `apps/lab/app/Support/AiService/VerdictClient.php` and
      `apps/lab/app/Jobs/Dedup/RequestLlmVerdict.php` — dispatched only where `band = 'uncertain'`, no
      verdict exists, **and the pair is not terminally failed** (FR-077). Each of the seven fields
      lands in its own column (FR-081).
- [ ] T094 [US5] Verdict failure handling — a 502/503 records `VERDICT_FAILED`, increments
      `verdict_attempts` and stores `verdict_last_error`, and the batch continues. Past
      `config('lab.dedup.verdict_max_attempts')` the pair is marked `verdict_failed = true` and is
      **never re-dispatched**; a 422 throws rather than consuming a retry (FR-122 – FR-124). **This is
      what stops a permanently failing pair from re-spending the budget on every run.**
- [ ] T095 [US5] `LabDedup --step=verdict` — over the database queue, overnight, targeting ≤5,000
      pairs ≈ 6 hours, `kind = 'p2_verdict'`, resumable so an interrupted batch re-judges nothing and
      skips nothing (FR-080).
- [ ] T096 [P] [US5] `apps/lab/tests/Feature/Dedup/LlmBandProofTest.php` — the count of candidates
      with a verdict whose band is not `uncertain` is **0**, and the guard throws when a
      non-uncertain pair is offered (FR-079). **Not accepted at any value above zero.**
- [ ] T097 [P] [US5] `apps/lab/tests/Feature/Dedup/VerdictFailureTest.php` — a pair failing every
      attempt becomes terminally failed, is not re-dispatched on a second run, and is **never
      auto-clustered or auto-escalated** (FR-126). An absent verdict is not a verdict.

### The high band

- [ ] T098 [US5] `apps/lab/app/Jobs/Dedup/AutoClusterHighBand.php` — for `band = 'high'` **AND**
      `media_relation <> 'different_media'`, form clusters by **transitive closure** (T030) with
      `relation_type = 'probable_duplicate'`, `status = 'auto'`, `source_layer = 'high_band_auto'` and
      **zero model calls** (FR-083, FR-116).
- [ ] T099 [US5] `AutoClusterHighBand` — apply the closure size guard, and **log the component-size
      distribution before the config value is fixed** (plan.md decision 6). The guard applies to
      pair-derived closure **only**: a legitimate **538-member** hash group exists, and guarding hash
      clusters would flag the bank's largest real duplicate group as a defect (FR-119).
- [ ] T100 [US5] A stratified **5% (±1)** sample of the clusters that step created, written with
      `purpose = 'spot_check'` and worked through T072's confirm/reject mode (FR-084).
- [ ] T101 [US5] A rejected spot-check sets `status = 'rejected'` and writes a review row. **The
      cluster is not deleted** — it is the only record that the auto path made a mistake (FR-085).
- [ ] T102 [US5] `LabDedup --step=auto-cluster`.
- [ ] T103 [US2] Extend `MediaBoundaryTest` — no cluster with `source_layer = 'high_band_auto'`
      contains a `different_media` pair, whatever its similarity (FR-030).
- [ ] T104 [P] [US5] `apps/lab/tests/Feature/Dedup/ClusterMembershipTest.php` — no question appears in
      two clusters of the **same** `source_layer`; clusters of different layers **may** overlap
      (FR-120, FR-121).

**Checkpoint**: the model has been spent only inside the band, and the counter proves it.

---

## Phase 8: US6 — A key reaching 4,000 students is worked before one reaching two (Priority: P6)

**Goal**: **928** answer-key conflicts (notes.md N2, corrected from the plan's ~1,125) become a
**standing, tiered** backlog ranked by the students each error actually reached, and a regenerable
report carries the top of it to the people who can fix it.

**Independent test**: a synthetic conflicting cluster with known `n` values produces a report count
equal to the raw SQL sum exactly.

**The backlog blocks nothing** (operator decision 2026-08-28, FR-151). The operator is a solo
developer with no trainer time to commit, and the measurement makes that workable rather than a
concession: the **top 100 of the 928 groups carry 50.4% of the backlog's 269,153 total
affected-student exposure**, the top 200 carry 67.3%, and p50/p75/p90 are 141/282/686 students
(notes.md N10). Ranking is the difference between 3–8 hours and 31–77 for the same half of the
benefit. **Zero groups have zero impact**, so no zero-impact tier is built.

- [ ] T105 [US6] Any `conflicting_duplicate` verdict — from the model **or** a human — creates a
      cluster with `status = 'urgent_review'` (FR-087).
- [ ] T106 [US6] `apps/lab/app/Support/Dedup/AffectedStudentCounter.php` — a deterministic SQL sum of
      `source_item_stats.n` at `scope = 'active'` across a cluster's members, each counted **exactly
      once** (FR-088, FR-120). **The model never computes this number** (constitution IV).
- [ ] T107 [US6] A question whose `answer_key_state` is not `single_correct` is **never
      auto-escalated** as a conflict — it is flagged for a human instead (FR-093). The literal values
      are `single_correct` / `broken_no_key` / `multi_key`; the project plan's `single_key` does not
      exist (notes.md N5).
- [ ] T108 [US2] A `different_media` pair is **never** auto-escalated as a conflict, because a
      different diagram is a different question (FR-030).
- [ ] T108b [US6] `apps/lab/app/Support/Dedup/ConflictTierAssigner.php` — assign `priority_tier` by
      **deterministic SQL** from the measured `affected_student_count` distribution at
      `config('lab.dedup.conflict_tier_percentiles')` (`0.50 / 0.75 / 0.90`), and **log the computed
      cut values** with the run — they are a measurement of the current population, not constants
      (FR-150). Measured 2026-08-28: p50 = 141, p75 = 282, p90 = 686, max = 6,966. **No model touches
      this column.**
- [ ] T109 [US6] `apps/lab/app/Support/Dedup/ConflictReportGenerator.php` → the top N clusters by
      student impact, each with both questions, both answer keys, the count, **its tier**, and the
      trainer's decision where one exists. It also states the **measured concentration** — the share
      of total affected-student exposure the reported top N accounts for — so partial coverage reads
      as *impact covered*, not as *rows remaining* (FR-091). Generated from stored rows and
      **regenerating identically**.
- [ ] T110 [US6] `LabDedup --step=conflict-report` → `docs/reports/p2-conflicting-duplicates.md`.
      **This is where the Lab stops**: a human carries the correction into the Production admin, and
      no phase opens a write path to `injazedu` (FR-092).
- [ ] T111 [OPERATOR] ⚪ **Configuration, not a gate** (reclassified 2026-08-28 — it was human gate F).
      The operator's decision is already recorded: **no trainer commitment**; the backlog is a
      **standing queue** worked by rank at `daily_review_cap = 10` (T001). **This blocks nothing**
      (FR-151) — set the value if 10 is wrong and move on.
      *Why it stopped being a gate*: it required committing 31–77 trainer hours before Phase 9 could
      ship, yet nothing downstream depended on the backlog being **worked** — the console ships
      whether or not one item has been reviewed. The constitution's gate policy names that shape
      exactly: *"any check whose only purpose is satisfying another document."* The engineering
      properties it looked like it protected are held by FR-088 – FR-092 and FR-150 – FR-154, which
      are **tested**. Nothing was weakened; a scheduling promise was removed.
- [ ] T111b [US6] The **on-demand AI triage** action on a single conflict cluster (FR-153, FR-154):
      human-initiated, one cluster per call, written **only** to the five `ai_triage_*` columns and
      labelled as a recommendation with its confidence and prompt version. It **never** writes
      `affected_student_count`, `priority_tier`, `status` or `relation_type`. **No batch pass over the
      backlog** — most of the 928 come from the hash layer and carry no verdict, so a batch pass would
      be a new unbounded model path outside the uncertain band. Where a verdict already exists, its
      `llm_recommended_action` / `llm_confidence` are **displayed, not recomputed**.
- [ ] T112 [P] [US6] `apps/lab/tests/Feature/Dedup/ConflictReportTest.php` — affected-student counts
      are reproducible from raw rows and the report regenerates byte-identically.
- [ ] T112b [P] [US6] `apps/lab/tests/Feature/Dedup/TriageDeterminismTest.php` — **SC-033**: tiers
      match a hand-computed percentile split on a fixture and the cut values are logged; AI triage
      output leaves `affected_student_count`, `priority_tier` and `status` unchanged; and a conflict
      never leaves `urgent_review` without a **human** `duplicate_reviews` row.
- [ ] T112c [P] [US6] `apps/lab/tests/Feature/Dedup/BacklogBlocksNothingTest.php` — **SC-034**: with
      **zero** conflicts reviewed, the conflict report generates, the console renders and the full
      pipeline completes. No gate, report or criterion reads the backlog's remaining size as a
      condition (FR-151).

**Checkpoint**: the program's most urgent deliverable is a ranked, **tiered**, countable, regenerable
artefact that **nothing waits on**.

---

## Phase 9: US7 — A human decision sits beside the verdict, never on top of it (Priority: P7)

**Goal**: an Arabic console where a moderator settles a pair in seconds, and every decision is stored,
attributed and timestamped — with the AI verdict untouched beside it.

**Independent test**: a Feature test — not a manual click-through — records a decision and asserts the
review row's author, timestamp and status transition alongside an unchanged verdict.

- [ ] T113 [US7] `apps/lab/app/Filament/Resources/DuplicateClusters/` — Arabic with correct RTL; both
      questions side by side with options, derived correct answers and **attached images**; the
      similarity scores; the AI verdict with its confidence (FR-094).
- [ ] T114 [US7] The list is ordered by `status = 'urgent_review'` first, then **`priority_tier`**,
      then `affected_student_count` **descending** — **never by `id`** (FR-089) — and displays the
      **full** remaining backlog size **per tier** so a partly worked list never looks finished and
      the operator can see which tier the cap is being spent on (FR-090). A tier filter is the
      practical triage surface: `tier_1_critical` alone is ~93 items carrying ~49% of the exposure.
- [ ] T115 [US7] The five actions `[نفسه] [تنويعة صحيحة] [ليس تكرارًا] [تعارض!] [تخطٍّ]`, each writing
      a review row with the decision, reviewer, timestamp and the status transition (FR-096).
- [ ] T116 [US7] The status/relation transition map (FR-128): `same`→`confirmed`;
      `valid_variant`→`confirmed` + `same_objective_variant`; `not_duplicate`→`rejected`;
      `conflict`→`urgent_review` + `conflicting_duplicate`; `skip`→`skipped`. A decision on a cluster
      already at `urgent_review` sets **`resolved`** — the trainer's arbitration (FR-129).
- [ ] T117 [US7] A decision may change the cluster's `relation_type` but **never** an `llm_*` column
      on the candidate row (FR-097, FR-130). The verdict and the post-review relation stay queryable
      side by side (FR-131).
- [ ] T118 [US7] AI output is labelled as a **recommendation** with its confidence and prompt version,
      visually distinct from measured values (FR-095, constitution VI).
- [ ] T119 [US7] `apps/lab/app/Support/Dedup/P3StatsLookup.php` — reports unavailability, and the
      statistics row **does not render**. No placeholder dashes, and P3's schema is not modelled before
      P3 exists (FR-098).
- [ ] T120 [US7] The `uncertain_review` mode — populated from verdicts flagged `review_required`
      (spec Assumptions) — completing T072's three modes (FR-101).
- [ ] T121 [US7] The **soft** daily cap on the ongoing queues, default **10** (FR-152): at the cap the
      console says so and shows what remains **per tier**, and still serves the next item — **it never
      blocks**. Today's count is shown beside it. The calibration set is **exempt in every wave**
      (FR-135 – FR-138).
- [ ] T122 [US7] Every displayed statistic carries its sample size and `snapshot_taken_at`, and every
      count reaches the underlying questions in one click (FR-100, constitution VI).
- [ ] T123 [P] [US7] `apps/lab/tests/Feature/Dedup/HumanOverrideTest.php` — a decision is stored,
      attributed and timestamped; each of the five actions produces exactly the mapped status and
      relation; the verdict columns are unchanged.

**Checkpoint**: all seven stories are independently functional.

---

## Phase 10: Wrap-up, Guards, and the Acceptance Run

- [ ] T124 [P] `apps/lab/app/Support/Health/VerdictEndpointCheck.php` — the **eleventh** check,
      `number() === 11`, following `ChatModelCheck`'s shape. It asserts `/verdict` answers with a
      schema-valid verdict on the fixed identical-text/different-image pair, so the health check and
      the contract test assert the same rule from two directions.
- [ ] T125 [P] `apps/lab/tests/Feature/Dedup/NoDeletionTest.php` — the `source_questions` count is
      **identical** before and after a full run. Not accepted at any other value, ever.
- [ ] T126 [P] `apps/lab/tests/Feature/Dedup/PipelineIdempotencyTest.php` — a second full run produces
      **zero** new clusters and **zero** new candidates (FR-106).
- [ ] T127 [P] `apps/lab/tests/Feature/Dedup/ResumeTest.php` — every long step interrupted mid-run and
      resumed loses no row and duplicates none (FR-107).
- [ ] T128 Confirm the thirteen named suites from the spec all exist and are green:
      `composer test` (Unit + Feature on `injazedu_lab_test`) and `composer test:mirror`
      (MirrorValidation, read-only on the real mirror).
- [ ] T129 `README.md` — the P2 section: `lab:dedup`'s eleven steps and the two consoles.
- [ ] T130 `apps/lab/.env.example` — every new key listed **with no value**.
- [ ] T131 `CLAUDE.md` and `AGENTS.md` — P2's measured facts, **byte-identical**; verify with
      `diff CLAUDE.md AGENTS.md`.
- [ ] T132 Correct the program plan's §13.3 budget table with an `**Updated**` note once Phase 8 has
      produced a real per-item time — the 30–60 hour program-wide figure is now known to be wrong for
      this project alone (spec Open Item 2).
- [ ] T133 **The acceptance run**: `php artisan test`, then `php artisan lab:health` (**11/11, exit
      0**), then the full `lab:dedup` pipeline **twice**, asserting the second run changes nothing
      (FR-111).
- [ ] T134 Confirm **no** new runbook, ADR, acceptance record or handover document was created. The
      one permitted exception is an embedder-switch ADR, and only if T085 forced that decision
      (FR-115).

---

## Dependencies

```text
Setup (T001–T003, +T001b/c)
   │
Foundational (T004–T034, +T024b/T025b/T025c/T031b–d)
   │                              A: migrations+models ∥ B: the pure rules
US1 (T035–T044, +T040b)  🎯 MVP   derive-text → hash-cluster (+ orthographic candidates)
   │
US2 (T045–T048)                   fingerprint + enforcement 1 of 3
   │
US3 (T049–T067)                   embed → candidates  (+ enforcement 2 of 3, T057)
   │
US4 (T068–T085, +T068b/T073b–d/T076b/T077b/T082b)
   │                              sample wave ─┐
   │                              T086–T091 (endpoint, moved here) 
   │                              🔴 T074 labels wave ─► calibrate ─► expansion rule
   │                                        │                              │
   │                                        └──── expand ◄─────────────────┘  (≤4 cycles, ceiling 400)
   │                                        └─ stop_fail loops to T083, then T050 and Phase 5
   ├──────────────┬───────────────
US5 dispatch      US5 high band    T092–T097 ∥ T098–T104  (+ enforcement 3 of 3, T087)
   └──────────────┴───────────────
   │
US6 (T105–T112, +T108b/T111b/T112b/T112c)   tiering + ranking + report   (nothing blocks on it)
   │
US7 (T113–T123)                   the console
   │
Wrap-up (T124–T134)
```

### The blocking human gates — now one

| Task | Gate | Blocks |
|---|---|---|
| **T003** | Confirm the fold rules **and both label alphabets** with a trainer | T036 running at scale |
| **T074** | 🔴 Label **wave 1 — 100 pairs** (1.5–2.5 hrs), expanding only on `expand` | T075 onward — **the whole downstream pipeline** |
| ~~T111~~ | ⚪ **No longer a gate** — reclassified to configuration on 2026-08-28 (FR-151) | **Nothing** |

T083 (approve alternative embedders) is conditional and reached only on a `stop_fail`, or on a
failed gate at the 400 ceiling.

### Parallel opportunities

- **T004–T011** — the eight migrations, except T008 and T009 which need T007's table.
- **T013–T020** — all eight models, different files.
- **T024/T024b/T026/T028/T030/T031b/T031c** — the rule classes are independent pure functions, and
  their unit tests follow each immediately.
- **Phase 2's two halves** — the schema (T004–T022) and the rules (T024–T031d) share nothing.
- **T092–T097 ∥ T098–T104** — the verdict dispatch and the high band read disjoint bands of
  `duplicate_candidates` and never write the same row. (T086–T091, the stateless endpoint, are built
  earlier in Phase 6.)
- **T124–T127** — four independent test files.

---

## What is deliberately NOT here

```text
A task to delete, merge, or edit any question            forbidden program-wide
A task writing anything to injazedu MySQL                the Lab never writes to the source
A task altering any P1 mirror table                      Decision 1: P2 adds tables beside it
A task creating an HNSW index                            constitution VII: ~70 MB exact-scans fine
A task creating a PostgreSQL extension                   both already present in both databases (N8)
A task editing NoPiiInLabSchemaTest                      it already covers the eight tables (N7)
A task computing r_pbis, p-value, or distractors         P3
A task modelling P3's item_statistics schema             Decision 6: the row is omitted, not faked
A task building a passage-excerpt embedding pipeline     Decision 3: zero stimulus rows
A task refreshing the snapshot or gating on its age      cancelled program-wide
A task on a backup, dump, or restore drill               cancelled program-wide
A task with a memory number as its criterion             cancelled (constitution VII)
A new runbook, ADR, or handover document                 constitution documentation policy
A task making the fuzzy fold reach any strict hash        FR-141; constitution IV v2.5.0 permits it
                                                          ONLY as a recall aid
A task auto-clustering an orthographic-only match         FR-142: it routes to the high band
A batch AI triage pass over the conflict backlog          FR-154: on demand, one cluster, human-started
A task blocking anything on the backlog's size            FR-151: it is a standing queue
A task writing an AI value into human_relation,           FR-147, FR-153: ground truth and measured
  affected_student_count, priority_tier, or status         numbers are never model output
```

---

## Summary

| | |
|---|---|
| | Tasks |
|---|---|
| **Total** | **154** (T001–T134, plus 20 suffixed tasks added 2026-08-28) |
| Setup | 5 (T001, T001b, T001c, T002, T003) |
| Foundational | 37 (T004–T034 + T024b, T025b, T025c, T031b–T031d) |
| **US1 — zero-cost detection** 🎯 **MVP** | 11 (+T040b, the orthographic candidate source) |
| US2 — the media boundary | **9** — 4 in its own phase, 5 embedded where the code lives (T057, T058, T087, T103, T108) |
| US3 — embeddings and candidates | 17 |
| US4 — the eval set and the gate | 23 (2 conditional on a failed gate; +6 for waves and the pre-label) |
| US5 — the rationed verdict and high band | 17 (T086–T091 built in Phase 6, dispatch stays in Phase 7) |
| US6 — the conflict backlog | 10 (+T108b tiering, T111b triage, T112b/c guards) |
| US7 — the review console | 11 |
| Wrap-up | 11 |
| **Operator tasks** | 4 — T003, T074, T083 (conditional), and T111 (**configuration, not a gate**) |
| **Parallel markers** | 51 |

**Numbering.** The 20 tasks added on 2026-08-28 carry **suffixed ids** (`T001b`, `T040b`, …) rather
than renumbering T001–T134. Renumbering would invalidate every cross-reference in spec.md, plan.md
and this file — the same reasoning the spec applies to its own FRs, which are appended from the end.

**MVP scope**: Phases 1–3 (T001–T044). That delivers the Arabic normalizer, both hash layers and the
first clustering — **60.3% of the bank resolved with no model, no threshold, and no human**. It is
also the only slice that survives every downstream go/no-go failure, which is why it is first.

**What the three operator decisions of 2026-08-28 changed here**, in one place:

```text
T003          now also confirms the option-label alphabets for BOTH scripts and the fuzzy fold map
T001b/c       new config keys: alphabets, fold map, wave sizes, CI level, tier percentiles, cap = 10
T024b         OptionLabelStripper — leading-anchored, both scripts
T025b         the test that keeps constitution IV v2.5.0's carve-out honest
T040b         orthographic matches become CANDIDATES, never clusters
T068b         every wave independently stratified — why stopping at 100 is safe, not sloppy
T073b–d       AI pre-label: separate storage, blind-first, blind second labeller
T074          wave 1 = 100 pairs (1.5–2.5 hrs), not 400 (5–10 hrs)
T076b/T077b   the Wilson stopping rule — a STRONGER gate than the old point estimate
T082b         proves no AI value ever becomes ground truth
T108b         deterministic percentile tiers, cut values logged
T111          reclassified from a 🔴 blocking gate to configuration — it blocks nothing
T111b         AI triage: on demand, one cluster, advisory only
T112b/c       prove the tiers stay deterministic and the backlog blocks nothing
```
