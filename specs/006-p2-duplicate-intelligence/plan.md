# Implementation Plan: P2 — Arabic Normalization & Duplicate Intelligence

**Branch**: `p2/duplicate-intelligence` · **Date**: 2026-08-28 · **Spec**: [spec.md](./spec.md)
**Phase 0 findings**: [notes.md](./notes.md) — ten, four of which change what gets built
**Design**: [data-model.md](./data-model.md) · [contracts/verdict.md](./contracts/verdict.md)
**Project plan**: `docs/plans/project/2/p2-duplicate-intelligence.md` (v1.0) — eleven phases,
delivered as this one spec (§7.1). Governed by §17 of the program plan.

## Summary

Find the bank's real duplication, prove the cheap layers did most of the work, spend the model only
where nothing cheaper could decide, and hand a ranked backlog of answer-key conflicts to a human —
without deleting one question or writing one row to Production. One command (`lab:dedup`, eleven
steps), eight Lab-owned tables beside the mirror, one new service endpoint, and one Arabic console
that serves three review modes.

Five things shape the approach, and four of them come from measurement rather than from the plan:

1. **The embedding budget was computed off the wrong key (N1).** The plan and the spec both said "one
   representative per `question_with_options_hash`, ~11,416 calls". Those are different grains:
   11,416 is the distinct *raw text* count, the stem's key yields **11,094**, and the options key
   yields **12,969** — *more* than 11,416, because text-plus-options is the more specific key. The
   honest figure is **~24,063 calls against 57,494 undeduplicated, a 58% saving**. The cascade's
   argument is untouched; only the arithmetic was wrong. Corrected in the spec before Phase 4 rather
   than discovered during it.
2. **The plan's conflict backlog was ~18% too large (N2).** Its group tallies did not sum
   (4,602 + 136 ≠ 4,689). Re-measured they do — **4,558 + 131 = 4,689** — and the non-image
   conflicting backlog is **928**, not ~1,125. That is **31–77 trainer hours**, still far past
   §13.3's 30–60 hours program-wide, so Decision 5's ranking remains the whole design. The image
   effect survives at **73.3% vs 20.4% — 3.6×** — so Decision 4 stands on measurement.
3. **A legitimate 538-member duplicate group exists.** Median group size is 3 and p99 is 15, but one
   group holds 538 questions with identical text. Hash equality is transitive, so that is a true
   finding, not a runaway merge — which fixes the scope of the clarified size guard: it applies to
   **pair-derived closure only**, never to hash clusters.
4. **Nothing here is expensive to reverse.** Unlike P1, whose mirror schema Principle I names
   explicitly, every P2 artefact is a new sibling table, a job class, a service endpoint, or a
   Filament resource — all reversible by an edit. That is Decision 1's procedural point, and it is
   why this plan has **no open questions**.
5. **Three operator decisions on 2026-08-28 reshaped the human-facing half (N10).** They are recorded
   in the spec's second clarification session and are not re-litigated here:
   - **Normalization.** The strict path is unchanged and `ة → ه` stays out of every hash. A named
     **recall-only** fold tolerates the `ة`/`ه` typo and can never produce an `exact_duplicate`; this
     required narrowing constitution IV, which the operator approved (**v2.5.0**). Option labels are
     stripped in **both scripts**, because 3,604 options carry a Latin label against 1,200 Arabic and
     9.9% of the bank has no Arabic character at all. Case folding, missing from FR-011 while N1's
     budget assumed it, is now explicit.
   - **Calibration is progressive.** Waves of 100 up to the same 400 ceiling, expanded only when a
     **95% Wilson interval** says the sample cannot decide. This *raises* the gate — it must now clear
     an interval, not a point estimate — while letting the operator commit 1.5 hours before 10. AI may
     pre-label as a separate, hidden-until-committed suggestion; the human label stays the sole ground
     truth.
   - **The conflict backlog blocks nothing.** Human gate F was a **procedural gate** by the
     constitution's own definition and is reclassified to configuration. The backlog ships as a
     standing, tiered queue at `daily_review_cap = 10`. The measurement carries the argument: the top
     100 of 928 groups hold **50.4% of the 269,153 total affected-student exposure**, so ten working
     days covers half the measured harm.

## Technical Context

**Language/Version**: PHP **8.4.2** at `/opt/homebrew/opt/php@8.4/bin/php`, never linked · Python
3.13 for the service · SQL (PostgreSQL 17 only — **P2 issues no MySQL statement at all**)
**Primary dependencies**: none new. Laravel 13.26.1, Filament 5.7.6, FastAPI + pydantic already
present. **No `pgvector/pgvector-php`** — `$table->vector($col, 768)` is native and proven by
`lab_vector_probes`. **No new model** unless the gate fails and the operator approves one
**Storage**: PostgreSQL 17 + pgvector 0.8.6 + pg_trgm 1.6 on `127.0.0.1:5433` — **both extensions
verified present in `injazedu_lab` *and* `injazedu_lab_test` by querying `pg_extension`** (N8).
+8 tables on today's ~673 MB; vectors ≈ **70 MB** (24,063 × 768 × 4 bytes × 2 columns, shared across
hash groups)
**Testing**: `composer test` (Unit + Feature, against the disposable `injazedu_lab_test`) ·
`composer test:mirror` (MirrorValidation, read-only against the real mirror) ·
`php artisan lab:health` as the acceptance instrument — **10/10 on arrival, 11/11 on exit**
**Target platform**: macOS 26.5.2 (Darwin 25.5.0), Apple M1 Pro, 16 GB
**Scale**: 28,747 active questions (29,142 with soft-deleted) · 11,094 distinct normalized stems ·
12,969 distinct stem+options · 4,689 raw duplicate groups holding 22,020 questions · 5,582 image rows
over 5,578 questions · ~114,000 candidate pairs (upper bound) · ≤5,000 uncertain-band verdicts
**Constraints**: zero rows written to `injazedu` · zero MySQL reads · no mirror column altered · no
question deleted · no HNSW index · no LLM call outside `band = 'uncertain'`, proven by a counter that
must read zero · no gate on the snapshot's age or on a memory number

## Checks Before Building

- [x] **Nothing decided that needed approval** — five questions were put to the operator on
      2026-08-27 and answered (spec `## Clarifications`). Everything else was ordinary architecture,
      decided with judgement and stated under *Decisions Taken Under Principle I* below. **No open
      question remains**, because Decision 1 (new sibling tables, never `ALTER` on the mirror) keeps
      this whole feature inside Principle I's "decide with judgement" half. Two items still need the
      **operator**, and both are already recorded as human gates, not as plan questions: approving an
      alternative embedder (only on a failed gate) and committing trainer time to the backlog.
- [x] **Read-only toward InjazEdu MySQL** — stronger than read-only here: **P2 opens no MySQL
      connection**. It reads the Lab mirror and writes Lab tables. Neither allowlist in
      `config/lab.php` changes, `SourceReader` is not involved, and the three write-blocking layers
      are re-asserted by the existing tests rather than re-implemented.
- [x] **No PII into the Lab** — none of the eight tables can hold a personal column, and
      `NoPiiInLabSchemaTest` proves it **with no edit and no exemption** (N7): it scans
      `information_schema` across every non-framework table, and `reviewer_id` / `labelled_by` are not
      on its forbidden list, which names `user_id`, not `*_id`. **Spec Open Item 5 closes.**
- [x] **Laravel owns migrations** — nine (eight tables plus the earned trigram index), all in Laravel
      (ADR-013). The service stays stateless and returns JSON. Every metric — `affected_student_count`,
      precision, recall, the thresholds — is computed in SQL or PHP; **the model computes no number**.
      The one AI task returns schema-constrained *and* pydantic-validated structured output, with a
      versioned prompt (contracts/verdict.md §5).
- [x] **Tests are the targeted kind** — the thirteen named suites, placed across the three existing
      testsuites (N9). Pure functions get units; the boundary rules, the band proof and the status map
      get feature tests on fixtures; whole-bank coverage claims go to `MirrorValidation`, read-only.
      No coverage target, no UI wiring tests.
- [x] **Fits the budget; cheap layers before the LLM** — this is the feature the constitution's
      cascade rule was written for. Layers 0–1 cost nothing and resolve 60.3% of the bank; Layer 3
      is ~70 MB exact-scanned; the LLM sees ≤5,000 pairs and a guard throws before any call outside
      the band. Only one model is resident at a time, and the chat model is loaded **before** the
      embedding model whenever both are needed.

## One Fork Worth Planning For

| Condition | Decision |
|---|---|
| **The gate fails decisively** — the 95% Wilson **upper** bound of precision is below 0.90 at any wave, or the point estimate fails at the 400 ceiling (end of Group G) | **Stop and ask before re-embedding anything.** Pulling `bge-m3` / `multilingual-e5-large` is a new dependency (human gate B) and switching the embedding contract is Principle I explicitly (gate C). Approval means an ADR, then Groups D and E **re-run in full** before re-calibration. If nothing clears the gate, the semantic track becomes a program-level open item and **the gate is not lowered** — Layers 0–2 still ship and still resolve 60.3% of the bank. |
| **The gate is ambiguous** — the interval straddles a threshold, a stratum is unfilled, or fewer than 30 positives (FR-144) | **Draw the next wave and re-calibrate on the cumulative set.** This is the ordinary path, not the fork: it writes an `expand` row and costs another ~1.5–2.5 labelling hours. It reaches the fork above only at the 400 ceiling. |

This is the one result that can invalidate half the plan, and it is known before Groups H and I run
over the whole bank. Everything else the pipeline returns is recorded and read.

## Project Structure

`✅` created here · `✏️` amended · `📁` untouched

```text
injazedu-ai-lab/
├── apps/lab/
│   ├── app/Console/Commands/
│   │   ├── LabDedup.php                        ✅ 11 steps, --step --resume --chunk --count
│   │   ├── LabImport.php · LabProfile.php      📁 the shape LabDedup follows
│   │   └── LabHealth.php                       📁 the acceptance instrument, unchanged
│   ├── app/Support/
│   │   ├── Dedup/
│   │   │   ├── ArabicNormalizer.php            ✅ clean() + search() + fuzzy(), two versions
│   │   │   ├── OptionLabelStripper.php         ✅ both alphabets, leading-anchored only
│   │   │   ├── OptionsNormalizer.php           ✅ consumes P1's option_index, never re-derives it
│   │   │   ├── DuplicateHasher.php             ✅ two hashes + mediaFingerprint(ordered list)
│   │   │   ├── ClusterClosure.php              ✅ union-find, pure, order-independent
│   │   │   ├── TrigramCandidateFinder.php      ✅
│   │   │   ├── VectorCandidateFinder.php       ✅ top-K=20, exact scan
│   │   │   ├── EvalSetSampler.php              ✅ per-wave deciles + seven quotas + neg. control
│   │   │   ├── ThresholdCalibrator.php         ✅ deterministic sweep, records pass AND fail
│   │   │   ├── WilsonInterval.php              ✅ pure; the stopping rule's arithmetic
│   │   │   ├── CalibrationExpansionRule.php    ✅ pure; expand | stop_pass | stop_fail
│   │   │   ├── ConflictTierAssigner.php        ✅ SQL percentiles, cut values logged
│   │   │   ├── EmbedderBenchmark.php           ✅ conditional — eval set only, never the bank
│   │   │   ├── LlmBudgetGuard.php              ✅ throws before any out-of-band dispatch
│   │   │   ├── AffectedStudentCounter.php      ✅ SQL over source_item_stats.n
│   │   │   ├── P3StatsLookup.php               ✅ reports unavailability; the row is omitted
│   │   │   └── *ReportGenerator.php            ✅ eval-set · calibration · conflicts
│   │   ├── AiService/
│   │   │   ├── EmbeddingClient.php             ✅ sends RAW text — the service owns the prefix
│   │   │   └── VerdictClient.php               ✅ bounded retry → terminal verdict_failed
│   │   ├── Health/VerdictEndpointCheck.php     ✅ the eleventh check, number() === 11
│   │   └── Import/ImportErrorCode.php          ✏️ +3 cases, +severity/description arms
│   ├── app/Jobs/Dedup/                         ✅ DeriveQuestionTextLayers · DeriveSectionTextLayers
│   │                                              ClusterExactHashMatches · EmbedQuestions
│   │                                              GenerateCandidatePairs · RequestLlmVerdict
│   │                                              AutoClusterHighBand
│   ├── app/Models/                             ✅ 8 models, relations wired through source_id
│   ├── app/Filament/Resources/
│   │   ├── DuplicateEvalPairs/                 ✅ the labeling screen, 3 modes, keyboard shortcuts
│   │   └── DuplicateClusters/                  ✅ the review console, 5 actions, ranked backlog
│   ├── database/migrations/                    ✅ 8 tables + 1 earned trgm index
│   ├── config/lab.php                          ✏️ dedup block: guard size, daily cap (10), top-K,
│   │                                               floors, fold map, label alphabets, wave sizes,
│   │                                               CI level, positive floor, tier percentiles
│   ├── lang/{ar,en}/                           ✏️ the console's strings
│   └── tests/
│       ├── Unit/Dedup/                         ✅ normalizer · hasher · options · closure · calibrator
│       ├── Feature/Dedup/                      ✅ schema+FK · media boundary · band proof
│       │                                          human override + status map · idempotency
│       ├── Feature/NoPiiInLabSchemaTest.php    📁 UNCHANGED — already covers the eight (N7)
│       └── Validation/Dedup/                   ✅ whole-bank coverage, read-only on the real mirror
├── apps/ai-service/
│   ├── app/verdict.py                          ✅ Ollama /api/generate + format + pydantic
│   ├── app/main.py                             ✏️ POST /verdict, the sixth endpoint
│   ├── app/prompts/duplicate_verdict_v1.md     ✅ versioned; v2 never overwrites v1
│   └── tests/test_verdict_contract.py          ✅ schema · enum · malformed · image case · injection
├── docs/reports/p2-eval-set.md                 ✅ GENERATED
├── docs/reports/p2-calibration.md              ✅ GENERATED
├── docs/reports/p2-conflicting-duplicates.md   ✅ GENERATED — the human hand-off
├── README.md                                   ✏️ a P2 section: the command and the console
├── apps/lab/.env.example                       ✏️ any new key, no values
└── CLAUDE.md · AGENTS.md                       ✏️ P2's measured facts, byte-identical
```

**Structure notes.** `app/Support/Dedup/` is where the deterministic core lives, and every class in it
is a pure function or a single SQL statement — which is why the unit suite can cover the rules before
they touch a row. `app/Jobs/Dedup/` mirrors `app/Jobs/Import/`'s shape so `ResumeCursor` and
`BatchUpsert` are reused rather than re-invented. **No new ADR** unless the gate forces an embedder
switch; a command, a job, an endpoint and a Filament resource are none of them architectural *and*
durable *and* expensive to reverse.

## Design Artefacts

| Artefact | Why it earns its place | |
|---|---|---|
| [notes.md](./notes.md) | Nine Phase 0 findings. N1 (the embedding budget is off the wrong key) and N2 (the conflict tallies do not reconcile) each **changed the spec**; N7 closed an open item; N3, N5, N6 and N9 stop a job or a migration from being written against the plan's prose. | ✅ |
| [data-model.md](./data-model.md) | Eight tables, column by column, against the measured schema. The `*_source_id` convention is the single most likely defect in this feature, and this is where it is pinned. | ✅ |
| [contracts/verdict.md](./contracts/verdict.md) | **The service is a second party, and P4 is a third.** This is the program's first structured-output endpoint; the seven fields become seven columns, so a field rename is a migration. The prompt-versioning and injection rules belong with the contract, not in a docstring. | ✅ |
| ~~research.md~~ | Skipped: the research *is* `notes.md`, and it is measurement, not survey. A second file would restate it. | ❌ |
| ~~quickstart.md~~ | Skipped: `README.md`'s P2 section **is** the quickstart and is a deliverable (FR-113). A second copy would drift — the same call P1 made. | ❌ |
| ~~a new runbook / ADR / handover~~ | Skipped by policy. `lab:dedup --help` carries the operating instructions. The one permitted ADR is conditional on an embedder switch that has not happened. | ❌ |

## Implementation Grouping

| Group | Phase | Covers | Depends on | Model calls |
|---|---|---|---|---|
| **A — Schema** | 1 | 8 migrations · 8 models · the FK-through-`source_id` test · verify `NoPiiInLabSchemaTest` passes unchanged | nothing | none |
| **B — The rules** | 2 | `ArabicNormalizer` (incl. `fuzzy()`) · `OptionLabelStripper` · `OptionsNormalizer` · `DuplicateHasher` · `ClusterClosure` · `WilsonInterval` · `CalibrationExpansionRule` + units, incl. the `ة → ه` negative test and the fold-isolation test | nothing (**parallel with A**) | none |
| **C — Text, hashes, first clustering** | 3 | derive-text · hash-cluster · the media split · the `orthographic` candidate source · `LabDedup` skeleton | A, B | none |
| **D — Embeddings** | 4 | `EmbeddingClient` · `EmbedQuestions` (two dedup keys, N1) · 2 error codes | C | ~24,063 embed |
| **E — Candidates** | 5 | the earned trgm index · both finders · `GenerateCandidatePairs` · media rule enforced again | D | none |
| **F — The eval set** | 6 | `EvalSetSampler` (per-wave) · **the verdict endpoint, service side** · the labeling screen (3 modes) · the optional blind-first pre-label · eval-set report | E · **🔴 wave-1 labels** | ≤ eval-set size (pre-label, optional) |
| **G — Calibration** | 7 | `ThresholdCalibrator` · `WilsonInterval` · the expansion rule · banding · the gate · calibration report · *conditionally* `EmbedderBenchmark` | F's labels, **per wave** | none (benchmark: eval set only) |
| **H — The verdict** | 8 | `VerdictClient` · `LlmBudgetGuard` · band-gated dispatch · failure/terminal state | G | ≤5,000 verdicts |
| **I — The high band** | 9 | `AutoClusterHighBand` (closure + size guard) · the 5% spot-check | G (**parallel with H**) | none |
| **J — Backlog & console** | 10 | `AffectedStudentCounter` · `ConflictTierAssigner` · the review console · the status map · the daily cap · on-demand AI triage · conflict report | H, I | on demand, 1/cluster |
| **K — Guards & wrap-up** | 11 | the 13 test suites · the 11th health check · README · `.env.example` · CLAUDE.md = AGENTS.md · the double acceptance run | all | none |

**Order**: A and B are independent and come first — B is pure functions with no database at all, so it
can be written and fully tested before a single migration runs. C through G are strictly sequential,
because each consumes the previous group's rows. **H and I run in parallel**: they read disjoint bands
of the same table and never write the same row. J needs both. K last.

**The human labels gate G, not the whole feature.** A through F can be built and tested before a
single pair is labelled. G cannot start, because calibrating against absent labels is the one thing
FR-104 exists to prevent. **Amended 2026-08-28**: what gates G is now **wave 1 — 100 pairs, 1.5–2.5
hours** — and F↔G may cycle up to four times (sample a wave → label → calibrate → `expand`) before G
settles. Each cycle is cheap; the ceiling is 400 and never moves.

**Why the verdict endpoint moved from H into F.** The stateless service side — `verdict.py`,
`POST /verdict`, prompt v1, the contract test — takes two questions and returns a verdict. It needs no
thresholds, so it never depended on calibration; only the *rationed dispatch* does. Moving it ahead of
labelling is what makes the optional AI pre-label possible at all. **The band guard did not move**:
`VerdictClient`, `LlmBudgetGuard` and the band-gated dispatch stay in H, where the band exists, and
FR-079's counter still reads exactly zero. The eleventh health check stays in K, because 10/10 is the
*arrival* baseline every intermediate phase is measured against and 11/11 is the exit condition.

**Within C**: the derive pass must complete before the cluster pass, and the media fingerprint must be
computed in the **same** pass as the hashes — a second pass over 29,142 rows to add it would be free
to write and would let the two drift.

## Decisions Taken Under Principle I

Ordinary architecture, decided with judgement and stated here rather than asked. All are reversible
by an edit.

| Decision | Reasoning |
|---|---|
| **Each embedding deduplicates by its own hash** — stem by `question_text_hash`, full by `question_with_options_hash` | N1. Sharing one key is wrong in both directions: the text hash would give two questions with different options the same full vector; the options hash would recompute 1,875 identical stem vectors. The two grains are genuinely different sizes (11,094 vs 12,969) |
| **Phase 4 uses the existing single-text `/embed` and measures the first chunk before anything is batched** | N3. The endpoint has no batch mode and Ollama's does. Adding one is small, but "we will need it later" is not a justification (constitution IV) — `import_runs.elapsed_seconds` decides, not a guess |
| **`media_fingerprint` hashes an ordered list of paths; video is excluded; a NULL path folds in as empty string** | N5. Four questions carry two images each, so a single-path assumption is right 99.93% of the time and silently wrong otherwise. Decision 4's evidence is about images specifically; a video attachment is not part of the identity test until something measures that it should be |
| **Closure is union-find in PHP, in a pure tested class — not a recursive CTE** | The pair set is ~114,000 at its upper bound, which is milliseconds and a few MB in PHP. Order-independence (FR-118) is then a property provable by a unit test that shuffles the input, which is far harder to assert about a CTE |
| **The closure size guard applies to pair-derived clusters only, never to hash clusters** | A **538-member** hash group exists and is a true finding — hash equality is transitive, so it cannot be a chaining artefact. Median group size is 3 and p99 is 15. Guarding hash clusters would flag the bank's single largest real duplicate group as a defect |
| **The guard's initial value is config, and Phase 9 records the component-size distribution before it is fixed** | There is no measured basis yet for the *pair-derived* distribution, only for hash groups. Setting a number now would be exactly the unmeasured assumption the constitution forbids; the guard ships with a starting value and a logged distribution |
| **`relation_type` and `status` are `text` with a check constraint, not Postgres `enum` types** | P1's own precedent: `source_item_stats.scope` is `text` and its migration records why — "a third scope costs a row, never a migration". A new relation value should cost an edit, not a type migration with data in it |
| **The doubled subsample is a `label_round` column in the unique key** | Makes agreement one self-join, keeps the cumulative count unambiguous (`round = 1`), and lets a third labeller cost a row. The alternatives were a ninth table or doubled nullable columns |
| **`sample_wave` is a separate column from `label_round`, and not in the unique key** | They are orthogonal: `label_round` is *who labelled*, `sample_wave` is *which draw*. Overloading `label_round` would make waves 2 and 3 read as second and third labellers and silently corrupt inter-rater agreement — the likeliest defect in progressive calibration. A pair is drawn in exactly one wave, so the wave does not belong in the key |
| **The fuzzy fold stores a hash, not a text, and gets a btree rather than a GIN index** | The fold is a pure function of `search_text`, so a stored folded string is a second copy that can drift. Grouping on `fuzzy_text_hash` reuses the Layer 0/1 machinery and gives perfect recall for the tolerated typo class with no threshold. Measured yield is ~12 stems (N10) — nowhere near enough to earn a second trigram index (constitution VII) |
| **`fuzzy_rules_version` is separate from `normalizer_version`** | The strict hashes do not depend on the fold, so changing or disabling the fold map must not make a single strict hash look stale. Sharing one version column would force a needless re-derive over 29,142 rows |
| **The expansion rule and the Wilson interval are pure classes, not inline calibrator code** | The stopping rule is the one place a progressive design can silently become a weaker gate. Pure functions let a unit test pin "expands when the interval straddles" and "stops on a decisive fail" against hand-computed values, without a database |
| **The AI pre-label is blind-first in the screen rather than merely stored separately** | Separate storage stops the *data* from being contaminated; it does not stop the *human* from being anchored. Hiding the suggestion until the label is committed is the only mechanism that protects the ground truth itself, and pairing it with a blind `label_round = 2` makes the anchoring effect measurable at zero extra cost |
| **AI triage is on-demand per cluster, never a batch pass** | Most of the 928 conflicts come from the hash layer and carry no verdict. A batch triage pass would be a new unbounded model path outside the uncertain band — the same invariant FR-078 protects. On demand, the human is the budget |
| **`priority_tier` is stored but derived from configured percentiles, with the cut values logged** | Same discipline as the closure size guard: there is no measured basis for fixing an absolute threshold that will drift with the population. Storing the tier makes the console's ordering an index scan; logging the cut values makes "why was this tier 2?" answerable later |
| **Verdict failure needs three columns, not one** — `verdict_attempts`, `verdict_last_error`, `verdict_failed` | A boolean alone loses *why*; a counter alone cannot express "stop trying". Together they make the retry bound enforceable and the console count possible |
| **`LabDedup` resolves `SourceSnapshot::latestRun()` and records `ran_via`, exactly as `LabImport` does** | N6. Both `import_runs` columns are NOT NULL with no default, and the plan mentions neither. Failing with a clear message beats a constraint violation |
| **Tests split across the three existing suites rather than adding a fourth** | N9. `Unit`/`Feature` run on the disposable database; whole-bank claims go to `MirrorValidation`, which is read-only against the real mirror and already excluded from the default run |
| **The daily cap is configuration, not a table** | It is one integer per queue with no history worth keeping. A table would imply the cap is data someone reports on; it is a pacing setting the operator changes when the commitment changes |
| **`NoPiiInLabSchemaTest` is not edited** | N7. The scan is already table-agnostic and picks up the eight tables the moment they exist. Editing a security assertion that already does the right thing is how it stops doing the right thing |

## Open Questions

**None.** Every question this feature raised was either answered in the 2026-08-27 clarification
session (five), decided by the operator on 2026-08-28 (three, recorded as a second session in the
spec), or falls inside Principle I's "decide with judgement" half and is stated in the table above.
Decision 1 — new sibling tables, never `ALTER` on the mirror — is what keeps it that way: the one
artefact class Principle I names as expensive to reverse is the mirror schema, and this feature does
not touch it.

**One item still needs the operator conditionally, and it is already a human gate rather than a plan
question:**

| # | Item | When |
|---|---|---|
| **B / C** | Approve pulling alternative embedder weights, and decide whether to switch the embedding contract | **Only if** the gate fails decisively, or at the 400 ceiling, at the end of Group G |

**Gate F is no longer here.** It required committing 31–77 trainer hours before Group J could ship,
but nothing downstream depended on the backlog being *worked* — the console ships whether or not one
item has been reviewed, and the constitution's gate policy names that shape as a procedural gate. The
operator's answer is recorded instead: **a standing queue at `daily_review_cap = 10`, blocking
nothing** (FR-151). What the gate appeared to protect — deterministic ranking, the full size always
visible, no Production write path — is protected by FR-088 to FR-092 and FR-150 to FR-154, which are
tested rather than promised.

**Blocking prerequisite, not a question**: **wave 1 — 100 pairs** must be labelled before Group G
runs, and up to three further waves may be requested by FR-144's rule. Already decided (spec
Dependencies, human gate A) — it needs doing, not deciding.

**One constitutional amendment was required and was approved rather than assumed.** Constitution IV
forbade `ة → ه` without qualification; the fuzzy recall form needed it. Rather than absorb the
conflict, it was put to the operator, who approved narrowing IV to the property it was protecting —
the strict layers and every hash, cluster key and identity decision — while admitting a named
recall-only form. **Constitution is now v2.5.0.** This is the only rule in the program this feature
changed.
