# InjazEdu AI Assessment & Engagement Lab
## Full Implementation Plan — Local Development First

**Version:** 1.0  
**Date:** 2026-08-07  
**Primary development environment:** macOS  
**Future deployment target:** Hostinger VPS for the AI Lab + DigitalOcean for `injazedu.co` Production

---

# 1. Executive Summary

الهدف من هذا البرنامج هو بناء مجموعة مشاريع مترابطة حول `injazedu.co` تحقق هدفين في الوقت نفسه:

1. تطوير Features حقيقية تخدم المنصة وتزيد جودة الاختبارات والتفاعل.
2. استخدام المنصة كمختبر عملي لتعلّم وتطبيق:
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

المنصة تحتوي بالفعل على نحو **25,000 سؤال** موزعة على الاختبارات العامة والخاصة بالدورات، مع وجود أسئلة مكررة. لذلك لا يجب أن تكون البداية بإنشاء أسئلة جديدة، بل بتحويل بنك الأسئلة الموجود إلى **Question Intelligence Layer** نظيفة وقابلة للتحليل والتوسع.

الترتيب العام:

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

القاعدة الأساسية للنظام:

```text
Production serves students.
AI Lab prepares, analyses and recommends.
Humans approve.
```

---

# 2. هل Local Development يغيّر الخطة؟

نعم، ولكن التغيير في **Infrastructure وIntegration strategy** أكثر من التغيير في Architecture.

في مرحلة Local Development:

- لا نحتاج Hostinger VPS.
- لا نحتاج فتح أي AI service على الإنترنت.
- لا نحتاج اتصالًا مباشرًا من جهاز التطوير بقاعدة MySQL في Production.
- نبدأ باستخدام Snapshot / Export آمن من بيانات الأسئلة.
- نستخدم Ollama محليًا على الـMac.
- نستخدم Docker Compose لباقي خدمات الـInfrastructure.
- نستخدم Local filesystem لملفات PDF في البداية.
- Telegram يمكن تجربته لاحقًا باستخدام Bot تجريبي وقناة خاصة.
- Signed Internal API مع Production يتم بناؤه واختباره بعد إثبات الـLocal workflows.

## التغيير الأهم

أثناء التطوير المحلي:

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

لاحقًا:

```text
DigitalOcean Production
      │
      │ Signed HTTPS Internal API
      ▼
Hostinger AI Lab
```

**لا تجعل الـLocal AI Lab يتصل مباشرة بقاعدة Production MySQL بصلاحيات مفتوحة.**

---

# 3. Architecture Overview

## 3.1 Local Development Architecture

الـArchitecture الموصى بها على الـMac هي Hybrid:

- Ollama يعمل Native على macOS.
- PostgreSQL + pgvector داخل Docker.
- Redis داخل Docker.
- n8n داخل Docker.
- Laravel + Filament يمكن تشغيلهما Native أو داخل Docker.
- FastAPI يمكن تشغيله Native داخل Python virtual environment أو داخل Docker.
- Files تحفظ محليًا في Private Storage أثناء التطوير.

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

Production يظل الـSource of Truth للبيانات الأساسية التي تؤثر على الطلاب.

مسؤول عن:

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

لا يعتمد الطالب على Ollama لكي يبدأ أو يكمل اختبارًا.

---

## 4.2 AI Lab

مسؤول عن:

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

AI Lab لا يصبح الـSource of Truth للاشتراكات أو صلاحيات الطلاب.

---

## 4.3 n8n

n8n هو Orchestrator، وليس Data Processing Engine.

يستخدم في:

- Scheduling
- Triggering API jobs
- Telegram publishing
- Telegram workflows
- Human approvals
- Notifications
- Scheduled reports
- Failure alerts
- Cross-system automation

ولا يستخدم في:

- معالجة 25,000 سؤال Node-by-Node.
- إنشاء Embeddings لجميع البيانات داخل Workflow طويل.
- PDF parsing الثقيل.
- OCR الثقيل.
- Statistical computation الثقيل.
- حفظ Business Logic الأساسية.

القاعدة:

```text
n8n triggers and coordinates.
FastAPI and Queue Workers process.
Laravel manages business rules and human review.
```

---

# 5. Recommended Local Stack

## Application Layer

### Laravel + Filament

الاستخدام:

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

الاستخدام:

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

لا تضع Prompt logic الرئيسية داخل n8n.

---

## Data Layer

### PostgreSQL + pgvector

يستخدم لـ:

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

يستخدم لـ:

- Queues
- Job state
- Locks
- Rate limits
- Short-lived caching
- Idempotency keys

---

## AI Layer

### Ollama

يعمل Native على الـMac أثناء التطوير.

السبب:

- على Apple Silicon يستفيد Ollama من Metal GPU acceleration.
- تشغيله Native يفصل الـAI runtime عن Docker networking.
- يبسط اختبار النماذج.
- يمنع إضافة طبقة Virtualization غير ضرورية للنموذج.

إذا كان الجهاز Intel Mac، فـOllama يعمل CPU-only حسب وثائق Ollama الحالية.

---

# 6. Recommended Ollama Models

## 6.1 Embeddings Model

### Baseline

```bash
ollama pull embeddinggemma:300m-qat-q4_0
```

الاستخدام:

- Question semantic similarity
- Duplicate candidate search
- Related-question search
- PDF chunk embeddings
- RAG retrieval
- Topic clustering experiments

لا تستخدم Gemma 4 نفسه لإنشاء Embeddings.

---

## 6.2 Generative Model

### Baseline Model

```bash
ollama pull gemma4:e2b-it-qat
```

الاستخدام:

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

إذا كان الـMac يحتوي على RAM / Unified Memory كافية:

```text
gemma4:e4b-it-qat
gemma4:12b-it-qat
```

لا تختَر النموذج بناءً على حجم ملفه فقط؛ يجب قياس:

- Real memory usage
- Tokens/sec
- Prompt latency
- Arabic quality
- JSON compliance
- Accuracy on InjazEdu Eval Dataset

## Practical Local Guidance

### 8 GB memory

ابدأ فقط بـ:

```text
EmbeddingGemma
Gemma 4 E2B QAT
```

وأغلق الخدمات غير الضرورية أثناء الاختبارات الثقيلة.

### 16 GB memory

مناسب جدًا لـE2B.

يمكن تجربة E4B ومقارنة الأداء.

### 24–32 GB أو أكثر

يمكن Benchmark:

```text
E2B
E4B
12B
```

لكن اختيار Production model يتم حسب الـEvaluation وليس الحجم فقط.

---

# 7. Ollama Local Configuration Principles

ابدأ بسياق صغير بدل استخدام Max Context الخاص بالنموذج:

```text
Development baseline context: 4096
```

ارفعه عند وجود سبب واضح.

اقتراحات:

```text
Classification:
temperature = 0.0 – 0.2

Quality Evaluation:
temperature = 0.0 – 0.2

Question Generation:
temperature = 0.4 – 0.7
```

كل AI task يجب أن يكون له:

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

يمكن تنفيذ Laravel وFastAPI في Repositories مستقلة أو Monorepo.

للتعلّم وسهولة التطوير، Monorepo مناسب في البداية:

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

يفضل أن يحتوي Docker Compose في البداية على:

```text
postgres
redis
n8n
```

ويمكن لاحقًا إضافة:

```text
laravel
fastapi
queue-worker
scheduler
```

Ollama يفضل أن يبقى Native على Mac أثناء Local Development.

FastAPI داخل Docker يستطيع الوصول إلى Ollama الموجود على الـHost باستخدام إعداد Host networking المناسب لـDocker Desktop، أو يمكن تشغيل FastAPI Native أثناء أول مراحل التطوير لتبسيط الاتصال بـ:

```text
http://localhost:11434
```

---

# 10. Project 0 — AI Lab Foundation

## Goal

تجهيز بيئة Local موحدة تسمح ببناء كل المشاريع التالية دون لمس Production.

## Scope

إنشاء:

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

يجب أن تستطيع تنفيذ:

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

- جميع Secrets داخل `.env`.
- لا ترفع `.env` إلى Git.
- لا تستخدم Production credentials محليًا إلا عند مرحلة Integration محددة.
- Ollama port لا يحتاج exposure إلى الإنترنت.
- PostgreSQL port لا يفتح Public.
- Redis لا يفتح Public.
- n8n local instance لا يستخدم Production Telegram credentials في البداية.

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

إنشاء نسخة محلية آمنة من بنك الأسئلة الحالي وتحليل حالته قبل استخدام AI.

## Local Development Data Strategy

في أول مرحلة لا تستخدم Live API.

اعمل Controlled Export من Production.

يفضل تحويل البيانات إلى JSON Schema ثابت.

مثال:

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

أول Import يجب أن يسجل المشاكل بدل إخفائها:

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

كل record يحتوي:

```text
source_system = injazedu_production
source_id
source_updated_at
imported_at
payload_hash
```

## Inventory Dashboard

تعرض:

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

في Local MVP:

لا حاجة له في أول Import.

لاحقًا يمكن:

```text
Manual Trigger
→ Laravel Import Endpoint
→ Check Job
→ Notify Result
```

## Future Production Sync

بعد نجاح Local Import:

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

اكتشاف التكرار الحقيقي داخل بنك الأسئلة دون حذف البيانات الأصلية.

## Core Principle

احتفظ دائمًا بالنص الأصلي.

```text
raw_text
clean_text
search_text
```

## raw_text

النص كما هو في Production.

لا يتم تعديله.

## clean_text

تنظيف تقني فقط:

- Remove unnecessary HTML
- Normalize whitespace
- Normalize Unicode
- Preserve meaning

## search_text

نسخة خاصة بالمقارنة والبحث.

يمكن تطبيق:

- Unicode normalization
- Remove Tatweel `ـ`
- Remove Arabic diacritics for search
- Normalize whitespace
- Normalize punctuation
- Normalize Arabic / Latin digits into one representation
- Normalize selected Alef forms for search
- Remove answer labels when required

## Important Arabic Rule

لا تقم تلقائيًا بتحويلات لغوية قد تغيّر المعنى.

مثال:

```text
ة → ه
```

لا تستخدم هذا كNormalization عام.

---

## Stage A — Exact Duplicate Detection

بدون LLM.

أنشئ:

```text
question_text_hash
question_with_options_hash
```

مثال:

```text
SHA256(search_question_text)
SHA256(search_question_text + normalized_options)
```

يكتشف:

- Exact copies
- Formatting differences
- Whitespace differences
- Simple punctuation differences

---

## Stage B — Lexical Similarity

استخدم:

- Character n-grams
- Token overlap
- Jaccard similarity
- Edit distance

هذه مرحلة Candidate Generation إضافية.

---

## Stage C — Semantic Similarity

استخدم EmbeddingGemma.

لكل سؤال:

```text
stem_embedding
full_question_embedding
```

### stem_embedding

يتضمن:

```text
Question stem only
```

### full_question_embedding

يتضمن:

```text
Question
+
Options
```

احفظ الـvectors في pgvector.

ثم:

```text
Question
→ Top-K nearest candidates
→ Similarity threshold
→ Candidate pairs
```

لا تقارن كل سؤال بكل الـ25,000 سؤال باستخدام LLM.

---

## Stage D — Gemma 4 Adjudication

Gemma 4 لا يبحث في البنك كله.

يستقبل فقط Candidate Pair.

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

Output يجب أن يكون Structured JSON:

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

لا تحذف الأسئلة.

أنشئ:

```text
duplicate_clusters
duplicate_cluster_members
duplicate_candidates
duplicate_reviews
```

مثال:

```text
Cluster 52
├── Canonical #120
├── Duplicate #845
├── Duplicate #9120
└── Valid Variant #10455
```

## Why not delete?

لأن السؤال قد يكون مرتبطًا بـ:

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

قبل اعتماد Threshold:

أنشئ Dataset بشرية مثل:

```text
200 exact/near duplicate pairs
200 semantic duplicate pairs
200 non-duplicate related pairs
200 unrelated pairs
```

ثم قِس:

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

تحويل كتب الدورات وملفات PDF إلى Knowledge Source موثوقة يمكن الرجوع إليها عند إنشاء ومراجعة الأسئلة.

## Supported Documents

البداية:

```text
PDF
```

لاحقًا:

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

لكل ملف:

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

استخدم PyMuPDF / PyMuPDF4LLM.

يفضل الاحتفاظ بـ:

```text
page number
text blocks
headings
tables
images metadata
bounding boxes when useful
```

الهدف ليس مجرد استخراج String طويل.

---

## Scanned PDF

استخدم OCRmyPDF + Tesseract.

للمحتوى العربي/الإنجليزي:

```text
ara+eng
```

على macOS يمكن استخدام:

```bash
brew install ocrmypdf
brew install tesseract-lang
```

ثم اختبار Tesseract Arabic support قبل معالجة Batch كبير.

## OCR Rule

لا تستخدم OCR على كل ملف تلقائيًا إذا كان لديه Text Layer جيد.

OCR يستخدم عندما:

```text
No usable text layer
OR
Extraction quality is low
OR
Specific pages require OCR
```

---

## Gemma 4 Vision

يستخدم كـFallback أو Reviewer، وليس OCR engine الافتراضي.

استخدمه عندما توجد:

- Complex tables
- Diagram
- Question embedded in image
- Broken page layout
- Low-confidence extraction
- Mixed visual/text page

---

## Chunking Strategy

لا تستخدم:

```text
Every 500 tokens
```

بصورة عمياء.

ابدأ بـStructural Chunking:

```text
Course
→ Document
→ Chapter
→ Heading
→ Subheading
→ Page / Paragraph
```

كل Chunk يحتفظ بـ:

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

أي سؤال جديد Grounded يجب أن يخزن:

```text
document_id
document_version
page_number
chunk_id
supporting_excerpt
```

## PDF Review UI

يفضل أن يعرض:

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

معرفة جودة الـ25,000 سؤال، وما الذي نحتاج إلى إصلاحه أو إنشائه بدل توليد أسئلة عشوائيًا.

## Quality Layers

### Deterministic Checks

بدون LLM:

- Question not empty
- Correct answer exists
- Correct answer belongs to options
- No duplicate options
- Expected option count
- No broken HTML
- Valid relations

### AI Review

Gemma 4 يفحص:

- Ambiguous wording
- Potential multiple correct answers
- Weak distractors
- Answer leakage
- Poor Arabic wording
- Missing context
- Explanation mismatch
- Possible conflict with another question

AI لا يعتمد السؤال نهائيًا.

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

حاول تصنيف السؤال إلى:

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

لا تعتمد الـAI taxonomy مباشرة.

أولًا يجب أن يعتمد الفريق:

```text
Official / Internal Taxonomy
```

ثم يقوم AI بالتصنيف داخل هذه القائمة.

---

## Coverage Dashboard

مثال:

```text
Topic: استراتيجيات التدريس

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

نريد الوصول إلى Gap Map:

```text
Topic A has enough questions.
Topic B has too many duplicates.
Topic C lacks Application questions.
Topic D lacks medium/hard questions.
```

هذه الخريطة هي Input لمشروع Question Generation.

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

مساعدة المدرب على إنشاء Draft Questions من مصادر معتمدة، وليس السماح للـAI بإنشاء ونشر أسئلة تلقائيًا.

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

لا ترسل كتابًا كاملًا للنموذج.

أرسل فقط:

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

استخدم Reviewer prompt منفصلًا عن Generator prompt.

يفحص:

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

استخدام Question Bank النظيفة لإنشاء اختبارات عامة وخاصة بالدورات بشكل منظم وقابل للقياس.

## Important Boundary

AI يساعد على بناء الاختبار.

Production يشغل الاختبار.

## Test Types

- Public free test
- Course private test
- Chapter quiz
- Post-lecture quiz
- Mock exam
- Daily practice
- Weekly challenge

## Assessment Blueprint

مثال:

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

يجب أن يقوم Laravel / SQL بـ:

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

أضف من البداية:

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

بدون Event Tracking لن تستطيع بناء Project 8 و9 بصورة جيدة.

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

تحويل Telegram إلى قناة تفاعل تقود الطلاب إلى الأسئلة والاختبارات والمنصة.

## n8n يصبح مهمًا هنا.

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

في البداية يفضل أن يكون الاعتماد داخل Filament.

n8n يتلقى:

```text
approved_content_id
publish_at
channel
```

ثم ينشر.

هذا يجعل Audit Trail الأساسي داخل النظام نفسه.

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

لا تكتفِ بـViews.

اربط:

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

استخدام البيانات الفعلية لتحسين الأسئلة، الاختبارات، المحتوى، والتفاعل.

## Calculations

الحسابات الأساسية تتم باستخدام:

```text
SQL
Python
Statistical rules
```

وليس LLM.

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

Gemma 4 يمكنه:

- Explain statistical findings.
- Summarize anomalies.
- Produce trainer-facing report.
- Suggest items for human review.

مثال:

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

AI لا يحسب النسب بنفسه إذا كانت SQL تستطيع حسابها.

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

زيادة التفاعل باستخدام سلوك الطالب الحقيقي وQuestion Bank النظيفة.

## Start Rule-based

لا تبدأ بـMachine Learning.

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

مثال:

```text
5 questions
5–10 minutes
Immediate result
Topic progress
Streak
```

## Personalized Practice

لاحقًا:

```text
Student history
→ Weak topic rules
→ Exclude recent questions
→ Difficulty progression
→ Build 10-question practice set
```

AI يمكن أن يشرح لماذا تم اقتراح Session.

لكن اختيار الأسئلة نفسه يفضل أن يعتمد بدرجة كبيرة على deterministic rules + analytics.

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

اللغة العربية ليست مجرد Prompt باللغة العربية.

يجب بناء Layer مستقلة للتعامل معها.

## Store Multiple Representations

```text
raw_text
clean_text
search_text
```

## Preserve Original

لا تغيّر:

- Question wording
- Correct answer
- Options
- Explanation

إلا في Review workflow مع Version History.

## Search Normalization

مسموح:

- Diacritics removal for search only
- Tatweel removal
- Whitespace normalization
- Unicode normalization
- Selected punctuation normalization
- Consistent digit representation

## Do not over-normalize

تجنب قواعد قد تغيّر المعنى.

## Mixed Arabic / English

ضع في الاختبارات بيانات تشمل:

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

أنشئ Dataset يراجعها مدربون/Moderators.

تقيس:

```text
Grammar acceptance
Meaning preservation
Duplicate classification accuracy
Question clarity
Correct-answer agreement
Distractor quality
Grounding quality
```

وجود Multilingual support في Model Card لا يعني أن جودة العربية مناسبة تلقائيًا لاختبارات الرخصة المهنية.

الـEvaluation الخاصة بـInjazEdu هي الحكم.

---

# 21. PDF & Arabic OCR Evaluation

أنشئ مجموعة اختبار ثابتة من الصفحات:

```text
10 clean Arabic digital pages
10 scanned Arabic pages
10 Arabic + English pages
10 tables
10 pages with diagrams/images
```

لكل صفحة:

- Gold text.
- Page number.
- Important headings.
- Important table values.
- Known difficult regions.

قِس:

```text
Text preservation
Arabic word accuracy
Heading detection
Page reference accuracy
Table preservation
Manual correction time
```

لا تعتمد على OCR output فقط لأنه "يبدو جيدًا".

---

# 22. AI Evaluation Strategy

هذه ليست Optional.

## Duplicate Eval Set

مثال:

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

كل سؤال له Human labels:

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

قِس:

- Trainer accept without edit.
- Accept after small edit.
- Major rewrite.
- Reject.
- Duplicate created.
- Unsupported answer.
- Arabic correction required.

---

# 23. Prompt Management

لا تخزن Prompts داخل Controller code فقط.

أنشئ Prompt registry:

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

مثال:

```text
duplicate-adjudicator:v1
question-quality-reviewer:v1
question-generator:v1
source-grounding-reviewer:v1
```

عند تغيير Prompt:

```text
v1 → v2
```

لا تستبدل التاريخ.

الهدف أن تعرف:

> هل تحسن الـResult بسبب Model جديد أم Prompt جديد؟

---

# 24. Structured Outputs

كل مهمة قابلة للتنظيم يجب أن ترجع JSON Schema.

لا تعتمد على:

```text
AI generated paragraph
```

ثم Parsing باستخدام Regex.

مثال Classification:

```json
{
  "label": "semantic_duplicate",
  "confidence": 0.91,
  "reasons": ["..."],
  "requires_human_review": true
}
```

Laravel / FastAPI يتحقق من Schema قبل قبول النتيجة.

---

# 25. Human-in-the-loop Rules

يجب أن يكون الإنسان في Control Point واضح.

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

بعد نجاح المشاريع محليًا، أضف Signed Internal API.

## Authentication

يمكن استخدام:

```text
HMAC-signed requests
Timestamp
Nonce
Request body hash
```

أو Service-to-service token مع controls قوية.

الأهم:

- HTTPS only.
- Short replay window.
- IP restrictions if practical.
- Rate limiting.
- Audit logs.
- Separate credentials from user auth.

## Read APIs

مثل:

```text
GET /internal/ai/questions
GET /internal/ai/questions/changes
GET /internal/ai/courses
GET /internal/ai/tests
```

## Write APIs

يتم تأجيلها.

لاحقًا، إذا احتجنا Publish-approved Question:

```text
POST /internal/ai/approved-questions
```

لكن يجب أن:

- يقبل فقط approved payload.
- يسجل AI Lab source.
- يكون Idempotent.
- لا يسمح بتعديل Historical question دون explicit flow.

---

# 27. Incremental Sync Strategy

بعد Initial Snapshot:

استخدم:

```text
updated_at + id
```

أو Change Log.

مثال:

```text
GET /internal/ai/questions/changes
    ?after=2026-08-07T10:00:00Z
    &after_id=12345
```

كل Sync له:

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

إذا فشل Batch، لا تبدأ من الصفر.

---

# 28. Data Privacy

حتى لو كانت الأسئلة غير Personal Data، النظام لاحقًا سيتعامل مع Student Analytics.

## Local Development

يفضل:

- عدم نقل Names.
- عدم نقل Emails.
- عدم نقل Phones.
- استخدام IDs مجهولة.
- Synthetic student datasets عند بناء Analytics أول مرة.

مثال:

```text
student_ref = hashed/internal surrogate id
```

ولا تحتاج AI لمعرفة اسم الطالب حتى يحلل أداءه.

---

# 29. Security Rules

## Ollama

- Localhost only أثناء التطوير.
- لا تعرض port `11434` Public مستقبلًا.
- FastAPI هو Gateway للـAI.

## PostgreSQL

- Password.
- Private network.
- No public exposure في Production.

## Redis

- Private only.
- لا expose Public.

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

كل AI Job يسجل:

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

كل n8n workflow مهم يسجل:

```text
workflow
execution_id
external_reference
status
failure_reason
```

لا تخزن Sensitive text في Logs بدون حاجة.

---

# 31. Idempotency

مهم جدًا في:

- Imports
- Sync
- PDF processing
- Telegram publishing
- AI generation
- Production writes

مثال:

```text
document SHA256
```

إذا رُفع نفس الملف:

```text
Do not process it twice unless explicitly versioned.
```

Telegram:

```text
publish_key = channel + content_id + scheduled_time
```

لتقليل Double Publishing.

---

# 32. Queues

AI processing لا يعمل داخل HTTP request طويل.

مثال:

```text
POST /generate-question
→ 202 Accepted
→ job_id
```

ثم:

```text
Queue Worker
→ Retrieval
→ Ollama
→ Validation
→ Save
```

UI يعرض:

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

قبل Production data:

```text
100–500 synthetic questions
2–3 test PDFs
fake courses
```

الهدف:

- Schema.
- Jobs.
- UI.
- Embeddings.
- Ollama.
- PDF flow.

## Phase B — Real Snapshot

استورد نسخة Controlled من الـ25K سؤال.

لا تشمل Student PII.

## Phase C — Human Evaluation

استخدم الفريق لمراجعة:

- Duplicate pairs.
- AI flags.
- PDF extraction.
- Generated questions.

## Phase D — Read-only Production Integration

Signed API.

AI Lab يقرأ Changes فقط.

## Phase E — Human-approved Write Integration

فقط عندما تثبت المنظومة نفسها.

---

# 34. Recommended First MVP

لا تبدأ بجميع المشاريع.

## MVP 1 — Question Intelligence Foundation

المكونات:

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

نجاح هذه الثلاثة يعني إثبات أهم Pipeline:

```text
Existing Data
→ Clean
→ Retrieve
→ AI
→ Validate
→ Human Review
```

بعد ذلك ابدأ Features الطلاب.

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

ستتعلم:

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

ستتعلم:

- PDF parsing
- OCR
- Chunking
- Retrieval
- Grounded generation

## Automation

```text
Project 7
```

ستتعلم:

- n8n
- Telegram API workflows
- Approval
- Retry
- Scheduling

## Analytics

```text
Project 8
```

ستتعلم:

- Event tracking
- SQL
- Analytics design
- Evidence-based AI reporting

## Advanced engagement

```text
Project 9
```

ستتعلم:

- Rules engines
- Personalization
- Cohorts
- Engagement measurement

---

# 37. What Not to Build Yet

أجّل:

- Full student chatbot.
- Autonomous AI Agent with Production write access.
- Fine-tuning Gemma.
- ML prediction of exam success.
- Full recommendation ML.
- Automatic question publishing.
- Automatic Telegram publishing without approval.
- Vector search over every possible production document.
- Complex multi-agent architecture.

هذه الأشياء تضيف Complexity قبل إثبات القيمة.

---

# 38. Fine-tuning Decision

لا تبدأ Fine-tuning.

ابدأ:

```text
Good data
+ RAG
+ Structured prompts
+ Few-shot examples
+ Human review
+ Evals
```

فكر في Fine-tuning فقط إذا كان لديك:

- Large clean approved dataset.
- Repeated failure pattern.
- Reliable benchmark proving prompting/RAG are insufficient.
- Clear measurable objective.

---

# 39. Model Selection Must Be Empirical

لا تعتمد Gemma 4 E2B فقط لأن حجمه صغير.

اعمل Benchmark:

| Test | E2B | E4B | 12B |
|---|---:|---:|---:|
| Arabic duplicate accuracy | | | |
| JSON compliance | | | |
| Question quality | | | |
| Grounding | | | |
| Avg latency | | | |
| Memory usage | | | |
| Trainer acceptance | | | |

ثم اختر Model per task إن احتجت.

ربما:

```text
E2B → classification
E4B/12B → generation
```

لكن لا تعتمد هذا قبل الـBenchmark.

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

هذه المعالجة يجب أن تكون Backend jobs.

---

# 41. Telegram Development Instructions

استخدم في البداية:

- Development Bot.
- Private test channel.
- Test user accounts.
- Non-production links.

لا تستخدم Production channel أثناء بناء Workflow.

أضف Environment separation:

```text
TELEGRAM_ENV=development
TELEGRAM_BOT_TOKEN=...
TELEGRAM_CHANNEL_ID=...
```

وفي Production credentials منفصلة.

---

# 42. Data Versioning

كل شيء مهم يحتاج Versioning:

```text
Question revision
Document version
Prompt version
Model tag
Taxonomy version
Generation rule version
Assessment blueprint version
```

بدون Versioning لن تعرف لماذا اختلف Output بعد عدة أشهر.

---

# 43. Question Lifecycle

اقترح:

```text
source_imported
needs_cleanup
duplicate_review
quality_review
approved_existing
needs_revision
archived
```

للأسئلة الجديدة:

```text
ai_draft
trainer_review
moderator_review
approved
rejected
published
```

لا تخلط Imported Question مع Generated Draft.

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

فقط:

```text
approved
```

يمكن استخدامه كمصدر رسمي لتوليد سؤال.

---

# 45. AI Job Types

استخدم enum واضحًا:

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

هذا يساعد في:

- Metrics
- Retry rules
- Queue priorities
- Cost/performance analysis

---

# 46. Retry Rules

ليست كل الأخطاء Retryable.

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

تشغّل نفس Eval Dataset بعد:

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

يفضل إنشاء Staging قبل Production integration الكامل:

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

ولا تنقل Local database نفسها إلى Production.

استخدم:

- Migrations.
- Seeds.
- Controlled imports.

---

# 49. Hostinger Transition Checklist

عند الانتقال من Mac إلى VPS:

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

لا تفترض أن أداء Gemma 4 على الـMac يساوي أداءه على KVM 2؛ الـMac قد يستفيد من Apple GPU/Metal بينما KVM CPU characteristics مختلفة.

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

نجاح البرنامج لا يعني أن AI ينتج أسئلة كثيرة.

النجاح يقاس بـ:

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

ابدأ بهذا الترتيب فقط:

## Step 1

أنشئ repository:

```text
injazedu-ai-lab
```

## Step 2

شغّل:

```text
PostgreSQL + pgvector
Redis
n8n
Ollama
```

## Step 3

أنشئ Laravel + Filament Lab.

## Step 4

أنشئ FastAPI AI Service.

## Step 5

اختبر:

```text
FastAPI → Ollama → Gemma 4
FastAPI → Ollama → EmbeddingGemma
```

## Step 6

أنشئ Schema لاستيراد الأسئلة.

## Step 7

ابدأ بـ100 سؤال فقط.

نفذ:

```text
Import
→ Normalize
→ Exact duplicate
→ Embedding
→ Similarity search
```

## Step 8

بعد نجاح 100 سؤال:

```text
1,000 questions
```

ثم:

```text
25,000 questions
```

## Step 9

أنشئ أول Duplicate Review Screen.

## Step 10

لا تبدأ PDF أو Generation حتى تستطيع مراجعة Duplicate candidates بصورة موثوقة.

---

# 53. Local macOS Setup Notes

## Ollama

المتطلبات الحالية لـOllama على macOS هي macOS 14 Sonoma أو أحدث.

على Apple M-series يدعم Ollama GPU acceleration باستخدام Metal.

تحقق:

```bash
ollama --version
```

ثم:

```bash
ollama pull embeddinggemma:300m-qat-q4_0
ollama pull gemma4:e2b-it-qat
```

اختبر:

```bash
ollama list
```

---

## OCR

```bash
brew install ocrmypdf
brew install tesseract-lang
```

تحقق من اللغات:

```bash
tesseract --list-langs
```

يجب أن تجد:

```text
ara
eng
```

---

## PostgreSQL + pgvector

في Local Development الأفضل استخدام Docker image تحتوي pgvector بدل بناء Extension يدويًا كل مرة.

بعد تشغيل Database:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

---

# 54. Suggested Local Environment Variables

مثال فقط، ولا تستخدم Production secrets:

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

وجود:

```text
PRODUCTION_WRITE_ENABLED=false
```

كـKill Switch مفيد حتى بعد بدء Integration.

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

لا يكون الهدف:

> كيف نضع AI في InjazEdu؟

بل:

> ما العملية التي نريد تحسينها، وما الجزء الذي يجب أن ينفذه Code أو SQL أو Workflow أو LLM أو Human؟

التقسيم الأفضل:

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

بهذا الشكل يظل المشروع مفيدًا لـInjazEdu حتى لو تم تغيير Gemma 4 مستقبلًا إلى Model آخر.

---

# 57. Verified Technical References

تم التحقق من المعلومات التقنية التالية في أغسطس 2026:

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

هذا المستند هو **Implementation Roadmap v1.0**.

ينبغي تحديثه عند اتخاذ قرارات مهمة مثل:

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

لا تثبت هذه القرارات قبل وجود بيانات وتجارب كافية.
