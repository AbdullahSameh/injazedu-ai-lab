# InjazEdu AI Assessment & Engagement Lab
## Full Implementation Plan — Local Development First

> **HISTORICAL — superseded by v2.0.** The governing plan is
> `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md`. This file is retained
> as background and as the record of what changed and why (v2.0 §2 is the diff). **Where the two
> disagree, v2.0 governs.** Nothing in this file is a requirement.
>
> Known-obsolete here: the `explanation` / `correct_answer` / `exam_ids` fields, which do not exist
> in the Production schema · the Project 6 assessment builder, impossible under Production
> read-only · `[ ] PostgreSQL backup configured` (§ near line 3061) — backups are cancelled
> program-wide (v2.0 §14.6) · the snapshot/import ordering in Phases A–B — the local copy is fixed
> at 2026-08-07 and never refreshed (v2.0 §14.1).


**Version:** 1.0  
**Date:** 2026-08-07  
**Primary development environment:** macOS  
**Future deployment target:** Hostinger VPS for the AI Lab + DigitalOcean for `injazedu.co` Production

---

# 1. Executive Summary

The goal of this program is to build a set of interconnected projects around `injazedu.co` that achieve two things at the same time:

1. Develop real features that serve the platform and raise the quality of its assessments and engagement.
2. Use the platform as a practical laboratory for learning and applying:
   - Local LLMs
   - Ollama
   - RAG
   - Embeddings
   - Vector Search
   - AI Evaluation
   - PDF / Document Understanding
   - Arabic NLP
   - n8n Workflows
   - Event-driven systems
   - Analytics
   - Human-in-the-loop AI

The platform already holds roughly **25,000 questions** spread across public tests and course-specific tests, and duplicates exist among them. The starting point must therefore not be creating new questions, but turning the existing question bank into a clean, analyzable, extensible **Question Intelligence Layer**.

The overall order:

```text
0. AI Lab Foundation
        ↓
1. Question Data Mirror & Inventory
        ↓
2. Arabic Cleaning & Duplicate Intelligence
        ↓
3. PDF Knowledge & Source Library
        ↓
4. Question Quality Audit & Coverage Map
        ↓
5. AI Trainer Assessment Copilot
        ↓
6. Qiyas-style Assessment Builder
        ↓
7. Telegram Engagement Workflows
        ↓
8. Assessment Intelligence Dashboard
        ↓
9. Student Engagement & Personalized Practice
```

The foundational rule of the system:

```text
Production serves students.
AI Lab prepares, analyses and recommends.
Humans approve.
```

---

# 2. Does Local Development Change the Plan?

Yes, but the change is in **infrastructure and integration strategy** more than in architecture.

During the local development stage:

- We do not need the Hostinger VPS.
- We do not need to expose any AI service to the internet.
- We do not need a direct connection from the development machine to the Production MySQL database.
- We start from a safe snapshot / export of the question data.
- We use Ollama locally on the Mac.
- We use Docker Compose for the remaining infrastructure services.
- We use the local filesystem for PDF files at first.
- Telegram can be tried later using a test bot and a private channel.
- The Signed Internal API with Production is built and tested after the local workflows have proven themselves.

## The Most Important Change

During local development:

```text
Production MySQL
      │
      │ Manual / Controlled Export
      ▼
Local Import File
JSON / CSV / SQL-derived JSON
      │
      ▼
Local AI Lab
```

Later:

```text
DigitalOcean Production
      │
      │ Signed HTTPS Internal API
      ▼
Hostinger AI Lab
```

**Do not let the local AI Lab connect directly to the Production MySQL database with open privileges.**

---

# 3. Architecture Overview

## 3.1 Local Development Architecture

The recommended architecture on the Mac is hybrid:

- Ollama runs native on macOS.
- PostgreSQL + pgvector inside Docker.
- Redis inside Docker.
- n8n inside Docker.
- Laravel + Filament can run native or inside Docker.
- FastAPI can run native inside a Python virtual environment, or inside Docker.
- Files are stored locally in private storage during development.

```text
Mac — Local Development
┌───────────────────────────────────────────────────────────┐
│                                                           │
│  Laravel + Filament AI Lab                               │
│  ├── Question Inventory                                  │
│  ├── Duplicate Review                                    │
│  ├── PDF Library                                         │
│  ├── Trainer Copilot                                     │
│  ├── Approval Workflows                                  │
│  └── Analytics UI                                        │
│                  │                                        │
│                  ▼                                        │
│  FastAPI AI Service                                      │
│  ├── Arabic Processing                                   │
│  ├── PDF Extraction                                      │
│  ├── Embeddings                                          │
│  ├── Similarity                                          │
│  ├── LLM Evaluation                                      │
│  └── Question Generation                                 │
│                  │                                        │
│        ┌─────────┼──────────┐                             │
│        ▼         ▼          ▼                             │
│  PostgreSQL   Redis    Ollama Native                      │
│  + pgvector            ├── EmbeddingGemma                 │
│                        └── Gemma 4                        │
│                                                           │
│  n8n                                                     │
│  ├── Scheduling                                          │
│  ├── Telegram                                            │
│  ├── Approvals                                           │
│  └── Notifications                                       │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

## 3.2 Future Production Architecture

```text
DigitalOcean — Production
┌────────────────────────────────────────────┐
│ injazedu.co                                │
│                                            │
│ Laravel + MySQL                            │
│ Students / Courses / Enrollments           │
│ Approved Questions                         │
│ Exams / Attempts / Results                 │
│ Zoom / Bunny / Existing Features           │
│                                            │
│ Signed Internal API                        │
└────────────────────┬───────────────────────┘
                     │ HTTPS
                     ▼
Hostinger — AI Lab
┌────────────────────────────────────────────┐
│ Laravel + Filament                         │
│ ├── Question Review                        │
│ ├── Duplicate Review                       │
│ ├── PDF Library                            │
│ ├── Trainer Copilot                        │
│ └── Approval Workflows                     │
│                                            │
│ FastAPI                                    │
│ ├── PDF Processing                         │
│ ├── Arabic Normalization                   │
│ ├── Embeddings                             │
│ ├── Duplicate Detection                    │
│ ├── AI Evaluation                          │
│ └── Question Generation                    │
│                                            │
│ PostgreSQL + pgvector                      │
│ Redis                                      │
│ Ollama                                     │
│ n8n                                        │
└────────────────────────────────────────────┘
```

---

# 4. Responsibility Boundaries

## 4.1 InjazEdu Production

Production remains the source of truth for the core data that affects students.

Responsible for:

- Users
- Students
- Courses
- Enrollments
- Permissions
- Approved Questions
- Public / Private Tests
- Test Attempts
- Student Answers
- Results
- Course Access
- Payments
- Financial data
- Zoom
- Bunny.net
- Student-facing pages
- Telegram landing pages

A student does not depend on Ollama in order to start or finish a test.

---

## 4.2 AI Lab

Responsible for:

- Question Mirror
- Question Inventory
- Arabic text normalization
- Exact duplicate detection
- Semantic duplicate detection
- Embeddings
- Vector Search
- PDF processing
- Source Library
- Question classification
- Quality audits
- Coverage analysis
- AI-generated draft questions
- Trainer review
- Moderator review
- AI evaluations
- Prompt versions
- Model benchmarks
- Experiment logs
- Analytics reports

The AI Lab does not become the source of truth for subscriptions or student permissions.

---

## 4.3 n8n

n8n is an orchestrator, not a data processing engine.

It is used for:

- Scheduling
- Triggering API jobs
- Telegram publishing
- Telegram workflows
- Human approvals
- Notifications
- Scheduled reports
- Failure alerts
- Cross-system automation

And it is not used for:

- Processing 25,000 questions node-by-node.
- Creating embeddings for all the data inside one long workflow.
- Heavy PDF parsing.
- Heavy OCR.
- Heavy statistical computation.
- Holding core business logic.

The rule:

```text
n8n triggers and coordinates.
FastAPI and Queue Workers process.
Laravel manages business rules and human review.
```

---

# 5. Recommended Local Stack

## Application Layer

### Laravel + Filament

Used for:

- Admin / Lab UI
- Authentication
- Permissions
- Trainer workflows
- Moderator workflows
- Approval states
- Import management
- Review screens
- Audit logs
- Job status
- Business rules

---

### FastAPI

Used for:

- AI endpoints
- Arabic processing
- PDF processing
- OCR pipeline coordination
- Embedding generation
- Vector search helpers
- Duplicate classification
- AI quality evaluation
- Question generation
- Model abstraction

Do not put the main prompt logic inside n8n.

---

## Data Layer

### PostgreSQL + pgvector

Used for:

- Question mirror
- Embeddings
- Duplicate candidates
- Duplicate clusters
- Documents
- Pages
- Chunks
- AI reviews
- Generation jobs
- Model evaluation results
- Event analytics datasets

---

### Redis

Used for:

- Queues
- Job state
- Locks
- Rate limits
- Short-lived caching
- Idempotency keys

---

## AI Layer

### Ollama

Runs native on the Mac during development.

The reasons:

- On Apple Silicon, Ollama benefits from Metal GPU acceleration.
- Running it native separates the AI runtime from Docker networking.
- It simplifies model testing.
- It avoids adding an unnecessary virtualization layer around the model.

If the machine is an Intel Mac, Ollama runs CPU-only according to the current Ollama documentation.

---

# 6. Recommended Ollama Models

## 6.1 Embeddings Model

### Baseline

```bash
ollama pull embeddinggemma:300m-qat-q4_0
```

Used for:

- Question semantic similarity
- Duplicate candidate search
- Related-question search
- PDF chunk embeddings
- RAG retrieval
- Topic clustering experiments

Do not use Gemma 4 itself to create embeddings.

---

## 6.2 Generative Model

### Baseline Model

```bash
ollama pull gemma4:e2b-it-qat
```

Used for:

- Duplicate adjudication
- Question classification
- Arabic quality review
- Structured extraction
- Question generation
- Grounding checks
- Analytics explanations
- Difficult PDF page fallback
- Internal experiments

### Optional Benchmark Models

If the Mac has enough RAM / unified memory:

```text
gemma4:e4b-it-qat
gemma4:12b-it-qat
```

Do not choose a model based on its file size alone; you must measure:

- Real memory usage
- Tokens/sec
- Prompt latency
- Arabic quality
- JSON compliance
- Accuracy on the InjazEdu Eval Dataset

## Practical Local Guidance

### 8 GB memory

Start only with:

```text
EmbeddingGemma
Gemma 4 E2B QAT
```

And shut down unnecessary services during heavy tests.

### 16 GB memory

Very suitable for E2B.

E4B can be tried and its performance compared.

### 24–32 GB or more

You can benchmark:

```text
E2B
E4B
12B
```

But the production model is chosen by evaluation, not by size alone.

---

# 7. Ollama Local Configuration Principles

Start with a small context instead of using the model's max context:

```text
Development baseline context: 4096
```

Raise it when there is a clear reason.

Suggestions:

```text
Classification:
temperature = 0.0 – 0.2

Quality Evaluation:
temperature = 0.0 – 0.2

Question Generation:
temperature = 0.4 – 0.7
```

Every AI task must have:

```text
model_name
model_version/tag
prompt_version
input_hash
output
structured_output
latency
status
human_decision
created_at
```

---

# 8. Suggested Repository Structure

Laravel and FastAPI can live in separate repositories or in a monorepo.

For learning and ease of development, a monorepo is suitable at first:

```text
injazedu-ai-lab/
│
├── apps/
│   ├── lab/
│   │   └── Laravel + Filament
│   │
│   └── ai-service/
│       └── FastAPI
│
├── infrastructure/
│   ├── docker/
│   ├── postgres/
│   ├── n8n/
│   └── nginx/
│
├── data/
│   ├── imports/
│   ├── fixtures/
│   └── synthetic/
│
├── storage/
│   ├── documents/
│   └── extracted/
│
├── evals/
│   ├── duplicate-detection/
│   ├── question-quality/
│   ├── arabic/
│   ├── pdf/
│   └── generation/
│
├── prompts/
│   ├── duplicate-adjudicator/
│   ├── question-reviewer/
│   ├── question-generator/
│   └── classifier/
│
├── n8n/
│   └── workflows/
│
├── docs/
│   ├── architecture/
│   ├── ADR/
│   └── runbooks/
│
├── docker-compose.yml
└── README.md
```

---

# 9. Example Local Docker Compose Responsibilities

At first, Docker Compose should preferably contain:

```text
postgres
redis
n8n
```

And later it can add:

```text
laravel
fastapi
queue-worker
scheduler
```

Ollama should preferably stay native on the Mac during local development.

FastAPI inside Docker can reach the Ollama running on the host using the host-networking arrangement appropriate for Docker Desktop — or FastAPI can be run native during the first development stages to simplify the connection to:

```text
http://localhost:11434
```

---

# 10. Project 0 — AI Lab Foundation

## Goal

Prepare a unified local environment that makes it possible to build every following project without touching Production.

## Scope

Create:

- Laravel + Filament Lab
- FastAPI service
- PostgreSQL + pgvector
- Redis
- Ollama
- n8n
- Local private file storage
- Queue workers
- Logging
- Health checks

## Initial Health Checks

You must be able to execute:

```text
Laravel → PostgreSQL
Laravel → Redis
Laravel → FastAPI
FastAPI → PostgreSQL
FastAPI → Redis
FastAPI → Ollama
n8n → Laravel API
n8n → FastAPI API
```

## Required Development Rules

- All secrets inside `.env`.
- Do not push `.env` to Git.
- Do not use Production credentials locally except at a specific integration stage.
- The Ollama port does not need exposure to the internet.
- The PostgreSQL port is not opened publicly.
- Redis is not opened publicly.
- The local n8n instance does not use Production Telegram credentials at first.

## Deliverables

- Working local stack.
- Health endpoints.
- `.env.example`.
- Docker Compose.
- README setup.
- Basic logging.
- Queue test.
- Ollama test.
- Embedding test.

## Acceptance Criteria

```text
[ ] Stack starts predictably.
[ ] Laravel can create a job.
[ ] Queue executes it.
[ ] FastAPI calls Ollama.
[ ] Embedding can be saved to pgvector.
[ ] n8n can call a local API endpoint.
[ ] Restart does not lose PostgreSQL data.
```

---

# 11. Project 1 — Question Data Mirror & Inventory

## Goal

Create a safe local copy of the current question bank and analyse its state before using AI.

## Local Development Data Strategy

In the first stage, do not use a live API.

Do a controlled export from Production.

It is preferable to convert the data into a fixed JSON schema.

Example:

```json
{
  "production_question_id": 120,
  "course_id": 15,
  "exam_ids": [40, 42],
  "question_text": "...",
  "question_html": "...",
  "options": [
    {"key": "A", "text": "..."},
    {"key": "B", "text": "..."},
    {"key": "C", "text": "..."},
    {"key": "D", "text": "..."}
  ],
  "correct_answer": "B",
  "explanation": "...",
  "created_at": "...",
  "updated_at": "..."
}
```

## Never assume the existing schema is clean

The first import must record the problems instead of hiding them:

- Missing options
- Duplicate option keys
- Missing correct answer
- Invalid correct answer
- Empty question
- Broken HTML
- Question with image
- Question with deleted relation
- Question used in multiple tests
- Missing course relation
- Missing explanation

## Suggested Tables

```text
source_questions
source_question_options
source_question_exam_links
source_courses
import_runs
import_errors
```

Every record contains:

```text
source_system = injazedu_production
source_id
source_updated_at
imported_at
payload_hash
```

## Inventory Dashboard

Shows:

- Total questions
- Questions by course
- Questions by specialization
- Questions by test
- Questions without explanation
- Questions without source
- Questions with images
- Invalid questions
- Possible exact duplicates
- Recently changed questions

## n8n Role

In the local MVP:

It is not needed for the first import.

Later it can do:

```text
Manual Trigger
→ Laravel Import Endpoint
→ Check Job
→ Notify Result
```

## Future Production Sync

After the local import succeeds:

```text
Production Signed API
→ Cursor-based Sync
→ AI Lab Import
→ Queue
→ Upsert
→ Change Log
```

## Acceptance Criteria

```text
[ ] All 25K questions can be imported.
[ ] Every imported record preserves Production ID.
[ ] No Production records are modified.
[ ] Import is repeatable and idempotent.
[ ] Validation errors are visible.
[ ] Re-import updates changed records without creating duplicates.
```

---

# 12. Project 2 — Arabic Cleaning & Duplicate Intelligence

## Goal

Discover the real duplication inside the question bank without deleting the original data.

## Core Principle

Always keep the original text.

```text
raw_text
clean_text
search_text
```

## raw_text

The text exactly as it is in Production.

It is never modified.

## clean_text

Technical cleaning only:

- Remove unnecessary HTML
- Normalize whitespace
- Normalize Unicode
- Preserve meaning

## search_text

A representation dedicated to comparison and search.

It may apply:

- Unicode normalization
- Remove Tatweel `ـ`
- Remove Arabic diacritics for search
- Normalize whitespace
- Normalize punctuation
- Normalize Arabic / Latin digits into one representation
- Normalize selected Alef forms for search
- Remove answer labels when required

## Important Arabic Rule

Do not automatically apply linguistic transformations that may change meaning.

Example:

```text
ة → ه
```

Do not use this as a general normalization.

---

## Stage A — Exact Duplicate Detection

Without an LLM.

Create:

```text
question_text_hash
question_with_options_hash
```

Example:

```text
SHA256(search_question_text)
SHA256(search_question_text + normalized_options)
```

This detects:

- Exact copies
- Formatting differences
- Whitespace differences
- Simple punctuation differences

---

## Stage B — Lexical Similarity

Use:

- Character n-grams
- Token overlap
- Jaccard similarity
- Edit distance

This is an additional candidate-generation stage.

---

## Stage C — Semantic Similarity

Use EmbeddingGemma.

For every question:

```text
stem_embedding
full_question_embedding
```

### stem_embedding

Covers:

```text
Question stem only
```

### full_question_embedding

Covers:

```text
Question
+
Options
```

Store the vectors in pgvector.

Then:

```text
Question
→ Top-K nearest candidates
→ Similarity threshold
→ Candidate pairs
```

Do not compare every question against all 25,000 questions using an LLM.

---

## Stage D — Gemma 4 Adjudication

Gemma 4 does not search the whole bank.

It receives only a candidate pair.

Input:

```text
Question A
Question B
Options A
Options B
Correct Answer A
Correct Answer B
Optional topic metadata
Similarity scores
```

The output must be structured JSON:

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

## Relation Types

```text
exact_duplicate
formatting_duplicate
semantic_duplicate
same_objective_variant
related_not_duplicate
conflicting_duplicate
not_related
```

---

## Duplicate Clusters

Do not delete questions.

Create:

```text
duplicate_clusters
duplicate_cluster_members
duplicate_candidates
duplicate_reviews
```

Example:

```text
Cluster 52
├── Canonical #120
├── Duplicate #845
├── Duplicate #9120
└── Valid Variant #10455
```

## Why not delete?

Because a question may be linked to:

- Historical exams
- Attempts
- Student answers
- Results
- Analytics

## Human Review UI

Filament screen:

```text
Question A        Question B
Similarity        0.94

AI relation       semantic_duplicate
AI confidence     91%

[Same]
[Variant]
[Not duplicate]
[Conflict]
[Skip]
```

## Evaluation Dataset

Before adopting a threshold:

Create a human dataset such as:

```text
200 exact/near duplicate pairs
200 semantic duplicate pairs
200 non-duplicate related pairs
200 unrelated pairs
```

Then measure:

```text
Precision
Recall
False Positive Rate
False Negative Rate
Human agreement
```

## Acceptance Criteria

```text
[ ] Exact duplicates detected deterministically.
[ ] Embeddings generated for all valid questions.
[ ] pgvector nearest-neighbor search works.
[ ] LLM only reviews candidates.
[ ] Human can override AI classification.
[ ] No source question is deleted.
[ ] Evaluation metrics are recorded.
```

---

# 13. Project 3 — PDF Knowledge & Source Library

## Goal

Turn course books and PDF files into a trustworthy knowledge source that can be referenced when creating and reviewing questions.

## Supported Documents

At the start:

```text
PDF
```

Later:

```text
Slides
DOCX
Transcripts
Lecture material
```

## Document Lifecycle

```text
uploaded
→ validating
→ extracting
→ needs_ocr
→ extracted
→ needs_review
→ approved
→ superseded
```

## Suggested Tables

```text
documents
document_versions
document_pages
document_chunks
document_embeddings
document_processing_runs
document_review_actions
```

## Store Original File

For every file:

```text
document_id
course_id
trainer_id
original_filename
sha256
mime_type
file_size
page_count
version
status
created_at
```

## PDF Pipeline

```text
PDF Upload
→ File validation
→ SHA256
→ Detect text layer
→ Extract text
→ OCR only where required
→ Arabic cleanup
→ Page reconstruction
→ Structural chunking
→ Embeddings
→ Human validation
→ Approved Source
```

---

## Born-digital PDF

Use PyMuPDF / PyMuPDF4LLM.

It is preferable to keep:

```text
page number
text blocks
headings
tables
images metadata
bounding boxes when useful
```

The goal is not merely to extract one long string.

---

## Scanned PDF

Use OCRmyPDF + Tesseract.

For Arabic/English content:

```text
ara+eng
```

On macOS you can use:

```bash
brew install ocrmypdf
brew install tesseract-lang
```

Then test Tesseract Arabic support before processing a large batch.

## OCR Rule

Do not run OCR on every file automatically if it already has a good text layer.

OCR is used when:

```text
No usable text layer
OR
Extraction quality is low
OR
Specific pages require OCR
```

---

## Gemma 4 Vision

Used as a fallback or reviewer, not as the default OCR engine.

Use it when there are:

- Complex tables
- Diagram
- Question embedded in image
- Broken page layout
- Low-confidence extraction
- Mixed visual/text page

---

## Chunking Strategy

Do not use:

```text
Every 500 tokens
```

blindly.

Start with structural chunking:

```text
Course
→ Document
→ Chapter
→ Heading
→ Subheading
→ Page / Paragraph
```

Every chunk keeps:

```text
document_id
document_version_id
page_number
heading
text
clean_text
search_text
previous_chunk_id
next_chunk_id
embedding
```

## Source Citation Requirement

Any new grounded question must store:

```text
document_id
document_version
page_number
chunk_id
supporting_excerpt
```

## PDF Review UI

It should preferably display:

```text
PDF Page
          |
Extracted Text
          |
Detected Heading
          |
[Approve] [Correct] [Ignore]
```

## Acceptance Criteria

```text
[ ] Digital PDFs extract cleanly.
[ ] Arabic scanned pages support ara OCR.
[ ] Page references are preserved.
[ ] Chunks map back to pages.
[ ] Embeddings can retrieve relevant source chunks.
[ ] Human can approve/correct extracted content.
[ ] Old document versions remain traceable.
```

---

# 14. Project 4 — Question Quality Audit & Coverage Map

## Goal

Know the quality of the 25,000 questions, and what we need to fix or create, instead of generating questions at random.

## Quality Layers

### Deterministic Checks

Without an LLM:

- Question not empty
- Correct answer exists
- Correct answer belongs to options
- No duplicate options
- Expected option count
- No broken HTML
- Valid relations

### AI Review

Gemma 4 inspects:

- Ambiguous wording
- Potential multiple correct answers
- Weak distractors
- Answer leakage
- Poor Arabic wording
- Missing context
- Explanation mismatch
- Possible conflict with another question

AI does not give a question final approval.

---

## Structured Quality Output

```json
{
  "language_quality": "good",
  "single_correct_answer": true,
  "ambiguity_detected": false,
  "weak_distractors": ["D"],
  "answer_leakage": false,
  "source_supported": null,
  "recommended_status": "needs_minor_review",
  "issues": [
    {
      "type": "weak_distractor",
      "message": "..."
    }
  ]
}
```

---

## Coverage Classification

Try to classify each question into:

```text
Specialization
Course
Topic
Subtopic
Skill
Learning Objective
Cognitive Level
Predicted Difficulty
```

Do not adopt the AI taxonomy directly.

First the team must approve:

```text
Official / Internal Taxonomy
```

Then AI classifies within that list.

---

## Coverage Dashboard

Example:

```text
Topic: Teaching Strategies

Total approved questions: 850

Recall:        430
Understanding: 260
Application:   140
Analysis:       20

Exact duplicates:       110
Semantic duplicates:     70
Needs review:             95
With approved source:    320
Without source:          530
```

## Main Output

We want to arrive at a gap map:

```text
Topic A has enough questions.
Topic B has too many duplicates.
Topic C lacks Application questions.
Topic D lacks medium/hard questions.
```

This map is the input to the Question Generation project.

## Acceptance Criteria

```text
[ ] Deterministic quality checks run first.
[ ] AI output uses JSON schema.
[ ] Human overrides are stored.
[ ] Taxonomy is controlled.
[ ] Coverage gaps can be identified.
[ ] AI-generated difficulty is marked as predicted, not factual.
```

---

# 15. Project 5 — AI Trainer Assessment Copilot

## Goal

Help the trainer create draft questions from approved sources — not let AI create and publish questions automatically.

## User Flow

```text
Trainer selects:
- Course
- Topic
- Learning objective
- Approved source
- Pages / chapter
- Number of questions
- Difficulty
- Cognitive level
- Question type

        ↓

System retrieves:
- Relevant source chunks
- Existing similar questions
- Approved good examples

        ↓

Gemma 4 generates draft

        ↓

Validation

        ↓

Duplicate check

        ↓

AI reviewer

        ↓

Trainer review

        ↓

Moderator approval

        ↓

Approved Question Bank
```

---

## Prompt Inputs

Do not send a whole book to the model.

Send only:

```text
Task instructions
Approved taxonomy
Learning objective
Relevant source excerpts
Required difficulty
Required question type
Good examples
Nearest existing questions
Do-not-copy instructions
Output JSON schema
```

---

## Example Structured Output

```json
{
  "question": "...",
  "options": [
    {"key": "A", "text": "..."},
    {"key": "B", "text": "..."},
    {"key": "C", "text": "..."},
    {"key": "D", "text": "..."}
  ],
  "correct_answer": "B",
  "explanation": "...",
  "topic": "...",
  "subtopic": "...",
  "learning_objective": "...",
  "cognitive_level": "application",
  "predicted_difficulty": "medium",
  "source_document_id": 17,
  "source_page": 48,
  "source_chunk_id": 810,
  "supporting_excerpt": "..."
}
```

---

## Quality Gates

### Gate 1 — Schema

- JSON valid.
- Required fields.
- Four choices if that is the test rule.
- Correct answer valid.

### Gate 2 — Source Grounding

- Source exists.
- Page exists.
- Supporting excerpt exists.
- Relevant chunk retrieved.

### Gate 3 — Duplicate Check

```text
Generated question
→ Embedding
→ Search 25K bank
→ Nearest candidates
→ Similarity check
→ AI adjudication when needed
```

### Gate 4 — AI Reviewer

Use a reviewer prompt that is separate from the generator prompt.

It checks:

- Multiple answers
- Ambiguity
- Weak distractors
- Unsupported content
- Arabic quality

### Gate 5 — Human Approval

```text
AI Draft
→ Trainer
→ Moderator
→ Approved
```

## Never auto-publish in early versions.

## Acceptance Criteria

```text
[ ] Every generated question has source evidence.
[ ] Generated questions pass duplicate search.
[ ] Generator and Reviewer prompts are versioned separately.
[ ] Trainer can edit before approval.
[ ] Original AI output is preserved for evaluation.
[ ] Moderator can reject with a reason.
[ ] Acceptance/edit/rejection metrics are tracked.
```

---

# 16. Project 6 — Qiyas-style Assessment Builder

## Goal

Use the clean question bank to build public and course-specific tests in an organized, measurable way.

## Important Boundary

AI helps build the test.

Production runs the test.

## Test Types

- Public free test
- Course private test
- Chapter quiz
- Post-lecture quiz
- Mock exam
- Daily practice
- Weekly challenge

## Assessment Blueprint

Example:

```text
Questions: 50

Topics:
Topic A: 20%
Topic B: 30%
Topic C: 25%
Topic D: 25%

Difficulty:
Easy: 20%
Medium: 60%
Hard: 20%

Rules:
- Approved only
- No semantic duplicates
- No recently overused questions
- Balanced learning objectives
```

## AI Role

- Suggest blueprint.
- Detect imbalance.
- Suggest candidate questions.
- Explain coverage.
- Flag possible duplicates.
- Suggest missing items.

## Non-AI Logic

Laravel / SQL must handle:

- Randomization
- Time limit
- Attempt count
- Access control
- Question ordering
- Student answer recording
- Scoring
- Test availability
- Publish state

## Event Tracking

Add from the very beginning:

```text
exam_started
question_viewed
answer_selected
answer_changed
question_flagged
exam_completed
exam_abandoned
result_viewed
course_clicked
```

Without event tracking you will not be able to build Projects 8 and 9 well.

## Acceptance Criteria

```text
[ ] Exam can be built from approved questions.
[ ] No duplicate cluster appears twice unless intentionally allowed.
[ ] Blueprint coverage is visible before publish.
[ ] Production controls scoring and access.
[ ] Events are recorded.
[ ] Public and private exams use the same core engine.
```

---

# 17. Project 7 — Telegram Engagement Workflows

## Goal

Turn Telegram into an engagement channel that drives students toward the questions, the tests, and the platform.

## n8n becomes important here.

## Workflow A — Daily Question

```text
Schedule
→ Request approved question
→ Exclude recently published
→ Create Telegram preview
→ Moderator approval
→ Publish
→ Save Telegram message ID
→ Collect available metrics
```

## Workflow B — Mini Exam

```text
Moderator selects topic
→ Build exam draft
→ Review
→ Production creates public URL
→ n8n schedules Telegram post
→ Track landing/test events
```

## Workflow C — Weekly Challenge

```text
Weekly schedule
→ Select approved question set
→ Create challenge
→ Human approval
→ Publish
→ Weekly report
```

## Workflow D — Publishing Failure

```text
Telegram failure
→ Retry
→ Retry failed
→ Log
→ Notify moderator
```

## Workflow E — Approval

At first, approval should preferably live inside Filament.

n8n receives:

```text
approved_content_id
publish_at
channel
```

and then publishes.

This keeps the primary audit trail inside the system itself.

## Data to Save

```text
telegram_channel_id
telegram_message_id
content_type
question_id
test_id
published_at
workflow_execution_id
status
```

## Conversion Tracking

Do not settle for views.

Connect:

```text
Telegram
→ Landing Visit
→ Test Start
→ Test Complete
→ Registration
→ Course Visit
→ Purchase
```

## Acceptance Criteria

```text
[ ] No unapproved content is published.
[ ] Published question links to canonical/internal IDs.
[ ] n8n failures are visible.
[ ] Duplicate/recent question reuse is controlled.
[ ] Tracking links identify Telegram source.
```

---

# 18. Project 8 — Assessment Intelligence Dashboard

## Goal

Use the real data to improve the questions, the tests, the content, and engagement.

## Calculations

The core calculations are done with:

```text
SQL
Python
Statistical rules
```

and not with an LLM.

## Question Metrics

- Number of appearances
- Correct answer rate
- Incorrect answer rate
- Average answer time
- Abandonment rate
- Distractor selection
- First attempt vs repeated attempt
- Performance by course
- Performance by cohort
- Question discrimination

## Test Metrics

- Starts
- Completion
- Average score
- Median score
- Average duration
- Drop-off question
- Retry rate

## Engagement Metrics

- Daily practice completion
- Weekly active learners
- Return rate
- Telegram → test conversion
- Free test → registration
- Registration → purchase
- Test → course page click

## AI Role

Gemma 4 can:

- Explain statistical findings.
- Summarize anomalies.
- Produce trainer-facing report.
- Suggest items for human review.

Example:

```text
Question #418 needs review.

Evidence:
- Correct rate: 14%
- High performers frequently selected B
- Response time is far above test average
- Option D is almost never selected

Recommendation:
Review wording and B/C distractors.
```

AI does not compute the rates itself when SQL can compute them.

## Acceptance Criteria

```text
[ ] Metrics are reproducible from raw data.
[ ] AI explanations include supporting metric IDs/values.
[ ] AI cannot invent missing metrics.
[ ] Trainer can navigate from insight to question.
[ ] Analytics distinguishes observation from recommendation.
```

---

# 19. Project 9 — Student Engagement & Personalized Practice

## Goal

Increase engagement using real student behaviour and the clean question bank.

## Start Rule-based

Do not start with machine learning.

## Rules Examples

```text
Completed lecture
AND no quiz attempt
→ Suggest chapter quiz
```

```text
Multiple wrong answers in same topic
→ Suggest focused practice
```

```text
No activity for 10 days
AND relevant exam is approaching
→ Follow-up candidate
```

```text
Purchased course
AND never accessed test
→ Suggest first practice test
```

## Daily Practice

Example:

```text
5 questions
5–10 minutes
Immediate result
Topic progress
Streak
```

## Personalized Practice

Later:

```text
Student history
→ Weak topic rules
→ Exclude recent questions
→ Difficulty progression
→ Build 10-question practice set
```

AI can explain why a session was suggested.

But the selection of the questions themselves should rely largely on deterministic rules + analytics.

## Avoid

- Predicting intelligence.
- Permanent negative labels.
- Academic decisions without humans.
- Auto-sending sensitive messages.
- Claiming probability of exam success without validated model.

## Acceptance Criteria

```text
[ ] Every recommendation has an explainable rule.
[ ] Students are not permanently labeled.
[ ] Recent questions are excluded.
[ ] Practice selection is measurable.
[ ] Engagement changes can be evaluated.
```

---

# 20. Arabic Language Strategy

Arabic is not merely a prompt written in Arabic.

A dedicated layer must be built to handle it.

## Store Multiple Representations

```text
raw_text
clean_text
search_text
```

## Preserve Original

Do not change:

- Question wording
- Correct answer
- Options
- Explanation

except within a review workflow with version history.

## Search Normalization

Allowed:

- Diacritics removal for search only
- Tatweel removal
- Whitespace normalization
- Unicode normalization
- Selected punctuation normalization
- Consistent digit representation

## Do not over-normalize

Avoid rules that may change meaning.

## Mixed Arabic / English

Include in the tests data that covers:

```text
Arabic only
Arabic + English terminology
English specialization questions
Numbers
Equations
Scientific symbols
Names
Tables
```

## Arabic Evaluation

Create a dataset reviewed by trainers/moderators.

It measures:

```text
Grammar acceptance
Meaning preservation
Duplicate classification accuracy
Question clarity
Correct-answer agreement
Distractor quality
Grounding quality
```

The presence of multilingual support in a model card does not automatically mean the Arabic quality is adequate for professional-licence exams.

The InjazEdu-specific evaluation is the judge.

---

# 21. PDF & Arabic OCR Evaluation

Create a fixed test set of pages:

```text
10 clean Arabic digital pages
10 scanned Arabic pages
10 Arabic + English pages
10 tables
10 pages with diagrams/images
```

For each page:

- Gold text.
- Page number.
- Important headings.
- Important table values.
- Known difficult regions.

Measure:

```text
Text preservation
Arabic word accuracy
Heading detection
Page reference accuracy
Table preservation
Manual correction time
```

Do not rely on OCR output just because it "looks good."

---

# 22. AI Evaluation Strategy

This is not optional.

## Duplicate Eval Set

Example:

```text
500–800 reviewed pairs
```

Labels:

```text
exact_duplicate
semantic_duplicate
valid_variant
related
not_related
conflicting
```

## Quality Eval Set

```text
200–500 questions
```

Every question has human labels:

- Clear / unclear
- One correct answer / multiple
- Good distractors / weak
- Language accepted / rejected

## Grounding Eval Set

```text
100–200 question/source cases
```

## Generation Eval Set

```text
100 generation tasks
```

Measure:

- Trainer accept without edit.
- Accept after small edit.
- Major rewrite.
- Reject.
- Duplicate created.
- Unsupported answer.
- Arabic correction required.

---

# 23. Prompt Management

Do not store prompts inside controller code only.

Create a prompt registry:

```text
prompt_name
version
task
model
template
json_schema
created_at
active
notes
```

Example:

```text
duplicate-adjudicator:v1
question-quality-reviewer:v1
question-generator:v1
source-grounding-reviewer:v1
```

When a prompt changes:

```text
v1 → v2
```

Do not overwrite history.

The goal is to know:

> Did the result improve because of a new model or a new prompt?

---

# 24. Structured Outputs

Every structurable task must return a JSON schema.

Do not rely on:

```text
AI generated paragraph
```

and then parse it with regex.

Classification example:

```json
{
  "label": "semantic_duplicate",
  "confidence": 0.91,
  "reasons": ["..."],
  "requires_human_review": true
}
```

Laravel / FastAPI validates the schema before accepting the result.

---

# 25. Human-in-the-loop Rules

The human must sit at a clear control point.

## AI may:

- Suggest
- Classify
- Rank
- Flag
- Generate draft
- Explain metrics

## AI may not initially:

- Publish questions
- Delete source questions
- Change correct answers
- Send student messages without review
- Modify Production DB directly
- Make academic decisions
- Publish to Telegram without approval
- Replace trainer judgment

---

# 26. Production Integration Plan

After the projects succeed locally, add a Signed Internal API.

## Authentication

You can use:

```text
HMAC-signed requests
Timestamp
Nonce
Request body hash
```

or a service-to-service token with strong controls.

What matters most:

- HTTPS only.
- Short replay window.
- IP restrictions if practical.
- Rate limiting.
- Audit logs.
- Separate credentials from user auth.

## Read APIs

Such as:

```text
GET /internal/ai/questions
GET /internal/ai/questions/changes
GET /internal/ai/courses
GET /internal/ai/tests
```

## Write APIs

Deferred.

Later, if we need to publish an approved question:

```text
POST /internal/ai/approved-questions
```

But it must:

- Accept only an approved payload.
- Record the AI Lab as the source.
- Be idempotent.
- Not allow modifying a historical question without an explicit flow.

---

# 27. Incremental Sync Strategy

After the initial snapshot:

Use:

```text
updated_at + id
```

or a change log.

Example:

```text
GET /internal/ai/questions/changes
    ?after=2026-08-07T10:00:00Z
    &after_id=12345
```

Every sync has:

```text
sync_run_id
started_at
finished_at
cursor
records_seen
created
updated
deleted
failed
```

If a batch fails, do not start from zero.

---

# 28. Data Privacy

Even though the questions are not personal data, the system will later deal with student analytics.

## Local Development

Preferably:

- Do not transfer names.
- Do not transfer emails.
- Do not transfer phones.
- Use anonymous IDs.
- Use synthetic student datasets when building analytics for the first time.

Example:

```text
student_ref = hashed/internal surrogate id
```

AI does not need to know a student's name in order to analyse their performance.

---

# 29. Security Rules

## Ollama

- Localhost only during development.
- Do not expose port `11434` publicly in the future.
- FastAPI is the gateway to the AI.

## PostgreSQL

- Password.
- Private network.
- No public exposure in Production.

## Redis

- Private only.
- Do not expose publicly.

## n8n

- Protect UI.
- Encrypt credentials.
- Separate Development Telegram bot from Production.
- Export workflows to Git without secrets.

## PDFs

- Private storage.
- Access control.
- Malware/file validation.
- MIME/extension checks.
- File-size limit.
- Hash files.
- Never trust uploaded filename.

---

# 30. Logging & Observability

Every AI job records:

```text
job_id
task_type
input_reference
model
prompt_version
started_at
finished_at
latency_ms
status
error
tokens/usage where available
human_result
```

Every important n8n workflow records:

```text
workflow
execution_id
external_reference
status
failure_reason
```

Do not store sensitive text in logs without need.

---

# 31. Idempotency

Very important in:

- Imports
- Sync
- PDF processing
- Telegram publishing
- AI generation
- Production writes

Example:

```text
document SHA256
```

If the same file is uploaded again:

```text
Do not process it twice unless explicitly versioned.
```

Telegram:

```text
publish_key = channel + content_id + scheduled_time
```

to reduce double publishing.

---

# 32. Queues

AI processing does not run inside a long HTTP request.

Example:

```text
POST /generate-question
→ 202 Accepted
→ job_id
```

Then:

```text
Queue Worker
→ Retrieval
→ Ollama
→ Validation
→ Save
```

The UI shows:

```text
Queued
Processing
Completed
Failed
Needs review
```

---

# 33. Local Development Phases

## Phase A — Synthetic

Before Production data:

```text
100–500 synthetic questions
2–3 test PDFs
fake courses
```

The goal:

- Schema.
- Jobs.
- UI.
- Embeddings.
- Ollama.
- PDF flow.

## Phase B — Real Snapshot

Import a controlled copy of the 25K questions.

Do not include student PII.

## Phase C — Human Evaluation

Use the team to review:

- Duplicate pairs.
- AI flags.
- PDF extraction.
- Generated questions.

## Phase D — Read-only Production Integration

Signed API.

The AI Lab only reads changes.

## Phase E — Human-approved Write Integration

Only once the system has proven itself.

---

# 34. Recommended First MVP

Do not start all the projects.

## MVP 1 — Question Intelligence Foundation

Components:

```text
1. Local stack
2. Import sample questions
3. Import 25K snapshot
4. Arabic normalization
5. Exact duplicate detection
6. EmbeddingGemma
7. pgvector similarity
8. Gemma 4 duplicate adjudication
9. Filament duplicate review
10. Duplicate clusters
```

## MVP 2 — PDF Grounding

```text
1. Upload PDF
2. Extract text
3. OCR Arabic page when required
4. Create page/chunk records
5. Embed chunks
6. Search source
7. Display page citation
```

## MVP 3 — One Grounded Question

```text
Trainer selects source
→ Retrieve chunks
→ Generate one draft
→ Duplicate check
→ Quality review
→ Human approval
```

Succeeding at these three proves the most important pipeline:

```text
Existing Data
→ Clean
→ Retrieve
→ AI
→ Validate
→ Human Review
```

After that, start the student-facing features.

---

# 35. Project Dependency Map

```text
Project 0
  │
  ▼
Project 1
  │
  ▼
Project 2 ────────────┐
  │                   │
  ▼                   │
Project 3             │
  │                   │
  └──────┬────────────┘
         ▼
      Project 4
         │
         ▼
      Project 5
         │
         ▼
      Project 6
       /     \
      ▼       ▼
Project 7   Project 8
               │
               ▼
            Project 9
```

---

# 36. Suggested Build Order by Learning Goal

## AI / LLM fundamentals

```text
Project 0
Project 2
```

You will learn:

- Ollama
- Embeddings
- Structured Outputs
- Prompt versioning
- Evals

## RAG / Documents

```text
Project 3
Project 5
```

You will learn:

- PDF parsing
- OCR
- Chunking
- Retrieval
- Grounded generation

## Automation

```text
Project 7
```

You will learn:

- n8n
- Telegram API workflows
- Approval
- Retry
- Scheduling

## Analytics

```text
Project 8
```

You will learn:

- Event tracking
- SQL
- Analytics design
- Evidence-based AI reporting

## Advanced engagement

```text
Project 9
```

You will learn:

- Rules engines
- Personalization
- Cohorts
- Engagement measurement

---

# 37. What Not to Build Yet

Defer:

- Full student chatbot.
- Autonomous AI Agent with Production write access.
- Fine-tuning Gemma.
- ML prediction of exam success.
- Full recommendation ML.
- Automatic question publishing.
- Automatic Telegram publishing without approval.
- Vector search over every possible production document.
- Complex multi-agent architecture.

These add complexity before value has been proven.

---

# 38. Fine-tuning Decision

Do not start fine-tuning.

Start with:

```text
Good data
+ RAG
+ Structured prompts
+ Few-shot examples
+ Human review
+ Evals
```

Consider fine-tuning only if you have:

- Large clean approved dataset.
- Repeated failure pattern.
- Reliable benchmark proving prompting/RAG are insufficient.
- Clear measurable objective.

---

# 39. Model Selection Must Be Empirical

Do not adopt Gemma 4 E2B just because it is small.

Run a benchmark:

| Test | E2B | E4B | 12B |
|---|---:|---:|---:|
| Arabic duplicate accuracy | | | |
| JSON compliance | | | |
| Question quality | | | |
| Grounding | | | |
| Avg latency | | | |
| Memory usage | | | |
| Trainer acceptance | | | |

Then choose a model per task if needed.

Perhaps:

```text
E2B → classification
E4B/12B → generation
```

But do not adopt this before the benchmark.

---

# 40. n8n Design Rules

## Good n8n workflow

```text
Trigger
→ API
→ Wait/check
→ Approval
→ External action
→ Log
→ Failure branch
```

## Bad n8n workflow

```text
Load 25K records
→ Loop
→ Run LLM
→ Parse PDF
→ Generate Embeddings
→ SQL transformations
```

This kind of processing must live in backend jobs.

---

# 41. Telegram Development Instructions

At first use:

- Development Bot.
- Private test channel.
- Test user accounts.
- Non-production links.

Do not use the production channel while building a workflow.

Add environment separation:

```text
TELEGRAM_ENV=development
TELEGRAM_BOT_TOKEN=...
TELEGRAM_CHANNEL_ID=...
```

and separate credentials in Production.

---

# 42. Data Versioning

Everything important needs versioning:

```text
Question revision
Document version
Prompt version
Model tag
Taxonomy version
Generation rule version
Assessment blueprint version
```

Without versioning you will not know why the output differed several months later.

---

# 43. Question Lifecycle

Suggested:

```text
source_imported
needs_cleanup
duplicate_review
quality_review
approved_existing
needs_revision
archived
```

For new questions:

```text
ai_draft
trainer_review
moderator_review
approved
rejected
published
```

Do not mix an imported question with a generated draft.

---

# 44. Document Lifecycle

```text
uploaded
processing
needs_ocr
extracted
needs_review
approved
superseded
rejected
```

Only:

```text
approved
```

may be used as an official source for generating a question.

---

# 45. AI Job Types

Use a clear enum:

```text
EMBED_QUESTION
FIND_DUPLICATES
ADJUDICATE_DUPLICATE
CLASSIFY_QUESTION
QUALITY_REVIEW
PROCESS_DOCUMENT
EMBED_DOCUMENT
RETRIEVE_SOURCE
GENERATE_QUESTION
REVIEW_GENERATED_QUESTION
EXPLAIN_ANALYTICS
```

This helps with:

- Metrics
- Retry rules
- Queue priorities
- Cost/performance analysis

---

# 46. Retry Rules

Not every error is retryable.

## Retry

- Ollama temporary unavailable.
- Network timeout.
- Redis transient error.
- Telegram temporary failure.

## Do not blindly retry

- Invalid PDF.
- Invalid JSON schema after repeated attempt.
- Missing source.
- Permission error.
- Unsupported file.
- Failed validation.

---

# 47. Development Testing

## Unit Tests

- Arabic normalizer.
- Hash generation.
- Validation.
- Source reference.
- Duplicate state transitions.

## Integration Tests

- FastAPI → Ollama.
- FastAPI → pgvector.
- Laravel → FastAPI.
- Laravel Queue → AI job.
- n8n → Laravel.
- Telegram test bot.

## Golden Dataset Tests

Run the same eval dataset after:

- Model change.
- Prompt change.
- Normalization change.
- Embedding change.
- Chunking change.

---

# 48. Deployment Path

## Local

```text
Mac
```

## Later Staging

It is preferable to create a staging environment before full Production integration:

```text
Staging Laravel
Staging AI Lab
Synthetic/anonymized data
Test Telegram bot
```

## Production

```text
DigitalOcean:
injazedu.co

Hostinger:
AI Lab
```

And do not move the local database itself into Production.

Use:

- Migrations.
- Seeds.
- Controlled imports.

---

# 49. Hostinger Transition Checklist

When moving from the Mac to the VPS:

```text
[ ] Linux Docker stack tested.
[ ] PostgreSQL backup configured.
[ ] Redis persistence decision documented.
[ ] Ollama model benchmark repeated on server.
[ ] RAM limits verified.
[ ] Swap configured conservatively if needed.
[ ] n8n credentials encrypted/backed up.
[ ] HTTPS configured.
[ ] Firewall configured.
[ ] Ollama private only.
[ ] PostgreSQL private only.
[ ] Redis private only.
[ ] Signed Production API configured.
[ ] Secrets replaced.
[ ] Staging smoke tests completed.
```

Do not assume Gemma 4's performance on the Mac equals its performance on KVM 2; the Mac may benefit from the Apple GPU/Metal, while KVM CPU characteristics are different.

---

# 50. Important Architecture Decisions

## ADR-001

**Production remains source of truth.**

## ADR-002

**AI Lab starts read-only relative to Production.**

## ADR-003

**Ollama is not exposed directly to Production or the public internet.**

## ADR-004

**Embeddings and vector search use a dedicated embedding model, not the generative LLM.**

## ADR-005

**All AI outputs that can affect educational content require human review initially.**

## ADR-006

**n8n orchestrates workflows; it does not own core business logic.**

## ADR-007

**Arabic original text is preserved; normalization is task-specific.**

## ADR-008

**Question generation is source-grounded and duplicate-checked before approval.**

## ADR-009

**Analytics calculations are deterministic; LLM explains them.**

## ADR-010

**Local development uses controlled exports before live Production integration.**

---

# 51. Definition of Success

The program's success does not mean that AI produces many questions.

Success is measured by:

## Question Bank

- Duplicate rate identified.
- Duplicate review accuracy.
- Number of canonical clusters.
- Reduced repeated use.
- Number of quality issues resolved.

## Trainer Copilot

- Draft acceptance rate.
- Average edit size.
- Time saved.
- Source-grounding accuracy.
- Duplicate generation rate.

## PDF

- Extraction acceptance.
- OCR correction rate.
- Source retrieval accuracy.

## Assessment

- Completion rate.
- Question quality.
- Balanced coverage.

## Telegram

- Participation.
- Click-through.
- Test starts.
- Test completion.
- Registration.
- Conversion to course interest/purchase.

## Student Engagement

- Return rate.
- Practice completion.
- Weekly active students.
- Streak retention.
- Course/test engagement.

---

# 52. Recommended Immediate Next Steps

Start in this order only:

## Step 1

Create the repository:

```text
injazedu-ai-lab
```

## Step 2

Run:

```text
PostgreSQL + pgvector
Redis
n8n
Ollama
```

## Step 3

Create the Laravel + Filament Lab.

## Step 4

Create the FastAPI AI Service.

## Step 5

Test:

```text
FastAPI → Ollama → Gemma 4
FastAPI → Ollama → EmbeddingGemma
```

## Step 6

Create the schema for importing questions.

## Step 7

Start with only 100 questions.

Execute:

```text
Import
→ Normalize
→ Exact duplicate
→ Embedding
→ Similarity search
```

## Step 8

After the 100 questions succeed:

```text
1,000 questions
```

then:

```text
25,000 questions
```

## Step 9

Build the first Duplicate Review screen.

## Step 10

Do not start PDF or generation until you can review duplicate candidates reliably.

---

# 53. Local macOS Setup Notes

## Ollama

Ollama's current requirement on macOS is macOS 14 Sonoma or newer.

On Apple M-series, Ollama supports GPU acceleration using Metal.

Check:

```bash
ollama --version
```

Then:

```bash
ollama pull embeddinggemma:300m-qat-q4_0
ollama pull gemma4:e2b-it-qat
```

Test:

```bash
ollama list
```

---

## OCR

```bash
brew install ocrmypdf
brew install tesseract-lang
```

Check the languages:

```bash
tesseract --list-langs
```

You should find:

```text
ara
eng
```

---

## PostgreSQL + pgvector

In local development it is better to use a Docker image that already contains pgvector, instead of building the extension by hand every time.

After starting the database:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

---

# 54. Suggested Local Environment Variables

An example only — do not use Production secrets:

```dotenv
APP_ENV=local

AI_SERVICE_URL=http://localhost:8001

OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_CHAT_MODEL=gemma4:e2b-it-qat
OLLAMA_EMBED_MODEL=embeddinggemma:300m-qat-q4_0

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=injaz_ai_lab
DB_USERNAME=injaz_lab
DB_PASSWORD=local-only-password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

N8N_ENV=local

PRODUCTION_SYNC_ENABLED=false
PRODUCTION_WRITE_ENABLED=false
```

Having:

```text
PRODUCTION_WRITE_ENABLED=false
```

as a kill switch is useful even after integration has begun.

---

# 55. Final Roadmap

```text
LOCAL FOUNDATION
│
├── Project 0
│
DATA UNDERSTANDING
│
├── Project 1
│   Question Mirror
│
├── Project 2
│   Arabic + Duplicates
│
KNOWLEDGE FOUNDATION
│
├── Project 3
│   PDF Sources
│
├── Project 4
│   Quality + Coverage
│
AI CONTENT CREATION
│
├── Project 5
│   Trainer Assessment Copilot
│
ASSESSMENT EXPERIENCE
│
├── Project 6
│   Qiyas-style Assessment Builder
│
ENGAGEMENT
│
├── Project 7
│   Telegram
│
├── Project 8
│   Assessment Intelligence
│
└── Project 9
    Personalized Practice
```

---

# 56. Final Principle

The goal is not:

> How do we put AI into InjazEdu?

but rather:

> Which process do we want to improve, and which part of it should be executed by code, or SQL, or a workflow, or an LLM, or a human?

The better division:

```text
Deterministic Code
→ correctness and business rules

Embeddings
→ retrieval and similarity

Gemma 4
→ language understanding, generation and explanation

n8n
→ orchestration and external workflows

Laravel + Filament
→ business workflow and human review

FastAPI
→ AI processing boundary

PostgreSQL + pgvector
→ AI Lab data and vector layer

Redis
→ queues, locks and transient state

Humans
→ final educational approval
```

This way the project stays useful to InjazEdu even if Gemma 4 is later swapped for another model.

---

# 57. Verified Technical References

The following technical information was verified in August 2026:

- Ollama macOS documentation:  
  https://docs.ollama.com/macos

- Ollama GPU / Apple Metal support:  
  https://docs.ollama.com/gpu

- Ollama Gemma 4 model tags:  
  https://ollama.com/library/gemma4/tags

- Ollama EmbeddingGemma:  
  https://ollama.com/library/embeddinggemma

- Google Gemma 4 overview/model card:  
  https://ai.google.dev/gemma/docs/core/model_card_4

- pgvector:  
  https://github.com/pgvector/pgvector

- PyMuPDF4LLM:  
  https://pymupdf.readthedocs.io/en/latest/pymupdf4llm/

- OCRmyPDF installation and language packs:  
  https://ocrmypdf.readthedocs.io/en/latest/installation.html  
  https://ocrmypdf.readthedocs.io/en/latest/languages.html

- Tesseract supported languages (`ara` for Arabic):  
  https://tesseract-ocr.github.io/tessdoc/Data-Files-in-different-versions.html

- n8n Telegram integration:  
  https://docs.n8n.io/integrations/builtin/app-nodes/n8n-nodes-base.telegram/

---

# 58. Document Status

This document is **Implementation Roadmap v1.0**.

It should be updated when important decisions are taken, such as:

- Final local Docker architecture.
- Production Internal API design.
- Official Question Taxonomy.
- Selected Gemma 4 model after benchmarking.
- Embedding similarity thresholds.
- PDF chunking rules.
- Approval workflow.
- Telegram publishing policy.
- Assessment event schema.
- Production deployment capacity.

Do not fix these decisions before there is enough data and experimentation.
