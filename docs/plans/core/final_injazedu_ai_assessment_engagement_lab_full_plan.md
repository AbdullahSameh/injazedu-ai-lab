# InjazEdu AI Assessment & Engagement Lab
## Final Plan — v2.0 (Read-Only Production Edition)

**Version:** 2.0 — supersedes v1.0 (2026-08-07)
**Date:** 2026-08-17
**Development machine:** MacBook Pro 16" — Apple M1 Pro, 16 GB unified memory
**Production:** `injazedu.co` — Laravel + MySQL on DigitalOcean — **read-only for this program**
**Future AI Lab host:** Hostinger VPS (after local proof)

---

# Part I — Program Frame

## 1. Executive Summary

This document defines a program of **ten projects** around `injazedu.co` that achieves two goals at once:

1. Raise the quality of the questions and the tests, and increase **engagement** between trainers and students.
2. Use the platform as a practical laboratory for learning and applying: Local LLMs, Ollama, RAG, Embeddings,
   Vector Search, AI Evaluation, Document Understanding, Arabic NLP, Event-driven systems,
   Analytics, and Human-in-the-loop AI.

The current bank holds roughly **25,000 questions** with a great deal of duplication. So we do not start by creating new questions;
we start by turning the existing bank into a clean, measurable **Question Intelligence Layer**.

But — and this is the most important change from v1.0 — **engagement is the first declared goal**, and it must not wait for
six projects of data work. The program therefore runs on **two parallel tracks**:

```text
Track A — Question Intelligence          Track B — Engagement
(the foundation: understand and clean    (visible value early)
 the bank)

P0  AI Lab Foundation
     │
P1  Profiling & Question Mirror
     │
     ├──────────────────────────────────▶ P3  Item Statistics
P2  Arabic + Duplicate Intelligence           (SQL only, no AI)
     │                                         │
P4  Question Quality Audit                P6  Telegram Engagement Engine
     │                                         │
P5  Taxonomy & Coverage Map               P7  Public Practice & Funnel
     │
     └────────────▶ Track C — Content Creation
                    P8  PDF Knowledge & Source Library
                    P9  AI Trainer Assessment Copilot

Phase D (described, not built) — Production Integration & Assessment Builder
```

The system's foundational rule stays exactly as it was in v1.0:

```text
Production serves students.
AI Lab prepares, analyses and recommends.
Humans approve.
```

But with the "Production read-only" constraint we added a second, necessary rule (see §4):

```text
Where the Lab cannot hand anything over to Production,
the Lab owns an engagement surface of its own: Telegram + public practice pages.
```

---

## 2. What Changed from v1.0 and Why

The v1.0 plan is **excellent on principles**, and most of its architectural decisions are correct and preserved as they are in this document.
But it was written **before the actual schema was read**, so it was built on data assumptions that do not exist in Production.
This table summarizes the substantive changes:

| # | v1.0 | Reality / v2.0 | Impact |
|---|------|----------------|--------|
| 1 | Import JSON containing `"explanation"` | **There is no explanation column.** Only `questions.description` and `questions.hint` | Redefine the mapping before any import |
| 2 | `"correct_answer": "B"` | **There is no correct-answer column and no A/B/C/D keys.** The answer is inferred from `options.points > 0` | Questions with no correct answer and questions with more than one must be detected |
| 3 | `"exam_ids": [40, 42]` | **Structurally impossible.** `questions.section_id → sections.quiz_id` is a single-parent tree | Reframes the whole program — see §2.1 |
| 4 | Event tracking in Project 6 | **Not possible with Production read-only.** `results` has no timings, and `question_result.option_id` is `NOT NULL` | Split analytics into retrospective and prospective (§7) |
| 5 | Taxonomy assumed to exist | **There is no taxonomy at all in Production** | Taxonomy becomes an independent project (P5) with a human deliverable first |
| 6 | Telegram = Project 7 (after six projects) | Engagement is the first goal | Telegram becomes P6 on a **parallel track** that starts early |
| 7 | Item statistics buried in Project 8 | Computable **today** with SQL alone from `results`/`question_result` | Pulled forward as P3 — the highest value per unit of effort in the program |
| 8 | Project 4 merges Quality + Coverage | Quality does not need a taxonomy; Coverage does | Split into P4 and P5 |
| 9 | Redis + n8n in Project 0 | Neither is needed until P6, and the machine has 16 GB | Removed from the foundation (ADR-011, ADR-012) |
| 10 | FastAPI and Laravel both write to Postgres | Two schema owners = guaranteed drift | Laravel owns the schema, FastAPI is stateless (ADR-013) |
| 11 | No throughput budget | ~30K pairs × ~4 s = **~33 continuous hours** on an M1 Pro | The LLM runs only inside the uncertainty band (§13) |
| 12 | No benchmark for the embedders | Dedup quality is governed by the embedder, not the LLM | Benchmarking the embedders is mandatory (§12.4) |
| 13 | `conflicting_duplicate` with no path | Two identical questions with two different answers = an error that affects students right now | Urgent escalation path (P2) |
| 14 | STEP / IELTS not mentioned | Both depend on passage-based item sets | Stimulus as a first-class element (§8) |
| 15 | "Qiyas-style" with no definition | No specification of question count, timing, or distribution | A blueprint documented by subject-matter experts (P5) |
| 16 | §37 forbids agents while the brief asks for them | An apparent contradiction | An explicit and bounded position (ADR-014) |
| 17 | Three conflicting orderings (§34, §35, §52) | — | One ordering only (§34 in this document) |
| 18 | No effort estimates and no go/no-go | — | Every project has an effort estimate and accept/reject thresholds |

### 2.1 The Most Important Discovery: Duplication Is Not Negligence, It Is Structural

In Production, a question belongs to one section, and a section belongs to one quiz:

```text
questions.section_id → sections.quiz_id → quizzes.id
```

There is no many-to-many table between questions and quizzes. **So there is no way to reuse a question
in another quiz except by copying it.** That explains ~25,000 questions with heavy duplication: every time a trainer wanted
to use a good question in a new test, they created a copy.

The program-level consequence:

- Duplication is a **symptom**; the root cause is the **absence of a reusable item bank**.
- Cleaning up duplication (P2) treats the symptom, which is useful and necessary but **does not stop it from coming back**.
- The root fix is `question_pool` + a many-to-many relation in Production — and that **requires modifying
  Production**, so it falls into Phase D. It is the most important item there.
- Therefore: we do not promise anyone that "duplication will end." We promise to measure it, to group it into clusters, to prevent
  more than one member of a cluster from being used in one test, and to fix the dangerous errors.

This framing must appear in any presentation to management, because v1.0 would have been measured against a promise it could not keep.

---

## 3. The Four Governing Constraints

Every decision in this document derives from these constraints. If one of them changes, the document is reopened.

### 3.1 Production read-only

No migrations, no code changes, no data writes on `injazedu.co`.

**What this constraint kills from v1.0:**

| v1.0 | Why it does not work |
|------|----------------------|
| Project 6 — an Assessment Builder running in Production | `quizzes` has no type, no pass_mark, no attempt_limit, no time window, and no pool-draw |
| Event Tracking (Projects 6/8/9) | Needs a new events table + instrumentation in Production code |
| Write-back of approved questions (§26) | Needs a write endpoint in Production |
| Personalized Practice inside the platform | Needs reading real behaviour and writing recommendations |

**What remains entirely possible:** every analysis, every cleanup, every extraction, every draft generation, and every engagement over
a channel the Lab owns. And that is a great deal — see §7.

### 3.2 Engagement is the first goal

From the brief: "we focus primarily on engagement," and "the moderation team publishes the tests on Telegram automatically,"
and "encourage students to enroll in the open courses."

v1.0 put Telegram in Project 7 — that is, after six projects (months). That is an **inversion of priorities**.
In v2.0, Telegram sits on a parallel track that starts right after P1 and does not wait for the bank to be fully cleaned.

### 3.3 M1 Pro / 16 GB

The machine determines the stack and the compute capacity:

- Redis and n8n removed from the foundation.
- `gemma4:e2b-it-qat` is the working model; anything larger is measured in **isolated sessions** only.
- An explicit throughput budget (§13) — because "the LLM reviews all the candidates" is unbounded on this machine.

### 3.4 Human review capacity

What is available: **the developer (you) + the moderation team + a few trainers**.

This is a scarce and expensive resource, and it is the **real bottleneck** in any human-in-the-loop system. Therefore:

- Every human review step has a **budget** in hours (§13.3).
- Review is steered by active learning: the human reviews only what is close to the decision boundary, not everything.
- Trainers (the scarcest) are used only for domain judgments: answer correctness, question quality, the taxonomy.
- The moderation team handles repetitive operational work: reviewing duplicate pairs, approving publications.

### 3.5 An additional given: a Production copy exists locally

A copy of the Production database exists on the Mac, dated **2026-08-07**. This **simplifies P1 considerably** (no need to build
an exporter or agree on an export), but it **creates an immediate data-protection responsibility** — the copy contains
`users` (emails, phone numbers), `orders`, `certificates.id_number` (national ID), and `complaints`.
See §14 for the mandatory rules.

**Updated 2026-08-25:** this copy is **fixed for the entire local program**. It is not refreshed before P1, P2, or any later
project, and no gate anywhere blocks on its age. Its date travels with every number as context (§14.1).

---

## 4. Responsibility Model & ADRs

### 4.1 Decisions preserved from v1.0

These are correct and remain in force as they are:

| ADR | Decision |
|-----|----------|
| ADR-001 | Production remains the source of truth for identity, enrollment, payment, and official results |
| ADR-002 | The AI Lab starts read-only with respect to Production |
| ADR-003 | Ollama is not exposed to Production or to the internet; FastAPI is the gateway |
| ADR-004 | Embeddings come from a dedicated embedding model, not from the generative LLM |
| ADR-005 | Every AI output that affects educational content requires human review |
| ADR-006 | n8n orchestrates and does not own business logic |
| ADR-007 | The original Arabic text is preserved; normalization is task-specific |
| ADR-008 | Question generation is grounded in a source and duplicate-checked before approval |
| ADR-009 | Analytical calculations are deterministic; the LLM explains them and does not compute them |
| ADR-010 | Local development runs on controlled copies before any live integration |

### 4.2 ADR-011 — No Redis in the foundation

**Decision:** the Lab's queues run on Postgres (`database` queue driver); no Redis until a specific need appears.

**Why:** Production itself uses the `database` driver (the `jobs` table). The Lab already has Postgres.
Redis earns its place when distributed locks, rate limiting, or high load are needed —
and none of that applies to a single-machine laboratory. The benefit: one service fewer, ~200 MB less, and one concept fewer to learn
at the same time.

**Reconsider:** at P6 (Telegram limits). And even there, Postgres advisory locks + a token-bucket
table may be enough.

### 4.3 ADR-012 — n8n is deferred to P6, and its role is bounded

**Decision:** no n8n in P0. It enters at P6 for the parts that are genuinely orchestration-shaped — scheduling, calling
an API, alerting on failure, connecting systems — and every business rule stays in Laravel.

**Why — plainly:** n8n is a declared learning goal, but its engineering value here is limited, because v1.0 itself says
in §17 Workflow E that approval and the audit trail should live inside Filament. If approval is in
Filament and the logic is in Laravel, what is left for n8n is scheduling and alerting. That is enough to satisfy the learning goal
without inverting responsibilities.

**An acceptable alternative:** Laravel Scheduler + Jobs does the same job. Choosing between the two is
a learning decision, not an engineering one — and that is stated explicitly so the project is not measured on n8n.

### 4.4 ADR-013 — Laravel owns the schema; FastAPI is stateless

**Decision:** all migrations for the Lab's tables live in Laravel. FastAPI receives a payload and returns JSON,
and does not write to the Lab's tables.

**One narrow, declared exception:** bulk embedding writes may go directly from
FastAPI, and only into `question_embeddings` and `document_chunk_embeddings`, and those two tables
are still migrated by Laravel.

**Why:** in v1.0 §3.1 both FastAPI and Laravel appear writing to PostgreSQL. Two schema owners
means guaranteed drift and a painful correction later.

### 4.5 ADR-014 — Defining "agents" in this program

The brief asks for "Local AI models & agents," and v1.0 §37 forbids a "complex multi-agent architecture."
There is no contradiction once the term is defined:

**An agent here = a tool-limited loop, with a step ceiling, and a human gate.** Not an autonomous fleet of agents.

| Type | Classification |
|------|----------------|
| Duplicate adjudication, quality review, classification | **Not agents** — a single call with a structured output |
| Trainer Copilot session (P9) | **A genuine bounded agent** — it may call `retrieve_source`, `search_similar_questions`, and `validate_draft` in a loop, with a ceiling of ~6 steps, and must end with a draft + citations for human review |
| Writing to Production, publishing, deleting, changing a correct answer | **Forbidden for any agent** |

### 4.6 ADR-015 — The Lab owns the engagement surface (new and substantive)

**Decision:** since Production is read-only, the Lab **owns an engagement surface of its own**:
a Telegram bot and public practice pages hosted on the Lab. This content is **unofficial and ungraded**,
public, and aimed at the top of the marketing funnel. Conversion happens by linking outward to the course pages
in Production through tracked links.

**Why this is necessary:** v1.0 says "Production serves students" and "AI Lab prepares and recommends."
With read-only, there is no channel through which the Lab can hand anything to Production. So either the Lab owns
a surface, or nothing reaches any student at all. And this is not merely a concession: free public practice is by nature
a top-of-funnel product, and there is no reason to put it behind a login.

**The explicit boundaries:** the Lab issues no certificate, computes no official result, stores no PII, does not authenticate
a student's identity, and never claims to be an approved result. Official tests remain in Production alone.

**The meeting point:** Phase D — when Production is opened, the two surfaces merge.

---

# Part II — Production Reality

This part is what was missing from v1.0. Everything after it is built on it.
Every line here comes from `docs/schema/injazedu-db-schema.md`.

## 5. Concept → Reality Map

| Concept | Reality in Production | Lab handling |
|---------|-----------------------|--------------|
| Question text | `questions.name` — **LONGTEXT**, may contain HTML | `raw_text` / `clean_text` / `search_text` |
| Explanation | **No such column.** What exists is `questions.description` (TEXT) and `questions.hint` (TEXT) | `explanation_raw` ← `description`, `hint_raw` ← `hint`. **Measure the fill rate before relying on it** |
| Options | `options.name`, `options.points`, `options.order` | `option_index` derived from (`order` ASC, then `id` ASC) |
| A/B/C/D keys | **They do not exist** | Synthesized in the Lab only, for display and prompts, and never sent back to Production |
| Correct answer | **No such column.** Inferred: `options.points > 0` | `correct_option_ids[]` + a `correct_count` flag |
| Question ← test | `questions.section_id → sections.quiz_id`. **Exactly one test per question** | No many-to-many. See §2.1 |
| Course | `quizzes.course_id` (nullable) | `NULL` ⇒ a general/open test |
| Specialization | `quizzes.category_id` → the `categories` tree (`parent_id` INT, self-ref, **no FK**) | Copy the tree; watch out for the INT / BIGINT type mismatch |
| Lecture | `quizzes.lecture_id` | An indicator of a post-lecture quiz |
| Media | `quiz_files` — ENUM(video, image, audio), attached to a **section or a question**; and also `<img>` inside `questions.name` | Check both paths together |
| Shared text (passage) | `sections.name` + `sections.description` + `quiz_files.section_id` | An independent `stimulus` object — §8 |
| Duration | `quizzes.duration` INT, default 10 | For the whole test only. **There is no per-question timing** |
| Question author | **Does not exist.** Only `quizzes.user_id` | Attribution is at the test level, not the question level |
| Attempts | `results(total_points, user_id, quiz_id)` | No attempt number; it is derived by ordering `created_at` per (user, quiz) |
| Answers | `question_result(result_id, question_id, option_id, points)` | `option_id` is **NOT NULL** ⇒ **a skipped question cannot be recorded** |
| Answer correctness | No `is_correct` column | `points > 0` is the signal |
| Soft deletes | `deleted_at` on quizzes / sections / questions / options / results | Copy every row with `source_deleted_at`, and exclude them from active analysis |
| Course enrollment | **Ambiguous**: `course_user` versus `orders` + `course_order.expiry_date` | **Settled by query before any analysis** — §6.2 |
| Telegram channels | `courses.telegram_channel` / `telegram_group` / `telegram_private` (VARCHAR) | Copy them into a channels table with an explicit `is_public` flag |
| Roles | Spatie with custom table names: `user_roles`, `user_permissions`, `role_permissions`, morph key = `user_id` | Any tool that assumes the default names will fail |

### 5.1 Correct-answer inference rules (mandatory)

This is the most important rule in the whole ETL, because everything downstream depends on it:

```text
correct_option_ids = [o.id for o in options if o.deleted_at IS NULL and o.points > 0]
correct_count      = len(correct_option_ids)

correct_count == 1  →  single_correct        (the expected case)
correct_count == 0  →  BROKEN_NO_KEY         (a broken question — not used, and escalated)
correct_count >  1  →  MULTI_KEY             (either multi-select or partial credit — settled by measurement)
```

`MULTI_KEY` needs a decision: does the system support more than one correct answer, or are these data-entry errors?
Query 4 in §6.1 (the `points` distribution) answers it: if the values are all 0/1 they are probably errors;
if they are graduated it is intentional partial credit.

### 5.2 Option-order derivation rule

`options.order` is an INT whose default value is **0**, so it may repeat inside the same question.
A random ordering means that "option B" changes between one run and the next — and that ruins the prompts, the hashes,
and the human review.

```text
ORDER BY `order` ASC, id ASC   -- mandatory and identical everywhere
```

`order` is a reserved word in MySQL → always use backticks.

---

## 6. Profiling Query Pack

**This is the first practical task in the program, and it is executed before anything is built.**
v1.0 said "import 25 thousand questions" without measuring the bank first. We do not build on an unmeasured number.

They run against the **local copy** of MySQL. All of them are SELECT only.

> **Updated 2026-08-23:** queries **15, 16 and 18** read tables outside the copy list
> (`course_user`, `course_order`, `orders`, `user_roles`, `roles`, `book_course`). Those tables
> are now on the **aggregate-read list** in §14.2 — read as counts and never copied — so the whole pack is
> runnable in P1 with no exceptions.

### 6.1 Bank queries

```sql
-- 1) The real size of the bank
SELECT COUNT(*) AS total,
       SUM(deleted_at IS NULL) AS active,
       SUM(deleted_at IS NOT NULL) AS soft_deleted
FROM questions;

-- 2) Distribution of the number of options per question
SELECT opt_count, COUNT(*) AS questions FROM (
  SELECT q.id, COUNT(o.id) AS opt_count
  FROM questions q
  LEFT JOIN options o ON o.question_id = q.id AND o.deleted_at IS NULL
  WHERE q.deleted_at IS NULL
  GROUP BY q.id
) t GROUP BY opt_count ORDER BY opt_count;

-- 3) *** THE MOST IMPORTANT *** correct-answer integrity
SELECT correct_count, COUNT(*) AS questions FROM (
  SELECT q.id, SUM(CASE WHEN o.points > 0 THEN 1 ELSE 0 END) AS correct_count
  FROM questions q
  LEFT JOIN options o ON o.question_id = q.id AND o.deleted_at IS NULL
  WHERE q.deleted_at IS NULL
  GROUP BY q.id
) t GROUP BY correct_count ORDER BY correct_count;

-- 4) points distribution: is it 0/1 or graduated scores?
SELECT points, COUNT(*) AS options_count
FROM options WHERE deleted_at IS NULL
GROUP BY points ORDER BY points;

-- 5) Repeated order values inside a question (a hazard for key derivation)
SELECT COUNT(*) AS questions_with_order_ties FROM (
  SELECT question_id FROM options WHERE deleted_at IS NULL
  GROUP BY question_id, `order` HAVING COUNT(*) > 1
) t;

-- 6) Fill rate of description / hint (the explanation substitute)
SELECT COUNT(*) AS total,
       SUM(description IS NOT NULL AND TRIM(description) <> '') AS has_description,
       SUM(hint IS NOT NULL AND TRIM(hint) <> '')               AS has_hint
FROM questions WHERE deleted_at IS NULL;

-- 7) General versus course-specific
SELECT CASE WHEN course_id IS NULL THEN 'general' ELSE 'course' END AS kind,
       COUNT(*) AS quizzes, SUM(status = 1) AS active
FROM quizzes WHERE deleted_at IS NULL GROUP BY kind;

-- 8) Questions per quiz (to understand the real test sizes)
SELECT questions_per_quiz, COUNT(*) AS quizzes FROM (
  SELECT z.id, COUNT(q.id) AS questions_per_quiz
  FROM quizzes z
  JOIN sections s  ON s.quiz_id = z.id     AND s.deleted_at IS NULL
  JOIN questions q ON q.section_id = s.id  AND q.deleted_at IS NULL
  WHERE z.deleted_at IS NULL
  GROUP BY z.id
) t GROUP BY questions_per_quiz ORDER BY questions_per_quiz;

-- 9) HTML and images inside the question text
SELECT COUNT(*) AS total,
       SUM(name LIKE '%<img%')          AS has_img_tag,
       SUM(name LIKE '%<%')             AS has_any_html,
       SUM(CHAR_LENGTH(name) > 1000)    AS long_stems,
       MAX(CHAR_LENGTH(name))           AS longest_stem
FROM questions WHERE deleted_at IS NULL;

-- 10) Media: at the question level or the section level?
SELECT type,
       SUM(question_id IS NOT NULL) AS at_question,
       SUM(section_id  IS NOT NULL) AS at_section,
       COUNT(*) AS total
FROM quiz_files GROUP BY type;

-- 11) A first look at literal duplication (no normalization yet)
SELECT COUNT(*) AS duplicate_groups,
       SUM(c) - COUNT(*) AS redundant_questions
FROM (
  SELECT MD5(name) AS h, COUNT(*) AS c
  FROM questions WHERE deleted_at IS NULL
  GROUP BY h HAVING COUNT(*) > 1
) t;

-- 12) Sections that carry shared text (a passage) — important for STEP/IELTS
SELECT COUNT(*) AS sections_total,
       SUM(description IS NOT NULL AND CHAR_LENGTH(description) > 200) AS long_stimulus,
       SUM(name IS NOT NULL AND TRIM(name) <> '') AS named
FROM sections WHERE deleted_at IS NULL;
```

### 6.2 Behavioural-data queries (the basis of P3)

```sql
-- 13) The volume of answer data available for statistics
SELECT COUNT(*) AS answers,
       COUNT(DISTINCT result_id)   AS results,
       COUNT(DISTINCT question_id) AS questions_with_data
FROM question_result;

-- 14) How many questions have enough answers for statistics?
SELECT bucket, COUNT(*) AS questions FROM (
  SELECT question_id, CASE
    WHEN COUNT(*) >= 100 THEN 'a_100_plus'
    WHEN COUNT(*) >=  30 THEN 'b_30_to_99'
    WHEN COUNT(*) >=  10 THEN 'c_10_to_29'
    ELSE 'd_under_10' END AS bucket
  FROM question_result GROUP BY question_id
) t GROUP BY bucket ORDER BY bucket;

-- 15) *** SETTLING THE ENROLLMENT AMBIGUITY *** course_user or course_order?
SELECT 'course_user' AS src, COUNT(*) AS rows_,
       COUNT(DISTINCT user_id) AS users_, COUNT(DISTINCT course_id) AS courses_
FROM course_user
UNION ALL
SELECT 'course_order', COUNT(*),
       COUNT(DISTINCT o.user_id), COUNT(DISTINCT co.course_id)
FROM course_order co JOIN orders o ON o.id = co.order_id;

-- 16) Who are the users in course_user? Trainers or students?
SELECT r.name AS role, COUNT(DISTINCT cu.user_id) AS users_in_course_user
FROM course_user cu
JOIN user_roles ur ON ur.user_id = cu.user_id
JOIN roles r       ON r.id = ur.role_id
GROUP BY r.name ORDER BY users_in_course_user DESC;

-- 17) Telegram channel coverage (the basis of P6)
SELECT COUNT(*) AS courses,
       SUM(telegram_channel IS NOT NULL AND TRIM(telegram_channel) <> '') AS has_channel,
       SUM(telegram_group   IS NOT NULL AND TRIM(telegram_group)   <> '') AS has_group,
       SUM(telegram_private IS NOT NULL AND TRIM(telegram_private) <> '') AS has_private
FROM courses WHERE deleted_at IS NULL;

-- 18) Is the old book_course table really abandoned?
SELECT COUNT(*) AS book_course_rows FROM book_course;
```

### 6.3 What changes depending on the results

| Result | Effect on the plan |
|--------|--------------------|
| Many `correct_count = 0` (>2%) | Fixing the broken questions becomes the **first deliverable** in the program, before any dedup |
| Many `correct_count > 1` | Settle the meaning of multi-key before the ETL; it affects the hash and the polls in P6 |
| Low `has_description` (<30%) | The explanation path in P9 starts from zero; it cannot be used as few-shot examples |
| High `has_img_tag` (>10%) | The media path becomes a sub-project, not an edge case |
| Many questions with fewer than 10 answers | P3 is limited to the active bank; the coverage size is stated explicitly |
| `course_user` is all trainers | Enrollment = `course_order`; fix that in the documentation and build on it |
| `long_stimulus` is large | §8 (passage-based) rises from "an addition" to "a core requirement" |
| The real count ≠ 25,000 | Every estimate in §13 is updated |

---

## 7. What Can and Cannot Be Computed — with Production read-only

The table that should have been in v1.0 before the Project 8 promises.

### 7.1 Available today from existing Production data

| Metric | Source |
|--------|--------|
| Number of times a question appeared | `COUNT(question_result)` per `question_id` |
| Correct-answer rate (p-value) | `AVG(points > 0)` per question |
| Distractor selection distribution | `COUNT(*) GROUP BY option_id` |
| Discrimination (point-biserial) | Correlation of the item score with the test score from `results.total_points` |
| Score distribution per test | `results.total_points` |
| Number of attempts per (student, test) | `COUNT(results)` aggregated |
| Performance by course / specialization | Via `quizzes.course_id` / `category_id` |
| Attempt ordering (first versus repeated) | Ordering `results.created_at` |
| A rough estimate of test duration | `results.updated_at - created_at` — **a weak approximation, and labelled as such** |

### 7.2 Not available — needs Phase D

| Metric | Structural reason |
|--------|-------------------|
| Per-question answer time | No timestamp column in `question_result` |
| Test start/submit time | No `started_at` / `submitted_at` in `results` |
| Skipped questions | `question_result.option_id` is **NOT NULL** — the row is never created |
| Abandonment rate | There is no record of a test that started and did not finish — only completed results are recorded |
| Drop-off question | Follows from the above |
| Answer changes | No history |
| `question_viewed` and interface events | No instrumentation |
| Telegram → test conversion in Production | No link tracking in Production |

### 7.3 Fully available — on surfaces the Lab owns

This is what makes P6/P7 genuinely valuable despite the constraint: **the Lab can measure everything on its own surface.**

```text
practice_started      answer_changed        cta_clicked
item_viewed           item_flagged         link_clicked
answer_selected       practice_completed   poll_answered (Telegram)
                      practice_abandoned
```

**The explicit limit:** the funnel is measured up to the outbound click into Production, and we do not see the purchase.
Closing that loop needs Phase D. **This is stated in every conversion report** so the number is not misread.

---

## 8. Passage-Based Questions (STEP / IELTS / Verbal Aptitude)

The brief mentions STEP and IELTS sections. v1.0 ignored both entirely, and its question model is a flat MCQ.
But these tests are built on **groups of questions that share one text or one recording**.

**The good news:** Production supports that structurally:

```text
sections.name         → the passage title
sections.description  → the shared text (TEXT)
quiz_files.section_id → listening audio / an image
questions.section_id  → the questions belonging to the passage
```

**What the Lab must do:**

1. `stimulus` as a first-class object derived from the section, with its own `raw/clean/search` and its own embedding.
2. `item_set` = (stimulus + its questions), treated as one unit in selection and publishing.
3. **Dedup works on two levels**: duplication of the passage, and duplication of a question within a passage. Two questions that are textually
   identical but sit on two different passages **are not duplicates** — this is a common mistake that will generate many false positives
   if it is not handled.
4. EmbeddingGemma's 2K-token limit means a long passage **will not fit entirely** into a single embedding.
   The rule: a question's embedding is built from (the question + the options + an **excerpt** of the passage), not the whole passage.
5. Listening items (`quiz_files.type = 'audio'`) are entirely outside text processing; they are flagged
   `requires_media_review` and excluded from the text paths.

**A consequence for publishing:** passage-based questions **are not suitable for Telegram polls** (a 300-character limit on the question).
They are published as a link to a practice page in P7.

---

## 9. Known Production Problems the ETL Must Know About

We do not fix these (Production is read-only), but ignoring them produces wrong data in the Lab.

| # | Observation | Effect on the ETL |
|---|-------------|-------------------|
| 1 | `users.interested_course` is an INT with no FK and no Eloquent relation | A neglected marketing signal; it may help P7 — inspect it, do not rely on it |
| 2 | `categories.parent_id` is INT while `id` is BIGINT UNSIGNED, with no FK | Copy the tree carefully; expect orphans and cycles |
| 3 | `orders.signature` is also used to store the coupon code | Nothing to do with questions; not copied at all |
| 4 | `book_course` is abandoned (query 18) | Not copied |
| 5 | `quiz_files` has no soft delete — deletion is permanent | A media reference may be dead; verify the file exists |
| 6 | `Lecture.$fillable` does not include `youtube_id` / `upload_status` | Not question-related; noted only |
| 7 | `Section.$fillable` does not include `description` | The column exists and may nonetheless be populated — **read it, do not assume it is empty** (important for §8) |
| 8 | `orders.uuid` has no UNIQUE; `amount`/`currency` are strings | Out of scope |
| 9 | Spatie with non-default table names | Any role query uses `user_roles`, not `model_has_roles` |
| 10 | `quizzes.status` is TINYINT, and `questions` has no status | There is no question-level status — the Lab's status is the only status |
| 11 | `sorte_order` (a recurring typo) | Copied under the correct name in the Lab, with the original documented |
| 12 | Telescope is enabled in Production | Not copied; it may contain sensitive data |

---

# Part III — Platform

## 10. The Local Architecture (tuned for 16 GB)

```text
Mac — M1 Pro, 16 GB
┌────────────────────────────────────────────────────────────┐
│  Laravel 12 + Filament (native)          ← owns the schema │
│  ├── Question Inventory & Profiling                        │
│  ├── Duplicate Review Console                              │
│  ├── Item Statistics Dashboard                             │
│  ├── Quality & Taxonomy Review                             │
│  ├── Moderator Publishing Console                          │
│  ├── PDF Library                                           │
│  ├── Trainer Copilot                                       │
│  └── Queue Worker (database driver)                        │
│                    │                                        │
│                    ▼ HTTP (JSON only)                       │
│  FastAPI (native, uvicorn)                ← stateless      │
│  ├── Arabic normalization                                  │
│  ├── Hashing & lexical similarity                          │
│  ├── Embeddings (+ the mandatory prefixes)                 │
│  ├── LLM calls (structured output + validation)             │
│  ├── PDF extraction / OCR orchestration                    │
│  └── Retrieval helpers                                     │
│           │                              │                  │
│           ▼                              ▼                  │
│  Ollama (native, Metal)          PostgreSQL 17 + pgvector   │
│  ├── gemma4:e2b-it-qat                  (Docker, memory-capped)│
│  └── embeddinggemma:300m-qat-q4_0                            │
│                                                             │
│  MySQL 9.1 (native Homebrew — read-only through the app)  ← Production copy    │
│                                       read, never modified  │
│  [n8n — added at P6 only]                                    │
│  [Redis — not present; ADR-011]                              │
└────────────────────────────────────────────────────────────┘
```

**The native-versus-Docker decision:** on 16 GB, Docker Desktop itself consumes 1–2 GB. Therefore:
Postgres and MySQL in Docker (we need pgvector and an isolated MySQL copy), and everything else native.
A lighter alternative: OrbStack instead of Docker Desktop, or Postgres via Homebrew with pgvector.

## 11. Repository Structure

```text
injazedu-ai-lab/
├── apps/
│   ├── lab/                    Laravel 12 + Filament  (owns every migration)
│   └── ai-service/             FastAPI (stateless)
├── infrastructure/
│   ├── docker/                 compose + Dockerfiles
│   ├── postgres/               init.sql (CREATE EXTENSION vector)
│   └── n8n/                    added at P6
├── data/
│   ├── snapshots/              ← .gitignore — Production copies (sensitive)
│   ├── fixtures/               synthetic data for development
│   └── exports/                approved question packages (P9)
├── storage/
│   ├── documents/              original PDFs (private)
│   └── extracted/              extraction outputs
├── sql/
│   └── profiling/              the §6 pack as runnable files
├── evals/
│   ├── duplicate-detection/    embedder + adjudicator eval sets
│   ├── question-quality/
│   ├── arabic/
│   ├── pdf/
│   └── generation/
├── prompts/                    registry exported from the DB for review in Git
├── docs/
│   ├── plans/core/             this document
│   ├── schema/                 the Production reference
│   ├── architecture/
│   ├── ADR/
│   └── runbooks/
├── docker-compose.yml
└── README.md
```

## 12. The Local Models

### 12.1 The tags — verified

These tags were verified against `ollama.com` and `ai.google.dev` on 2026-08-17.
**The v1.0 tags are correct and need no correction:**

```bash
ollama pull embeddinggemma:300m-qat-q4_0    # 239 MB — 2K context
ollama pull gemma4:e2b-it-qat               # the working model
```

The available Gemma 4 family: `e2b`, `e4b`, `12b`, `26b`, `31b` — multimodal (text + image),
with a 128K–256K context and tunable thinking modes.

### 12.2 EmbeddingGemma — critical details missing from v1.0

| Property | Value | Impact |
|----------|-------|--------|
| Dimensions | **768**, with Matryoshka → 512 / 256 / 128 | 512 saves 33% of storage — **measure before adopting** |
| Context | **2K tokens only** | Long texts are truncated — truncation cases must be logged |
| **The prefixes** | **Mandatory** | Omitting them measurably degrades retrieval quality |

The correct forms:

```text
# For similarity and duplication (symmetric — used on both sides)
task: sentence similarity | query: {text}

# For document passages in RAG
title: {heading | "none"} | text: {chunk}

# For RAG queries
task: search result | query: {text}
```

**A mandatory rule:** the prefix is part of the embedding contract. Changing it **invalidates every stored vector**.
So every vector row carries an `embedding_config_version` covering (model tag + prefix template + dimension
+ normalization). Without it, the first prefix change will produce silently wrong comparisons.

### 12.3 The memory budget on 16 GB

> **Updated 2026-08-23 — this table is a reference, not a gate.** There is no acceptance criterion on a memory number in any
> project. The actual measurement on the machine (2026-08-23) gave **5,132 MiB** for the full stack with both
> models loaded — far below the estimate below — and **~90%** of it is the two models. Every other component is smaller
> than its estimate by a full order of magnitude: MySQL **18.6 MiB** rather than ~1 GB, Postgres **394.7 MiB** (the host RSS
> of OrbStack) rather than ~1.5 GB, Laravel **13.3 MiB**, and FastAPI **58.8 MiB**. The practical consequence:
> any performance work belongs to the **pipeline** (batches, filtering, the number of model calls), not to database tuning.
> The manual steps for when the machine feels slow are in `docs/runbooks/memory-check.md`.

| Component | Estimate |
|-----------|----------|
| macOS + editor + browser | 4–6 GB |
| Ollama + `gemma4:e2b-it-qat` | ~3 GB |
| Ollama + `embeddinggemma:300m-qat-q4_0` | ~0.3 GB |
| Postgres (Docker, `shared_buffers=512MB`, capped at 1.5 GB) | ~1.5 GB |
| MySQL (Docker, during the ETL only) | ~1 GB |
| Laravel + queue worker | ~0.4 GB |
| FastAPI | ~0.3 GB |
| **Total** | **~11–13 GB** |

The practical consequence:

- `e2b-it-qat` is the permanent working model.
- `e4b` (~5.5 GB) and `12b` **do not run alongside the full stack**. They are measured in **isolated sessions**:
  Docker, the browser, and the workers stopped.
- Ollama settings:

```bash
OLLAMA_MAX_LOADED_MODELS=2      # chat + embed together only
OLLAMA_NUM_PARALLEL=1
OLLAMA_KEEP_ALIVE=5m
```

- `num_ctx=4096` as the baseline. **The v1.0 advice is correct, and the reason deserves spelling out:** Gemma 4 supports 128K,
  but the KV cache at a 128K context exceeds the size of the model weights themselves. A small context is a memory decision, not stinginess.

### 12.4 The benchmarking protocol — the embedders first

v1.0 benchmarks the generative models and does not benchmark the embedders. **That is backwards:** the quality of duplicate detection — the
heaviest project in the program — is governed by the embedder, and the LLM only ever sees what the embedder sends it.
A weak embedder means correct pairs that are never shortlisted, and no LLM can rescue them.

**Embedder benchmark (on 400 human-labelled pairs):**

| Model | Recall@20 | Precision@T | Storage | Time/1K |
|-------|-----------|-------------|---------|---------|
| `embeddinggemma:300m-qat-q4_0` (768) | | | | |
| The same, truncated to 512 (Matryoshka) | | | | |
| `bge-m3` | | | | |
| `multilingual-e5-large` | | | | |

The decisive criterion: **Recall@20 on Arabic duplicate pairs.** Shortlisting cannot be compensated for later.

**Generative model benchmark:**

| Test | e2b | e4b | 12b |
|------|-----|-----|-----|
| Duplicate-verdict accuracy (against human labels) | | | |
| JSON Schema compliance (% with no retry) | | | |
| Arabic quality (trainer assessment) | | | |
| Source grounding | | | |
| Median response latency | | | |
| Resident memory | | | |

Choosing a different model per task (say e2b for classification, e4b for generation) is permitted — **after** the benchmark, not before.

## 13. Throughput & Capacity Budgets

The most important section that was entirely absent from v1.0. On 16 GB, capacity is a real constraint, not a detail.

> **Updated 2026-08-26:** the §6 pack ran in full against the fixed 2026-08-07 snapshot. Full
> results: `source_snapshots.profiling_results` (run `id=3`); the generated summary:
> `docs/reports/p1-profiling.md`. The measured bank is **29,142 questions** (28,747 active,
> 395 soft-deleted) — the estimates below still say 25,000; every count in this section is now
> the planning-time guess, not the measurement.
>
> The three findings §6.3 and §16 named as blocking (FR-061/FR-062/FR-063):
>
> - **Multi-key** (queries 3+4): 34 questions have `correct_option_count > 1` (33 at 2, 1 at 4 —
>   0.118% of active questions). **Operator decision (2026-08-26): data-entry errors, not a
>   supported question type** — a valid question has exactly one correct option.
>   `answer_key_state = multi_key` is a review flag, never an answerable item; nothing is repaired
>   or deleted in P1. Points values outside {0, 1} (2, 4, 5, 7, 8, 20, 25 — 344 options, mostly
>   trainer data-entry) do not change correctness: any option with `points > 0` is still the
>   correct one.
> - **Enrolment table** (queries 15+16): **`course_order` is enrolment** (71,228 rows, 28,292
>   distinct users, 228 courses) — **not** `course_user` (249 rows, 17 distinct users, all 17
>   split across trainer/user roles per query 16). Operator confirmed 2026-08-26:
>   `course_user` is an internal trainer/staff-course assignment table, not student enrolment.
>   P5/P6 planning builds on `course_order`.
> - **Broken-question rate** (query 3): `correct_count = 0` on 31 of 28,747 active questions —
>   **0.108%**, well under the 2% threshold (FR-063). The dedup track and this feature's scope
>   are unaffected; no re-scoping was triggered.

### 13.1 The embeddings

```text
25,000 questions × 2 embeddings (stem + full) = 50,000 calls
embeddinggemma 300m q4 on an M1 Pro ≈ 30–80/second
⇒ 10–30 minutes.  Entirely safe.
```

### 13.2 The LLM verdict — the real constraint

```text
top-K = 20 per question over 25K  →  ~250,000 undirected pairs
after excluding exact matches and applying a similarity floor  →  an estimated 20,000–60,000 pairs

e2b, a short JSON output (~150 tokens)  ≈  3–6 seconds/pair
30,000 pairs × 4 seconds  =  ~33 continuous hours
```

**The conclusion: it is not possible to run every candidate through an LLM.** Hence the banded strategy:

| Band | Handling | Cost |
|------|----------|------|
| Exact hash match | Automatic cluster, no LLM | Zero |
| `sim ≥ T_high` | Automatically clustered as `probable_duplicate` + a human spot-check on a 5% sample | Zero LLM |
| `T_low < sim < T_high` | **LLM verdict** — this is the only band | Target: ≤ 5,000 pairs ≈ 6 hours (an overnight batch) |
| `sim ≤ T_low` | Dropped | Zero |

`T_high` and `T_low` are **not fixed now** — they are calibrated on the evaluation set to hit the target in §21.
This is the "uncertainty band," and it is what makes the project finite rather than open-ended.

**Operating rules:** heavy batches run overnight; a `--low-memory` mode stops the worker during measurement.

### 13.3 The human review budget

| Task | Volume | Time/unit | Total | Reviewer |
|------|--------|-----------|-------|----------|
| Duplicate evaluation set | 400 pairs | 45–90 s | **5–10 hours** | Moderator + a trainer for the final verdict |
| Reviewing uncertain-band pairs | ≤ 1,500 pairs | 30–60 s | **12–25 hours** | Moderator |
| Quality evaluation set | 300 questions | 60–120 s | **5–10 hours** | Trainer |
| Conflicting-duplicate escalation | As discovered | 2–5 min | Variable — **high priority** | Trainer |
| Publishing approval (P6) | 1–3/day | 2–3 min | **~15 min/day** | Moderator |
| Draft review (P9) | 100 tasks | 3–8 min | **5–13 hours** | Trainer |

**Approximate total: 30–60 hours of human work spread across the program.** That is a manageable number if it is
scheduled, and a fatal one if it is assumed. Therefore: **scheduled review slots, not review on demand.**

**Reducing the volume with active learning:** after the first 150 labelled pairs, the thresholds are retrained, and humans
review only what is close to the decision boundary or where the LLM disagreed with the embedder. This roughly halves the review
volume for the same calibration accuracy.

### 13.4 pgvector

```text
50,000 vectors × 768 dimensions × 4 bytes ≈ 154 MB
```

- A small size: **an exact scan is sufficient to begin with — no index.**
- HNSW (`m=16, ef_construction=64`) is added only when interactive latency requires it.
- `vector(768)`, normalized to unit length, and cosine distance (`vector_cosine_ops`).
- `halfvec(768)` halves the storage if needed.
- If Matryoshka 512 holds up in the benchmark → a free 33% saving.

## 14. Data Protection, Privacy, and Ownership

### 14.1 The local Production copy — mandatory rules

The copy on the Mac contains real personal data:
`users` (emails, phone numbers), `orders` (partial card data), `certificates.id_number`
(**national ID**), `complaints`, `social_providers`.

```text
[ ] Stored on a FileVault-encrypted disk.
[ ] Never inside the repository folder.
[ ] Never in a folder synced to iCloud / Dropbox / Google Drive.
[ ] Its path is added to the global gitignore.
[ ] Fixed at snapshot_taken_at = 2026-08-07 for the whole program — no refresh, no
    cadence, no age gate (operator decision 2026-08-25). The date is recorded and
    displayed as context, never used as a threshold.
[ ] Never copied to any other machine or to the VPS.
```

### 14.2 The ETL allowlist

The Lab **never receives PII**. The ETL works from an explicit allowlist, not from exceptions.

> **Where it is enforced (updated 2026-08-23):** the lists are enforced in the **application layer** — `config/lab.php → source_tables` (what is copied) and `profile_tables` (what is read as aggregates only), plus `SourceReader`, which refuses by name any table outside the two lists, on top of an `injazedu` connection with no write host and a listener that throws on any statement that is not a read. There is no dedicated MySQL user and no `GRANT`; see `docs/ADR/ADR-021.md` for the decision and the accepted risk.

**Fully allowed:**
```text
categories, courses (metadata only), chapters, lectures (title and order only),
quizzes, sections, questions, options, quiz_files
```

**Allowed after anonymization:**
```text
results, question_result
  → user_id is replaced by student_ref = HMAC-SHA256(pepper, user_id)
  → the pepper lives in .env only, is never committed to Git, and is never stored in the Lab database
```

This allows counting each student's behaviour (attempts, progress, discrimination) **without knowing who they are** — and that is all the
analytics need. No model needs to know a student's name in order to analyse a question's performance.

**Allowed for aggregate reads only — and never copied (updated 2026-08-23):**
```text
course_user, course_order, orders, user_roles, roles, book_course
```

These six are read **as counts only** in the §6 pack (queries 15, 16 and 18), and they settle two questions
without which the program cannot move: which table actually records enrollment (`course_user` versus `course_order`),
and who the users in `course_user` are — students or trainers. Reading is allowed, copying is not. The guarantee that
remains is the guarantee that matters: **no PII column in the Lab database**, proven by `NoPiiInLabSchemaTest`.

**Forbidden to read and to copy alike:**
```text
users, book_order, coupons, certificates, complaints, complaint_responses,
social_providers, personal_access_tokens, paymob_logs, zoom_users, audits,
telescope_*, google_oauth_tokens, failed_jobs, settings
```

### 14.3 The Lab's public surface (P7) — no PII by design

- No login, no email, no phone number, no name.
- A random first-party session identifier, unlinked to any identity.
- Events are stored against the anonymous identifier only.
- Nothing is collected that is not used in a declared metric.

### 14.4 Prompt injection via documents

PDF and OCR text is **data, not instructions**. The books are semi-trusted sources, but the rule always applies:

- Retrieved chunks are placed in a designated data field with clear delimiters, never in the system instructions.
- The system prompt states explicitly that retrieved content is reference material only and that any instructions inside it are ignored.
- The generator's output is validated against a schema, and nothing in it is executed.

### 14.5 Intellectual property and the origin of the questions

This is a real risk that must be named. A bank of 25,000 questions in the domain of official examinations
**may contain questions copied verbatim from protected official material**.

The practical position:

- Record a `source_origin` for every question: `authored` / `book_derived` / `unknown` / `suspected_official`.
- The default is `unknown` — and nothing else is claimed without evidence.
- When any official material is available to the team, run a literal matcher to detect and flag the matches.
- Generation in P9 is grounded in the **licensed course books**, and the model is never asked to reproduce
  real exam questions.
- A legal review of the current bank's provenance is recommended. That is a management decision, not an engineering one, but the plan
  provides the data it would be based on.

### 14.6 Data durability — no local backups (updated 2026-08-23)

**Operator decision, 2026-08-23: there is no backup or restore requirement anywhere in the local program.**

The machine is a development environment, and the Production copy is **disposable** — it is renewed by taking a fresh copy, not
by restoring one. And everything in the Lab database is reproducible: re-run the import, re-run the pipeline.
A nightly backup on the same machine protects against disk loss alone, which is the least likely outcome here and the most expensive
in time.

The one exception is the **human review decisions** (P2 onward): they have no other source. Protecting them is a
matter of **moving to real operation** — a real database on real infrastructure — not a
local matter, and not an acceptance criterion in any project from P0 to P9.

---

# Part IV — The Projects

Every project follows the same template: goal, dependencies, inputs, scope, out of scope, Lab tables,
outputs, effort estimate, accept/reject thresholds, risks, acceptance criteria.

Effort estimates are in **focused working days for a single developer** — not calendar days.

---

## 15. P0 — AI Lab Foundation

### Goal
A unified local environment that allows everything after it to be built without touching Production — **with the smallest possible number of services**.

### Dependencies
None.

### Scope

```text
Laravel 12 + Filament        (native)
FastAPI                      (native, uvicorn)
PostgreSQL 17 + pgvector     (Docker, memory-capped)
MySQL 8                      (Docker, to read the Production copy only)
Ollama                       (native, Metal)
Queue worker                 (database driver — ADR-011)
Logging + health checks
```

### Out of scope
**Redis** (ADR-011) — **n8n** (ADR-012) — any connection to Production — any real data processing.

### Required health checks

```text
Laravel  → PostgreSQL
Laravel  → FastAPI
Laravel  → Queue (a job actually executes)
FastAPI  → PostgreSQL
FastAPI  → Ollama (chat)
FastAPI  → Ollama (embed)
Laravel  → MySQL snapshot (read-only)
pgvector → one vector saved and retrieved successfully
```

### Development rules

```text
[ ] All secrets in .env, and .env is never pushed to Git.
[ ] No Production credentials locally.
[ ] Ollama on 11434 locally only, never exposed.
[ ] PostgreSQL and MySQL are not exposed.
[ ] Read-only access to the local copy is enforced in the application in three layers, each of which
    blocks on its own (a connection with no write host · a listener that throws on any statement that is not
    a read · two allowlists: 11 tables for copying and 6 for aggregate reads) — ADR-021, §14.2 updated 2026-08-23.
```

### Outputs
A working stack — health endpoints — `.env.example` — `docker-compose.yml` — an installation README —
basic logs — a queue test — an Ollama test — an embedding test with the correct prefix.

### Effort estimate
**3–5 days**

### Accept/reject thresholds
- If Ollama does not stay stable with both models loaded → lower `num_ctx` or separate the embedding batches from
  the chat. This is a **runtime diagnosis**, not an acceptance gate (updated 2026-08-23).
- **The 13 GB gate was cancelled** (2026-08-23): there is no acceptance criterion on a memory number. The actual measurement put
  the stack at 5,132 MiB, and §12.3 became a reference rather than a gate. The manual steps are in
  `docs/runbooks/memory-check.md`.

### Risks
| Risk | Mitigation |
|------|------------|
| Docker Desktop consumes excessive memory | OrbStack, or native Postgres |
| The temptation to add services "because they will be needed later" | ADR-011/012 are written for exactly this reason |

### Acceptance criteria
```text
[ ] The stack starts predictably with a single command.
[ ] Laravel creates a job, and the worker executes it.
[ ] FastAPI calls Ollama and returns valid JSON.
[ ] A 768-dimension embedding is saved to pgvector and retrieved.
[ ] Restarting does not lose PostgreSQL data.
[ ] The MySQL copy is read and refuses writes.
```

---

## 16. P1 — Production Profiling & Question Mirror

### Goal
**Measure** the bank first, then create a faithful local mirror of it.

v1.0 started with "import 25 thousand questions." v2.0 starts with "find out what you actually have" — because every estimate in
this document rests on numbers that have not yet been measured.

### Dependencies
P0.

### Inputs
The local MySQL copy. The query pack in §6.

### Scope

**Phase 1 — Profiling (1–2 days):**
Run all of §6 through `php artisan lab:profile` — inside the three read-only layers, never a direct `mysql` client — and
**persist** the results: the full result set into `source_snapshots.profiling_results` (JSONB), and a summary generated from
that JSON into `docs/reports/p1-profiling.md`. The generated report is regenerable and never hand-maintained. It covers the
real count, correct-answer integrity, the option distribution, the HTML/image rate, the volume of behavioural data,
**and the resolution of the enrollment ambiguity** (queries 15/16). Update §13 with the real numbers.

Three findings — and only three — block downstream code, because each changes what gets built: the meaning of multi-key
(queries 3+4, which fixes `answer_key_state` and therefore `payload_hash`), the enrollment table (queries 15+16), and a
`correct_count = 0` rate above 2% (query 3), which reopens the scope below. Everything else in the pack is recorded and read,
and blocks nothing.

**Phase 2 — The ETL (4–6 days):**
MySQL → PostgreSQL, under the allowlist in §14.2, with:
- Production identifiers preserved exactly as they are.
- Soft-deleted rows copied with `source_deleted_at`.
- `correct_option_ids` and `option_index` derived by the rules in §5.1 and §5.2.
- `payload_hash` defined precisely: SHA256 over a normalized serialization (key-sorted JSON) of
  (`name`, `description`, `hint`, and the options ordered by `option_index` with `name` and `points`).
- `import_errors` records every anomaly instead of hiding it.
- Idempotent: re-running updates what changed and creates no duplicates.

**Phase 3 — The inventory console (2 days):**
A Filament screen showing the counts, the distributions, and the problems, and allowing navigation from a number down to the questions themselves.

### Out of scope
Arabic normalization (P2) — duplicate detection (P2) — any classification — any live connection to Production.

### Lab tables

```text
source_snapshots        (snapshot_taken_at, source_row_counts, notes)
source_courses          (+ category tree)
source_categories
source_quizzes          (course_id NULL ⇒ general)
source_sections         (+ stimulus fields — §8)
source_questions        (raw_text, source_deleted_at, payload_hash, source_origin)
source_question_options (option_index, points, is_correct_derived)
source_media            (from quiz_files — type, level, and path)
source_results          (pseudonymized student_ref)
source_item_stats       (per question x scope — from question_result, aggregated)
source_option_stats     (per option x scope — from question_result, aggregated)
import_runs
import_errors
```

**Note:** there is no `source_question_exam_links` — because the relation has a single parent (§2.1).
This is a direct correction of v1.0.

### Validation checks at import time

```text
Missing options            Empty question stem
Zero correct options       Multiple correct options
Duplicate option text      order ties
Broken/unbalanced HTML     Stem is only an image
Orphan section/quiz        Category orphan (parent_id missing)
Stimulus with no questions A question with no section
```

### Outputs
The written profiling report — repeatable ETL scripts — the tables populated — an inventory console —
a visible error log — **§13 updated with real numbers**.

### Effort estimate
**7–10 days**

### Accept/reject thresholds
- If the `correct_count = 0` rate is above 2% → **the dedup track stops**, and fixing
  the broken questions becomes the first deliverable handed to the team. A question with no correct answer affects a student right now.
- If `has_description` is below 30% → the explanation path in P9 starts from zero, and using it as few-shot examples
  is removed from the estimates.

### Risks
| Risk | Mitigation |
|------|------------|
| The local copy is dated 2026-08-07 by decision | `snapshot_taken_at` is recorded once and printed on every report and every screen, so every number is read in its own frame. Age is context, never a gate (§14.1) |
| Malformed HTML breaks parsing | Isolate the errors into `import_errors` and continue; never halt the batch |
| PII leaking through an ETL bug | An explicit allowlist + a test that fails if a forbidden column appears |

### Acceptance criteria
```text
[ ] The profiling pack has been run and its results persisted and read before the ETL
    derives any field that depends on them.
[ ] Every question is imported, and the Production id is preserved on every row.
[ ] Not a single row is modified in Production or in the local copy.
[ ] The import is repeatable and idempotent (payload_hash).
[ ] Validation errors are visible and filterable in the interface.
[ ] No PII in the Lab database — proven by an automated test.
[ ] Every number on the console is clickable through to the questions themselves.
```

---

## 17. P2 — Arabic Normalization & Duplicate Intelligence

### Goal
Detect the real duplication without deleting any data, **and within a finite compute budget**.

### Dependencies
P1.

### The text layers (preserved from v1.0 — correct)

```text
raw_text     ← exactly as it is in Production, never modified
clean_text   ← technical cleaning only: HTML, whitespace, Unicode. Meaning preserved
search_text  ← the comparison and search representation
```

`search_text` may apply: Unicode normalization (NFC), removing the tatweel `ـ`, removing diacritics, normalizing whitespace,
normalizing punctuation, unifying Arabic/Latin digits, normalizing selected Alef forms,
and stripping option labels where needed.

**A mandatory Arabic rule (preserved from v1.0):** transformations that change meaning are never applied.
`ة → ه` is **not** an acceptable general normalization.

### The cascade — with a budget

| Layer | Tool | Cost | Output |
|-------|------|------|--------|
| 0 | `question_text_hash` = SHA256(search_text) | Zero | Literal duplicates |
| 1 | `question_with_options_hash` = SHA256(search_text ⊹ the normalized ordered options) | Zero | Duplicates with formatting differences |
| 2 | Postgres `pg_trgm` + a GIN index on `search_text` | Low | Lexical candidates |
| 3 | pgvector top-K=20 over the embeddings | Medium | Semantic candidates |
| 4 | **The LLM, in the uncertain band only** | High — see §13.2 | A structured verdict |

**The embeddings** (with the mandatory prefix from §12.2):
```text
stem_embedding  ← the question alone
full_embedding  ← the question + the options
```
Both in the form `task: sentence similarity | query: {…}`, and every row carries an `embedding_config_version`.

**For questions attached to a shared passage (§8):** an excerpt of the passage is added, not the whole passage (the 2K limit).
Two identical questions on two different passages **are not duplicates** — a mandatory rule in the verdict.

### The verdict output (structured JSON)

```json
{
  "relation": "semantic_duplicate",
  "same_learning_objective": true,
  "same_correct_answer": true,
  "confidence": 0.91,
  "issues": [],
  "recommended_action": "group_under_canonical",
  "review_required": true
}
```

The relation types:
```text
exact_duplicate       formatting_duplicate   semantic_duplicate
same_objective_variant related_not_duplicate  conflicting_duplicate
not_related
```

### The urgent escalation path — `conflicting_duplicate`

**This is the most important immediate deliverable in the whole program, and v1.0 gave it no path.**

Two questions with the same text and two different correct answers = **a content error that affects students right now**. One of them
is wrong, and whoever answered it correctly was marked wrong (or the reverse).

```text
conflicting_duplicate detected
  → a high-priority queue for the trainers (it does not wait for the rest of the review)
  → the trainer's decision: which of the two answers is correct
  → a direct report to the team for correction in the Production admin
  → tracking: how many students were affected (from source_item_stats.n)
```

This is greatly reinforced by P3: **a negative discrimination coefficient** points at the same question from another, independent angle.
The intersection of the two signals is very strong evidence of a wrong key.

### The clusters — no deletion

```text
duplicate_candidates       (pair, sim scores, band, llm verdict)
duplicate_clusters         (canonical_question_id, relation_type, status)
duplicate_cluster_members
duplicate_reviews          (human decision, reviewer, timestamp, notes)
```

**Why we do not delete:** `question_result` is linked to `questions` by an FK, and the questions are linked to historical
tests, results, and analytics. Deleting corrupts the history.

### The review screen (Filament)

```text
Question A          |  Question B
Options + answer    |  Options + answer
Similarity: 0.94    |  AI verdict: semantic_duplicate (91%)
Stats A: p=0.71     |  Stats B: p=0.34    ← from P3 when available
[Same] [Valid variant] [Not a duplicate] [Conflict!] [Skip]
```

Showing the P3 statistics inside the review screen speeds up the human decision considerably: a large p-value gap between
"two identical questions" means they are not in fact identical in the students' eyes.

### Out of scope
Deleting any question — modifying Production text — classification and taxonomy (P5) — generation (P9).

### Outputs
An Arabic normalizer with unit tests — hashes — a trgm index — complete embeddings — candidates —
clusters — a review screen — **a 400-pair human-labelled evaluation set** — a threshold calibration report —
the conflicting-duplicate queue.

### Effort estimate
**10–15 days** (plus 17–35 hours of human review — §13.3)

### Accept/reject thresholds
- **The gate:** on the 400-pair set, **precision ≥ 0.90 at recall ≥ 0.70** must be achieved
  for the (exact + semantic duplicate) class **before** running over the whole bank.
- If that is not achieved with EmbeddingGemma → measure `bge-m3` and `multilingual-e5-large` (§12.4)
  **before** touching the prompts. The problem is usually in the shortlisting, not in the verdict.
- If the number of uncertain-band pairs exceeds 8,000 → tighten the thresholds, because the budget then exceeds 10 LLM hours.

### Risks
| Risk | Mitigation |
|------|------------|
| The embedder's Arabic quality is not good enough | Measure before committing; predefined alternatives |
| An explosion in the number of pairs | Explicit bands + a declared ceiling + a `log` of what was excluded |
| Human reviewers disagreeing | Measure inter-rater agreement; disagreements go to a trainer |
| A prefix change silently invalidating the vectors | `embedding_config_version` is mandatory |
| False positives from passage-based questions | The "different passage ⇒ not a duplicate" rule in the verdict and in the blocking |

### Acceptance criteria
```text
[ ] Literal duplicates are detected deterministically and without an LLM.
[ ] Embeddings exist for every valid question, with the correct prefix and a config version.
[ ] Nearest-neighbour search in pgvector works.
[ ] The LLM sees only uncertain-band pairs — proven by a counter.
[ ] A human can override the AI's verdict, and the decision is stored with its author.
[ ] No source question is deleted.
[ ] The evaluation metrics (precision/recall/human agreement) are recorded and published.
[ ] Every conflicting duplicate is in a high-priority queue with the number of affected students.
```

---

## 18. P3 — Item Statistics from Existing Results

### Goal
Extract question quality from **the real student behaviour that already exists** — with SQL alone, no AI,
no new events, and no modification to Production whatsoever.

### Why this project comes early
v1.0 buried it in Project 8 behind six projects. But it is:

- **Fully available today** — the data is already in `results` and `question_result`.
- **In need of no AI, no embeddings, no PDF, and no taxonomy.**
- **An immediate finder of wrong keys** — a negative discrimination coefficient means the top performers get it wrong, which means the key is probably wrong.
- **The highest value per unit of effort in the entire program.**

### Dependencies
P1 (Phase 2 — `source_results`, plus `source_item_stats` and `source_option_stats`).

**Changed 2026-08-26 (ADR-022):** answer events are no longer mirrored row-for-row. P3 receives the
per-question and per-option aggregates instead — `n`, `n_correct`, `p_value`, and the corrected-total
`m1`/`m0`/`sd` that `r_pbis` is computed from. Nothing P3 needs was lost: every formula below is a
`GROUP BY` over the raw rows, and those rows remain in the frozen 2026-08-07 snapshot, where
recomputing a different slice costs one ~5 s query.

### The metrics and the formulas

**Difficulty (p-value):**
```text
p = (number of answers on the question with points > 0) / (total answers on the question)
```

**Distractor distribution:**
```text
for each option_id: its share of the total answers on the question
```

**Discrimination (point-biserial):**
```text
r_pbis = ((M₁ − M₀) / SD_total) × √(p × q)

M₁ = mean total score of those who answered correctly
M₀ = mean total score of those who answered incorrectly
p  = the correct-answer rate,  q = 1 − p
SD_total = the standard deviation of the total scores
```

**An important correction:** use the total score **minus the item's own score** (the corrected total),
otherwise the coefficient inflates itself. This is a detail that is often forgotten and that corrupts the ranking.

**Minimum counts:**
```text
n < 10   → no metric is published at all
n < 30   → p-value only, no r_pbis
n ≥ 30   → the full set of metrics
```

### Flagging rules

| Rule | Meaning | Priority |
|------|---------|----------|
| `r_pbis < 0` | **The top performers get it wrong ⇒ the key is probably wrong** | **Urgent** |
| A distractor chosen more often than the key | A suspect key | **Urgent** |
| `p < 0.20` | Very hard, or wrong | High |
| `p > 0.95` | Very easy / does not discriminate | Medium |
| `0 ≤ r_pbis < 0.10` | Does not discriminate between levels | Medium |
| A distractor chosen by under 2% | A dead distractor — rewrite it | Low |
| A wide p-value spread inside a duplicate cluster | The cluster is not really a duplicate | Medium |

### The intersection with P2 — the strongest signal in the program

```text
A question with a negative r_pbis       (P3 — behavioural evidence)
        ∩
A member of a conflicting_duplicate cluster  (P2 — textual evidence)
        ⇓
A very high probability of a wrong key — top priority for the trainer
```

Two entirely independent signals agreeing on the same question. That is far stronger than either one alone.

### The AI's role
**None in the computation.** Later (after P4) the LLM may **phrase** the report for the trainer,
grounded in the computed numbers, without computing or inventing a number (ADR-009).

### Out of scope
Answer time, abandonment, drop-off question, answer changes — **all unavailable** (§7.2).
Any modification to Production. Any judgment about a student.

### Lab tables
```text
item_statistics       (question_id, n, p_value, r_pbis, computed_at, snapshot_id)
option_statistics     (option_id, selection_rate, n)
quiz_statistics       (quiz_id, starts_proxy, mean, median, sd, n)
item_flags            (question_id, flag_type, severity, evidence_json)
```

### Outputs
An SQL/Python pack for the computations — a Filament console (sorted by flag and priority) — a
"questions needing urgent review" report — an export for the trainers — a coverage report showing **how much of the bank has enough data**.

### Effort estimate
**4–6 days**

### Accept/reject thresholds
- If fewer than 20% of the bank has `n ≥ 30` → the project remains valuable but its scope is stated explicitly,
  and it is not presented as an assessment of the whole bank.
- Every number must be reproducible from the raw rows; a test verifies that on a sample.

### Risks
| Risk | Mitigation |
|------|------------|
| Small answer counts producing misleading numbers | Explicit minimum counts + always showing `n` next to every number |
| Mixing repeated attempts | Identify the first attempt by ordering `created_at` and report both |
| Different tests having different difficulty | `r_pbis` within a single test only, never across tests |
| The numbers being read as a judgment on a trainer | Reports are at the question level, and are never sorted by trainer |

### Acceptance criteria
```text
[ ] Every metric is reproducible from the raw rows.
[ ] The minimum counts are applied, and n is shown with every number.
[ ] Questions with negative discrimination are ranked in an urgent report.
[ ] The share of the bank covered by data is declared explicitly.
[ ] The trainer can navigate from a number to the question and review it.
[ ] No metric is computed by an LLM.
```

---

## 19. P4 — Question Quality Audit

### Goal
Question quality **independent of the taxonomy** — because quality needs no classification, while coverage does.
(This splits what was merged into Project 4 in v1.0.)

### Dependencies
P1 and P2. Benefits greatly from P3.

### Layer one — deterministic checks (no LLM)

```text
The question is not empty          A correct answer exists
The answer belongs to the options  No textually duplicated options
The option count is consistent     Sound HTML
The relations are valid            No answer leakage in the question text (a textual check)
Question and option lengths are sane   No "all of the above" option with a multi-key
```

### Layer two — AI review

Gemma 4 inspects what cannot be checked programmatically: ambiguity, the possibility of multiple correct answers, weak distractors,
answer leakage, clumsy Arabic, missing context, an explanation that contradicts the answer.

```json
{
  "language_quality": "good",
  "single_correct_answer": true,
  "ambiguity_detected": false,
  "weak_distractors": ["D"],
  "answer_leakage": false,
  "source_supported": null,
  "recommended_status": "needs_minor_review",
  "issues": [{"type": "weak_distractor", "message": "..."}]
}
```

**The AI never gives a question final approval** (ADR-005).

### Layer three — the behavioural evidence from P3
The P3 flags are merged in as an independent input. When the AI and the statistics agree on the same question, the priority rises.
When they disagree, the disagreement is shown to the human reviewer — and that in itself is a useful signal.

### Prioritized ordering
25,000 questions are not reviewed. They are ranked:
```text
1. A conflicting key (P2 ∩ P3)
2. No correct answer
3. Negative discrimination
4. The AI sees multiple correct answers or ambiguity + poor behavioural data
5. Dead distractors
6. The rest — a sample only
```

### Out of scope
Classification, taxonomy, and coverage (P5) — modifying Production text — generation (P9).

### Lab tables
```text
quality_checks       (question_id, check_type, passed, details_json)
ai_quality_reviews   (question_id, prompt_version, model, output_json, latency_ms)
quality_decisions    (question_id, reviewer_id, decision, notes, created_at)
```

### Outputs
The deterministic check engine — a quality review prompt (exported and versioned) — **a 300-question evaluation set
with human labels** — an AI/human agreement report — a priority-ordered review queue — the quality console.

### Effort estimate
**6–9 days** (+ 5–10 hours of trainer review)

### Accept/reject thresholds
- **The gate:** agreement ≥ 0.80 with the human labels on the `single_correct_answer` and
  `ambiguity_detected` fields before the AI flags are shown to trainers as recommendations.
- If that is not achieved → only the deterministic checks and the P3 flags are shown, and the AI output is hidden until the prompt
  or the model improves. **Showing unreliable flags to a trainer destroys trust in the whole system**, and that loss is more expensive
  than the flag's benefit.

### Risks
| Risk | Mitigation |
|------|------------|
| Over-trusting the AI flags | The gate above + labelling every AI output as a "recommendation" |
| Flooding the trainers | Strict prioritization + a daily ceiling |
| Arabic fluency being judged by a model | The human assessment is the judge (v1.0 §20 — preserved) |

### Acceptance criteria
```text
[ ] The deterministic checks run first and produce results before any AI call.
[ ] The AI output conforms to a JSON Schema and is validated before acceptance.
[ ] Human overrides are stored with their author and their reason.
[ ] The 0.80 agreement gate is measured and recorded.
[ ] The queue is ordered by priority, not by id.
[ ] AI-predicted difficulty is labelled "predicted" and kept distinct from the p-value measured in P3.
```

---

## 20. P5 — Taxonomy & Coverage Map

### Goal
An approved classification for the bank, and a gap map that will steer generation later.

### Why this is an independent project
**There is no taxonomy at all in Production** — no topic, no skill, no learning objective, no cognitive level,
no difficulty. v1.0 made three projects depend on a taxonomy for which it assigned neither an owner nor a date.

**The rule (preserved from v1.0 and correct):** the team approves the taxonomy first, **then** the AI classifies
within a closed list. The model is not allowed to invent branches.

### Dependencies
P1, P2 (to classify at the cluster level rather than the individual question — far cheaper).
**And an external human approval** — that is the critical path.

### Phase 1 — Human authoring (no programming)

A human deliverable, authored by subject-matter experts, and version-numbered:

```text
Specialization → Topic → Subtopic → Skill → Learning Objective
Cognitive Level   (e.g. recall / understanding / application / analysis)
Difficulty Bands  (easy / medium / hard — defined by p-value boundaries from P3, not by intuition)
```

**An important point:** after P3 we have **measured** difficulty for real. So the difficulty bands are defined
by real p-value boundaries instead of a human guess — a direct benefit of pulling P3 forward.

Production's `categories` is used as the root of the specializations (with care around `parent_id` — §9).

### Phase 1b — The Qiyas / STEP / IELTS specification

v1.0 uses "Qiyas-style" everywhere without defining it. What is needed is a written specification for each test family:

```text
Question count        The sections and their distribution
Timing                The weight of each topic
Difficulty mix        The question formats used
Scoring method        Are there shared passages?
```

It is authored by someone who knows the real test, and stored with a version number.
**Without this specification, "Qiyas-style" is a marketing phrase, not an engineering spec.**

### Phase 2 — AI classification
Classification within the closed list, **at the cluster level** (the canonical) rather than every question — which saves
a large share of the LLM calls.

### Phase 3 — The coverage map

```text
Topic: Teaching Strategies

Approved questions: 850

Recall:      430        Literal duplicates:    110
Understand:  260        Semantic duplicates:    70
Apply:       140        Needs review:           95
Analyse:      20        Has an approved source: 320
                        No source:             530

Measured difficulty (from P3):  easy 40% / medium 45% / hard 15%
The gap: a shortage of application and analysis, and a shortage of hard items
```

### Outputs
A version-numbered `taxonomy` — the test specifications — the bank classified — the gap map —
the coverage console — the difficulty bands defined by p-value boundaries.

### Effort estimate
**4–6 days of programming** + **2–4 weeks of elapsed time** waiting for the human authoring (the critical path).

**A scheduling recommendation:** the request to author the taxonomy starts **during P2**, not at P5. Otherwise the
program will stall waiting on a human decision that could have matured in parallel. This is the most important scheduling note in the document.

### Accept/reject thresholds
- With no human-approved taxonomy → **P5 does not start**, and the AI is not allowed to invent a classification.
  P4, P3, and P6 continue without it.
- If a test specification cannot be obtained → build a preliminary one from the actual tests present
  in Production (query 8 in §6.1), and label it "inferred, not approved."

### Risks
| Risk | Mitigation |
|------|------------|
| The taxonomy never gets authored | Start early; accept an incomplete v0.1 rather than waiting for perfection |
| The AI going outside the list | A programmatic check: any classification outside the list is rejected automatically |
| One classification forced onto a multi-faceted question | Allow more than one topic, with one primary |

### Acceptance criteria
```text
[ ] The taxonomy is human-approved and version-numbered before any AI classification.
[ ] Every AI classification is inside the closed list — proven by a programmatic check.
[ ] The difficulty bands are defined by a p-value measured in P3, not by intuition.
[ ] The gaps are identifiable and exportable as an input to P9.
[ ] At least one test specification is written and approved.
```

<!-- SENTINEL:PART4B -->
