# P2 — three operator decisions: normalization, progressive calibration, backlog triage

## Context

The operator has taken three decisions that change what `006-p2-duplicate-intelligence` builds. None
of the 134 tasks has been implemented yet (`apps/lab/app/Support/Dedup/` and `app/Jobs/Dedup/` do not
exist; no P2 migration exists), so **every change below lands in artefacts that have not been written
yet — there is no migration churn and no rework.**

1. **T003 — normalization.** Keep `ة → ه` out of the strict hashing path, but allow it as a *typo
   tolerance* in the fuzzy/candidate layer only; strip both Arabic and Latin option labels, anchored
   to the start of the option text.
2. **T074 — calibration workload.** Replace the fixed 400 human labels with progressive waves
   (100 → 200 → 300 → 400), expanding only when the current sample cannot support a reliable
   decision. Humans stay the ground truth; AI may pre-label as a separate, non-authoritative field.
3. **T111 — conflict backlog.** A solo developer cannot commit 31–77 trainer hours. The 928-item
   backlog must stay ranked by measured impact, must never block, gets a small soft
   `daily_review_cap`, and gains practical priority tiers. AI may rank and recommend; measured impact
   stays deterministic; no Production write path.

## What I measured before planning (read-only, `injazedu_lab`, snapshot 2026-08-07)

These are new numbers, not restatements. They decide three design choices below.

**Option labels are real, and Latin labels outnumber Arabic 3:1** — the operator's premise is
confirmed by the data, not assumed:

```text
115,719 active options
  3,604 begin with a Latin label + delimiter   (A.  B)  c- …)
  1,200 begin with an Arabic label + delimiter (أ.  ب)  ج- …)
    254 begin with a digit label
  9,601 begin with a Latin letter at all
```

**The bank is ~10% non-Arabic**, which is why the normalizer cannot be Arabic-only:

```text
2,844 of 28,747 active questions (9.9%) contain no Arabic character
2,036 question stems begin with a Latin letter
```

**Both proposed folds are small-yield, and that is worth stating plainly** (rough SQL normalizer,
stem grain):

```text
distinct normalized stems                     11,097   (agrees with notes.md N1's 11,094)
   + ة → ه fold                               11,085   → collapses 12 more stems
distinct without case folding                 11,102
   + Unicode lower-case                       11,097   → collapses 5 more stems
```

**The conflict backlog reproduces at exactly 928, and its impact is extremely concentrated** — this
is the whole case for triage:

```text
928 conflicting duplicate groups, no image member   (reproduces notes.md N2 exactly)
269,153 total affected students across the backlog
      0 groups with zero measured impact   ← a "no impact" tier would be empty; do not build one
     64 groups   1–29 students
    558 groups  30–199
    306 groups  ≥200            per-group: p50=141  p75=282  p90=686  max=6,966

Top  25 groups →  21.1% of all measured student exposure
Top  50 groups →  34.1%
Top 100 groups →  50.4%    ≈ 3–8 hours at 2–5 min/item
Top 200 groups →  67.3%    ≈ 7–17 hours
All 928        →   100%    ≈ 31–77 hours
```

**Half the measured harm sits in the top 100 items.** At a cap of 10/day that is ten working days.
This is the number that makes the operator's decision defensible rather than a concession.

---

## Decision 1 — T003, Arabic normalization

### What already agrees (no change needed)

`ة → ه` is already forbidden in the strict path by FR-012, SC-002, US1 scenario 3, the constitution
IV text-layering bullet, and a negative unit test (T025). The operator's first instruction confirms
the existing rule.

### What conflicts

| # | Where | Conflict |
|---|---|---|
| 1 | constitution IV | "Meaning-changing normalization (notably `ة → ه`) is **forbidden**" is written without qualification. A stored fuzzy form that folds `ة → ه` contradicts the sentence as written, even though it preserves the property the sentence protects. **This is the one item that needs the operator's explicit call — see the question below.** |
| 2 | FR-011 | Lists option-label stripping as "where present" with no alphabet, no delimiter set, and no anchoring rule. Given 3,604 Latin-labelled options, an Arabic-only implementation would silently miss the majority case. |
| 3 | FR-011 | **Omits case folding entirely** — yet notes.md N1 measured the 11,094 distinct-stem figure *with* lower-casing, and the whole ~24,063 embedding budget rests on it. The requirement list and the budget it quotes disagree. With 9.9% of the bank non-Arabic this is a real defect, not a nit. |
| 4 | FR-025, data-model §4 | `hash_match_level` admits only `exact` / `formatting` / NULL. An orthographic-variant match has nowhere to go, so it would either be silently dropped or mislabelled as `exact` — exactly what the operator forbids. |
| 5 | T003, spec Human Gate H, Dependencies | All say "confirm the option-label stripping list with a trainer" without listing it. Nothing to confirm. |

### What changes

**Strict path — untouched.** `clean_text`, `search_text`, `question_text_hash`,
`question_with_options_hash`, `media_fingerprint` and every cluster key keep exactly today's rules
plus case folding. No fold ever reaches them.

**A fourth, explicitly-named recall form.** `ArabicNormalizer::fuzzy()` applies a **config-gated**
character map to `search_text`. Only `ة → ه` ships enabled; the map is config so `ى/ي` costs an edit,
not a migration. Only its **hash** is stored (`fuzzy_text_hash`) — the text is a pure function of
`search_text` and is recomputable.

**How it produces recall without a second index.** Grouping on `fuzzy_text_hash` is a plain btree
join that reuses the Layer 0/1 machinery. It gives *perfect* recall for the tolerated typo class with
no similarity threshold and no second GIN index — which matters because trigram similarity degrades
on short stems, exactly where a one-character difference hurts most.

**The guard the operator asked for, stated three ways:**

- a pair equal under `fuzzy_text_hash` but not under `question_text_hash` is written as a
  **candidate** with `hash_match_level = 'orthographic'`, never as a cluster;
- no automatic path may assign it `relation_type = 'exact_duplicate'`;
- it routes to the high band for a verdict or a human, alongside `formatting` matches.

**Option-label stripping, specified.** Anchored to string start, one label token, followed by a
delimiter and optional whitespace, applied to option text and to a stem's leading enumerator:

```text
alphabets   Arabic   أ ب ج د هـ ه   (and the ا/إ/آ variants of أ)
            Latin    A B C D E  a b c d e
            digits   0–9 and ١–٥
delimiters  .  )  -  :  ،  ,  ( )-wrapped forms
anchor      ^\s* LABEL \s* DELIM \s+   — never a letter inside the text
```

A unit test asserts that `"د. المدينة"` loses its label while `"دمشق"` and `"A cat sat"` do not.

### FR-level changes

| FR | Change |
|---|---|
| **FR-011** amended | Name the label alphabets, the delimiters and the leading-anchor rule; **add Unicode-aware case folding**, with notes.md N1's lower-cased measurement cited as the reason |
| **FR-012** amended | Scope the prohibition precisely: forbidden in `clean_text`, `search_text`, and in every hash, cluster key and identity decision — and name FR-141's carve-out so the rule is narrowed deliberately rather than eroded |
| **FR-139** new | Option-label stripping is leading-anchored, single-token, delimiter-terminated, and covers both scripts because ~10% of the bank is non-Arabic |
| **FR-140** new | `search_text` applies Unicode case folding; the embedding budget depends on it |
| **FR-141** new | A config-gated fuzzy form MAY fold `ة → ه`; it MUST NOT feed any hash, cluster key or identity decision |
| **FR-142** new | Fuzzy-only equality becomes a candidate with `hash_match_level = 'orthographic'`, never an auto-cluster and never `exact_duplicate` |
| **FR-143** new | A test proves both hashes are byte-identical with the fold on and off, and that an orthographic-only pair never auto-clusters |
| **SC-002** amended | Scoped to the strict layers and hashes; adds the orthographic-never-exact assertion |
| **SC-031** new | Option-label stripping removes leading markers in both scripts and never a letter inside the text |

---

## Decision 2 — T074, progressive calibration

### What conflicts

| # | Where | Conflict |
|---|---|---|
| 1 | FR-050, SC-009, US4 scenarios 1/7, plan.md Group F, T070, T074, gate A | "exactly 400" is stated as a fixed quantity in nine places |
| 2 | data-model §8 | `label_round` already means *which labeller* (1 = primary, 2 = the independent second). Reusing it for expansion waves would break inter-rater agreement, which is computed as a self-join between rounds 1 and 2. **A separate `sample_wave` column is required** — this is the most likely silent defect in this decision |
| 3 | FR-051 | The quota list misses two of the case types the operator named: **same-stem/different-options** (`hash_match_level = 'formatting'`) and **answer-key conflicts**. Both are exactly the hard cases the gate must be measured on |
| 4 | **FR-078, FR-079, SC-012** | "A budget guard MUST run before **every** dispatch and MUST throw for any pair outside the uncertain band", and the out-of-band verdict counter "MUST be exactly 0". Eval pairs are sampled *before* calibration, so `band IS NULL` — an AI pre-label would trip the guard and break the counter. **This is a real invariant, and it is preserved by construction below, not relaxed** |
| 5 | Phase ordering | The verdict endpoint and prompt v1 are built in Phase 7 (T086–T089), *after* the Phase 6 labelling gate. An AI pre-label has nothing to call |
| 6 | FR-060/FR-061 | The gate is a bare point estimate. At n=100 with ~40 positives, a precision of 0.90 carries a 95% interval of roughly [0.76, 0.97] — a "pass" that is not a pass. A progressive design without a statistical stopping rule would make the gate *weaker*, not just cheaper |

### What changes

**Waves, with each wave independently representative.** Wave sizes come from config
(`[100, 100, 100, 100]`). **Every wave independently satisfies the full decile stratification and all
quotas**, so a set stopped at 100 is a smaller ruler, never a biased one. This is the property that
makes early stopping safe.

**The stopping rule is statistical, not a judgement call.** After each wave the calibrator computes
the gate on the *cumulative* labelled set and **expands unless all four hold**:

```text
1. every similarity decile and every quota is non-empty at the cumulative n
2. inter-rater agreement on the doubled subsample is acceptable        (FR-064, unchanged)
3. the positive class holds ≥ 30 pairs      (constitution VI's own n≥30 full-metrics threshold)
4. the 95% Wilson lower bound of precision ≥ 0.90 AND of recall ≥ 0.70
```

Condition 4 is what the operator's "cannot support a reliable decision" means in arithmetic. **This
raises the bar**: today's spec passes the gate on a point estimate at n=400; this passes only when
the *interval* clears it. A gate that clears at n=100 under a CI rule is better evidence than one
that clears at n=400 under a point estimate.

**A decisive failure short-circuits.** If the 95% *upper* bound of precision is below 0.90, the gate
is recorded failed and plan.md's embedder fork is taken immediately — no reason to label 300 more
pairs to confirm a clear failure. This saves human time on the bad path, which is where it matters.

**At 400 the expansion stops** and the decision is taken on the point estimate, recorded with its
interval either way. The ceiling never moves.

**Every wave writes its own `duplicate_eval_runs` row** carrying `sample_wave`, both confidence
intervals, the positive-class count and an explicit `expansion_decision` of
`expand | stop_pass | stop_fail`. FR-065's never-overwrite rule already covers this.

**Two new quotas** on top of FR-051's five: `formatting` pairs (same stem, different options) and
answer-key-conflict pairs — plus an `orthographic` quota from Decision 1, so the new fuzzy layer is
**measured on the eval set rather than assumed to help**.

**AI pre-label — separate storage, separate budget, separate counter.**

- Stored on `duplicate_eval_pairs.ai_*`. **Never** in `human_relation`, **never** in
  `duplicate_candidates.llm_*`, **never** in the calibration positive class.
- FR-079's counter is a query over `duplicate_candidates` — it continues to read **exactly zero**,
  untouched. FR-078's guard is scoped in wording to the verdict-dispatch path it was always about;
  the pre-label is a second path with its own ceiling (= the eval-set size) and its own counter.
- **Anti-anchoring is the real risk, and it is handled by construction:** the screen does not reveal
  the suggestion until the human has committed their own label. The row records
  `ai_suggestion_shown` and whether the human revised after seeing it.
- **The anchoring effect is measured for free:** the `label_round = 2` labeller works **blind**, so
  the existing inter-rater agreement between an assisted and an unassisted labeller *is* the
  measurement. No new machinery.
- Off by default (`ai_prelabel_enabled = false`). Wave 1 is labelled blind regardless.

**Ordering — approved.** The service-side verdict endpoint (`verdict.py`, `POST /verdict`, prompt v1,
the contract test — T086–T089) moves into Phase 6 ahead of the labelling task. It takes two questions
and returns a verdict; it needs no thresholds, so it is genuinely independent of calibration. The
**rationed dispatch** machinery (`VerdictClient`, `LlmBudgetGuard`, band-gated `RequestLlmVerdict`)
stays in Phase 7 where the band exists — **the guard is not moved, only the stateless endpoint.**
`lab:health`'s eleventh check (`VerdictEndpointCheck`, T124) may stay in Phase 10 or follow the
endpoint forward; it stays in Phase 10, because 11/11 is the *exit* condition and moving it would
break the 10/10 arrival baseline every intermediate phase is measured against.

### FR-level changes

| FR | Change |
|---|---|
| **FR-050** amended | Draw in waves; wave 1 = 100 by default; each wave independently satisfies the strata and quotas; cumulative ceiling 400 |
| **FR-051** amended | Add `formatting`, answer-key-conflict and `orthographic` quotas |
| **FR-056** amended | The `label_round = 2` labeller works blind, so agreement also measures anchoring |
| **FR-078** amended | Guard scoped to the verdict-dispatch path writing `duplicate_candidates.llm_*` |
| **FR-144** new | The four-condition expansion rule, incl. the Wilson lower bound |
| **FR-145** new | Decisive-failure short-circuit on the Wilson upper bound |
| **FR-146** new | 400 is the ceiling; the decision is recorded with its interval either way |
| **FR-147** new | AI pre-label stored in `ai_*` only, never authoritative |
| **FR-148** new | Blind-first labelling; `ai_suggestion_shown` and revision recorded |
| **FR-149** new | The pre-label path is separately budgeted and counted; FR-079's counter stays zero |
| **SC-009** amended | Wave-based; the stopping rule fired correctly and is recorded |
| **SC-012** amended | Scoped to `duplicate_candidates`; still exactly zero |
| **SC-032** new | No `ai_*` value ever appears in `human_relation` or the positive class, proven by test |

---

## Decision 3 — T111, conflict backlog

### What already agrees

Ranked by `affected_student_count` (FR-089), never by id · the cap is already soft and non-blocking
(FR-135–FR-138) · the full remaining size always visible (FR-090) · no Production write path
(FR-092) · Decision 5 already says "worked by rank, never presented as a finite task".

### What conflicts

| # | Where | Conflict |
|---|---|---|
| 1 | **T111, spec Human Gate F, Dependencies, plan.md's operator table, tasks.md dependency graph** | Gate F is 🔴 **blocking** — "commit trainer time … **decide before Phase 9 ships**". The operator cannot commit that time, and nothing downstream actually depends on the backlog being worked: the console ships whether or not anyone has reviewed an item. **The constitution already resolves this**: "Procedural gates are not gates and are not written … any check whose only purpose is satisfying another document." Gate F protects no engineering property. It is reclassified, not weakened |
| 2 | FR-089, data-model §5 | There is no tier — only a single ordering key. "Work the top of a 928-row list" is not triage for a solo developer |
| 3 | `config/lab.php` (T001) | `daily_review_cap` has no default value anywhere |
| 4 | FR-088, constitution II/IV | AI helping with triage is permitted only if the *measured* number stays deterministic. Needs to be stated as a requirement, not left to implementation |
| 5 | Model call budget | Most of the 928 come from the **hash** layer and carry no verdict at all. A batch AI triage pass over them would be a new unbounded model path outside the uncertain band — the same invariant as Decision 2 conflict #4 |

### What changes

**Gate F is reclassified from a blocking commitment to a recorded configuration decision.** The
operator's answer is already given: no trainer commitment; the backlog is a standing queue worked by
rank under a small cap. T111 becomes "set `daily_review_cap` and record that the backlog is standing"
— no phase, report, or acceptance criterion may block on its remaining size.

**Deterministic priority tiers, from the measured distribution.** Percentile cut points live in
config (`0.50 / 0.75 / 0.90` on arrival); the *values* are computed from the live
`affected_student_count` distribution at report time and **logged with the run** — the same
discipline plan.md already applies to the closure size guard, and the constitution's "measure before
building". On today's data:

| Tier | Rule | ≈ groups | ≈ share of exposure |
|---|---|---|---|
| `tier_1_critical` | ≥ p90 (≈686 students) | ~93 | ~49% |
| `tier_2_high` | p75–p90 (282–686) | ~139 | ~25% |
| `tier_3_standard` | p50–p75 (141–282) | ~232 | ~17% |
| `tier_4_deferred` | < p50 (<141) | ~464 | ~9% |

**No zero-impact tier is built** — the measurement says 0 of 928 groups have zero measured impact, so
a tier for them would be dead code.

**AI triage is advisory and bounded.**

- Stored in clearly named `ai_triage_*` columns on `duplicate_clusters`, displayed as a labelled
  recommendation with confidence and prompt version, visually distinct from measured values
  (FR-095 already requires this).
- **Never** writes `affected_student_count`, `priority_tier`, `status` or `relation_type`. Only a
  human review row moves a conflict out of `urgent_review` (FR-129, unchanged) — which is precisely
  "AI must not silently resolve conflicts requiring human judgement".
- **On demand, one cluster at a time, human-initiated.** Not a batch pass. Where a verdict already
  exists (uncertain-band conflicts), its stored `llm_recommended_action` / `llm_confidence` are
  simply displayed — no new call at all.

**The report carries the concentration.** The conflict report states the measured top-N share of
total exposure, so "we reviewed 100 of 928" reads as "we covered half the measured harm" rather than
as 11% of a list.

### FR-level changes

| FR | Change |
|---|---|
| **FR-089** amended | Order by `urgent_review`, then `priority_tier`, then `affected_student_count` desc |
| **FR-091** amended | The report is tier-scoped and states the top-N share of total measured exposure |
| **FR-150** new | `priority_tier` is deterministic SQL from config percentiles; the computed cut values are logged per run; never AI-assigned |
| **FR-151** new | The backlog is a standing queue; no gate, phase or criterion may block on its remaining size |
| **FR-152** new | `daily_review_cap` defaults to **10** and stays soft; the console shows per-tier counts and today's count |
| **FR-153** new | AI triage is advisory, stored in `ai_triage_*`, and may not write the count, the tier, the status or the relation type |
| **FR-154** new | AI triage is on-demand and single-cluster; never a batch pass; no new Production write path |
| **SC-017** amended | Adds tier assignment and its logged cut points |
| **SC-033** new | No AI value ever appears in `affected_student_count`, `priority_tier` or `status`, proven by test |
| **SC-034** new | The backlog blocks nothing: a full acceptance run passes with zero conflicts reviewed |

---

## The one constitutional conflict — resolved by the operator, 2026-08-28

**Constitution IV states, without qualification: "Meaning-changing normalization (notably `ة → ه`) is
forbidden."** Decision 1 asks for exactly that transform, guarded so it can never produce an identity
claim. The guard preserves the property the rule protects, but not the rule as written — and the
constitution is binding on every spec here, so this was put to the operator rather than edited.

**Approved: amend IV with a narrow carve-out, version 2.4.0 → 2.5.0.** The bullet becomes:

> **Arabic text is layered and never destroyed**: `raw_text` is immutable, `clean_text` is technical
> cleanup only, `search_text` is the comparison form. Meaning-changing normalization (notably
> `ة → ه`) is forbidden in `clean_text`, `search_text`, and in **every hash, cluster key, or identity
> decision derived from them**. A meaning-folding form may exist **only** as an explicitly-named
> recall aid that proposes candidates for human or model judgement — never as evidence of identity,
> and never the basis of an `exact_duplicate`.

The amendment **narrows** the rule to the property it was protecting and names the exception, rather
than eroding it. Everything else in this plan sits inside Principle I's "decide with judgement" half
— new sibling columns on P2-owned tables that do not exist yet, all reversible by an edit.

## Operator answers folded in, 2026-08-28

| Question | Answer |
|---|---|
| The `ة → ه` fuzzy fold vs constitution IV | **Amend IV** with the carve-out above; the full design ships — stored `fuzzy_text_hash`, `orthographic` candidate level, measured on the eval set |
| Verdict endpoint ordering for the AI pre-label | **Move the stateless endpoint into Phase 6** (`verdict.py`, `POST /verdict`, prompt v1, contract test). `VerdictClient`, `LlmBudgetGuard` and band-gated dispatch stay in Phase 7 — the guard does not move |
| `daily_review_cap` default | **10/day** — the top 100 conflicts (50.4% of measured exposure) in ten working days |

---

## Files to change

| File | Change |
|---|---|
| `specs/006-p2-duplicate-intelligence/spec.md` | 6 amended FRs, 16 new (FR-139–FR-154), 4 amended SCs, 4 new (SC-031–SC-034); a `### Session 2026-08-28` clarifications block recording all three decisions; Human Gates A/F/H rewritten; Dependencies and Assumptions updated |
| `specs/006-p2-duplicate-intelligence/data-model.md` | §2 `fuzzy_text_hash` + `fuzzy_rules_version` · §4 `hash_match_level` gains `orthographic` · §5 `priority_tier` + four `ai_triage_*` + revised index · §8 `sample_wave` + six `ai_*`/revision columns · §10 wave, CI bounds, positive-class count, `expansion_decision` |
| `specs/006-p2-duplicate-intelligence/plan.md` | Summary and Technical Context; Group F/G rewritten for waves; the endpoint moved into Group F; 5 new rows in *Decisions Taken Under Principle I*; the operator table loses gate F as blocking |
| `specs/006-p2-duplicate-intelligence/tasks.md` | Amend T001, T003, T024–T025, T068–T082, T086–T089 (moved), T105–T111, T113–T114; add ~10 tasks; update the dependency graph, the blocking-gates table and the summary counts |
| `specs/006-p2-duplicate-intelligence/notes.md` | An **N10** recording the six measurements above with their SQL provenance — the pattern N1/N2 already set |
| `specs/006-p2-duplicate-intelligence/checklists/requirements.md` | Record the 2026-08-28 session, as the 2026-08-27 one is recorded |
| `.specify/memory/constitution.md` | **Approved** — narrow IV's text-layering bullet as quoted above; version line 2.4.0 → 2.5.0 |
| `docs/plans/project/2/p2-duplicate-intelligence.md` | Three "superseded by spec" pointers (Phase 3 normalization, Phase 6's 400 pairs, §8 item F) — following the precedent that N1/N2's corrections live in the spec, not in the plan doc |

Not touched: any P1 mirror table · `config/lab.php`'s allowlists · `NoPiiInLabSchemaTest` · the media
boundary rules (FR-029–FR-034) · FR-092's no-Production-write rule · FR-108's no-deletion rule ·
FR-079's zero counter.

## Verification

These are documents, so verification is consistency, not a test run:

1. `grep -rn "exactly 400\|400-pair\|400 pairs" specs/006-p2-duplicate-intelligence/` returns only
   the ceiling sense, never the fixed-quantity sense.
2. `grep -rn "ة → ه\|ة→ه" specs/ .specify/` — every hit is either the strict prohibition or the
   named carve-out; none is ambiguous.
3. `grep -rn "FR-1[3-5][0-9]" specs/006-p2-duplicate-intelligence/` — every new FR is referenced by
   at least one task and one success criterion.
4. Gate F appears nowhere as 🔴 or as a blocker in spec.md, plan.md or tasks.md.
5. Every new column in data-model.md appears in the migration task that creates its table
   (T004, T006, T007, T010, T011) — no orphan column, no extra migration.
6. tasks.md's summary counts and dependency graph match the amended task list.
