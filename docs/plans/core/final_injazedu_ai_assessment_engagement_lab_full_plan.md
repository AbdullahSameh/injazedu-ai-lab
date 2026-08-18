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

هذا المستند يحدد برنامجًا من **عشرة مشاريع** حول `injazedu.co`، يحقق هدفين في وقت واحد:

1. رفع جودة الأسئلة والاختبارات، وزيادة **التفاعل** بين المدربين والطلاب.
2. استخدام المنصة كمختبر عملي لتعلّم وتطبيق: Local LLMs، Ollama، RAG، Embeddings،
   Vector Search، AI Evaluation، Document Understanding، Arabic NLP، Event-driven systems،
   Analytics، وHuman-in-the-loop AI.

البنك الحالي يحتوي على نحو **25,000 سؤال** فيها تكرار كثير. لذلك لا نبدأ بإنشاء أسئلة جديدة،
بل بتحويل البنك الموجود إلى **Question Intelligence Layer** نظيفة وقابلة للقياس.

لكن — وهذا هو أهم تغيير عن v1.0 — **التفاعل هو الهدف الأول المعلن**، ولا يجوز أن ينتظر
ستة مشاريع من أعمال البيانات. لذلك يعمل البرنامج على **مسارين متوازيين**:

```text
Track A — Question Intelligence          Track B — Engagement
(الأساس: فهم البنك وتنظيفه)               (قيمة ظاهرة مبكرًا)

P0  AI Lab Foundation
     │
P1  Profiling & Question Mirror
     │
     ├──────────────────────────────────▶ P3  Item Statistics
P2  Arabic + Duplicate Intelligence           (SQL فقط، بلا AI)
     │                                         │
P4  Question Quality Audit                P6  Telegram Engagement Engine
     │                                         │
P5  Taxonomy & Coverage Map               P7  Public Practice & Funnel
     │
     └────────────▶ Track C — Content Creation
                    P8  PDF Knowledge & Source Library
                    P9  AI Trainer Assessment Copilot

Phase D (موصوفة، غير مبنية) — Production Integration & Assessment Builder
```

القاعدة الأساسية للنظام تبقى كما هي في v1.0:

```text
Production serves students.
AI Lab prepares, analyses and recommends.
Humans approve.
```

لكن مع قيد "Production read-only" أضفنا قاعدة ثانية ضرورية (انظر §4):

```text
حيث لا يستطيع الـLab أن يسلّم شيئًا إلى Production،
يملك الـLab سطح تفاعل خاصًا به: Telegram + صفحات تدريب عامة.
```

---

## 2. ما تغيّر عن v1.0 ولماذا

خطة v1.0 **ممتازة في المبادئ**، وأغلب قراراتها المعمارية صحيحة ومحفوظة كما هي في هذا المستند.
لكنها كُتبت **قبل قراءة الـSchema الفعلية**، فبُنيت على افتراضات بيانات غير موجودة في Production.
هذا الجدول يلخص التغييرات الجوهرية:

| # | v1.0 | الواقع / v2.0 | الأثر |
|---|------|---------------|-------|
| 1 | Import JSON فيه `"explanation"` | **لا يوجد عمود explanation.** فقط `questions.description` و`questions.hint` | إعادة تعريف الـmapping قبل أي Import |
| 2 | `"correct_answer": "B"` | **لا يوجد عمود للجواب الصحيح ولا مفاتيح A/B/C/D.** الجواب يُستنتج من `options.points > 0` | يجب كشف أسئلة بلا جواب صحيح وأسئلة بأكثر من جواب |
| 3 | `"exam_ids": [40, 42]` | **مستحيل بنيويًا.** `questions.section_id → sections.quiz_id` شجرة أب واحد | إعادة تأطير البرنامج بالكامل — انظر §2.1 |
| 4 | Event tracking في Project 6 | **غير ممكن مع Production read-only.** `results` بلا أوقات، و`question_result.option_id` هو `NOT NULL` | فصل التحليلات إلى Retrospective وProspective (§7) |
| 5 | Taxonomy مفترضة موجودة | **لا يوجد أي taxonomy في Production** | Taxonomy تصبح مشروعًا مستقلًا (P5) بمخرَج بشري أولًا |
| 6 | Telegram = Project 7 (بعد ستة مشاريع) | التفاعل هو الهدف الأول | Telegram يصبح P6 على **مسار متوازٍ** يبدأ مبكرًا |
| 7 | Item statistics مدفونة في Project 8 | قابلة للحساب **اليوم** بـSQL فقط من `results`/`question_result` | تُسحب إلى الأمام كـP3 — أعلى قيمة لكل مجهود في البرنامج |
| 8 | Project 4 يدمج Quality + Coverage | Quality لا يحتاج taxonomy، Coverage يحتاجها | فصلهما إلى P4 و P5 |
| 9 | Redis + n8n في Project 0 | لا حاجة لهما حتى P6، والجهاز 16 GB | حذفهما من الأساس (ADR-011، ADR-012) |
| 10 | FastAPI وLaravel يكتبان إلى Postgres | مالكان للـSchema = Drift مضمون | Laravel يملك الـSchema، FastAPI stateless (ADR-013) |
| 11 | لا يوجد Throughput budget | ~30K زوج × ~4 ث = **~33 ساعة متصلة** على M1 Pro | LLM يعمل فقط في Uncertainty Band (§13) |
| 12 | لا Benchmark للـEmbedders | جودة الـdedup يحكمها الـembedder وليس الـLLM | Benchmark إلزامي للـembedders (§12.4) |
| 13 | `conflicting_duplicate` بلا مسار | سؤالان متطابقان بجوابين مختلفين = خطأ يمسّ الطلاب الآن | مسار تصعيد عاجل (P2) |
| 14 | STEP / IELTS غير مذكورين | كلاهما يعتمد على Passage-based item sets | Stimulus كعنصر أول (§8) |
| 15 | "Qiyas-style" بلا تعريف | لا مواصفة لعدد الأسئلة أو التوقيت أو التوزيع | Blueprint موثّقة من أهل التخصص (P5) |
| 16 | §37 يمنع Agents، والـbrief يطلبها | تناقض ظاهري | موقف صريح ومحدود (ADR-014) |
| 17 | ثلاثة ترتيبات متعارضة (§34، §35، §52) | — | ترتيب واحد فقط (§34 في هذا المستند) |
| 18 | لا Effort estimates ولا Go/No-Go | — | كل مشروع له تقدير جهد وحدود قبول/رفض |

### 2.1 الاكتشاف الأهم: التكرار ليس إهمالًا، بل نتيجة بنيوية

في Production، السؤال يرتبط بقسم واحد، والقسم يرتبط باختبار واحد:

```text
questions.section_id → sections.quiz_id → quizzes.id
```

لا يوجد جدول many-to-many بين الأسئلة والاختبارات. **إذن لا توجد طريقة لإعادة استخدام سؤال
في اختبار آخر إلا بنسخه.** هذا يفسّر وجود ~25,000 سؤال مع تكرار كثيف: كل مرة أراد مدرب
استخدام سؤال جيد في اختبار جديد، أنشأ نسخة.

النتيجة على مستوى البرنامج:

- التكرار **عَرَض**، والسبب الجذري هو **غياب Item Bank قابل لإعادة الاستخدام**.
- تنظيف التكرار (P2) يعالج العَرَض، وهذا مفيد وضروري لكنه **لا يمنع عودته**.
- العلاج الجذري هو `question_pool` + علاقة many-to-many في Production — وهذا **يحتاج تعديل
  Production**، فيقع في Phase D. وهو البند الأهم فيها.
- لذلك: لا نعد أحدًا بأن "التكرار سينتهي". نعد بأننا سنقيسه، ونجمّعه في clusters، ونمنع
  استخدام أكثر من عضو من العنقود في اختبار واحد، ونصلح الأخطاء الخطيرة.

هذا التأطير يجب أن يظهر في أي عرض للإدارة، لأن v1.0 كانت ستُقاس على وعد لا تستطيع الوفاء به.

---

## 3. القيود الأربعة الحاكمة

كل قرار في هذا المستند مشتق من هذه القيود. إذا تغيّر أحدها، يُعاد فتح المستند.

### 3.1 Production read-only

لا migrations، لا تعديل كود، لا كتابة بيانات على `injazedu.co`.

**ما يقتله هذا القيد من v1.0:**

| v1.0 | لماذا لا يعمل |
|------|----------------|
| Project 6 — Assessment Builder يعمل في Production | `quizzes` لا تحتوي type ولا pass_mark ولا attempt_limit ولا نافذة زمنية ولا pool-draw |
| Event Tracking (Projects 6/8/9) | يحتاج جدول أحداث جديد + instrumentation في كود Production |
| Write-back للأسئلة المعتمدة (§26) | يحتاج endpoint كتابة في Production |
| Personalized Practice داخل المنصة | يحتاج قراءة سلوك حقيقي وكتابة توصيات |

**ما يبقى ممكنًا بالكامل:** كل تحليل، كل تنظيف، كل استخراج، كل توليد draft، وكل تفاعل عبر
قناة يملكها الـLab. وهذا كثير — انظر §7.

### 3.2 التفاعل هو الهدف الأول

من الـbrief: «نركز أساسًا على التفاعل» و«فريق المدرشن ينشر الاختبارات على Telegram تلقائيًا»
و«تشجيع الطلاب على التسجيل في الدورات المفتوحة».

v1.0 وضعت Telegram في Project 7، أي بعد ستة مشاريع (شهور). هذا **انقلاب في الأولويات**.
في v2.0، Telegram على مسار متوازٍ يبدأ بعد P1، ولا ينتظر تنظيف البنك بالكامل.

### 3.3 M1 Pro / 16 GB

الجهاز يحدد الـstack والسعة الحسابية:

- حذف Redis وn8n من الأساس.
- `gemma4:e2b-it-qat` هو نموذج العمل؛ الأكبر يُقاس في **جلسات معزولة** فقط.
- ميزانية Throughput صريحة (§13) — لأن "LLM يراجع كل الـcandidates" غير محدود على هذا الجهاز.

### 3.4 طاقة المراجعة البشرية

المتاح: **المطوّر (أنت) + فريق المدرشن + بعض المدربين**.

هذا مورد نادر ومكلف، وهو **العائق الحقيقي** في أي نظام Human-in-the-loop. لذلك:

- كل خطوة مراجعة بشرية لها **ميزانية** بالساعات (§13.3).
- المراجعة تُوجَّه بـActive Learning: يراجع الإنسان فقط ما هو قريب من حدّ القرار، لا كل شيء.
- المدربون (الأندر) للأحكام التخصصية فقط: صحة الجواب، جودة السؤال، الـtaxonomy.
- المدرشن للعمل التشغيلي المتكرر: مراجعة أزواج التكرار، اعتماد النشر.

### 3.5 معطى إضافي: نسخة Production موجودة محليًا

توجد نسخة من قاعدة بيانات Production على الـMac. هذا **يبسّط P1 كثيرًا** (لا حاجة لبناء
مُصدِّر ولا اتفاق تصدير)، لكنه **يُنشئ مسؤولية حماية بيانات فورية** — النسخة تحتوي
`users` (إيميلات، جوالات)، `orders`، و`certificates.id_number` (هوية وطنية)، و`complaints`.
انظر §14 للقواعد الإلزامية.

---

## 4. Responsibility Model & ADRs

### 4.1 القرارات المحفوظة من v1.0

هذه صحيحة وتبقى نافذة كما هي:

| ADR | القرار |
|-----|--------|
| ADR-001 | Production يبقى Source of Truth للهوية والتسجيل والدفع والنتائج الرسمية |
| ADR-002 | AI Lab يبدأ read-only بالنسبة لـProduction |
| ADR-003 | Ollama لا يُعرَّض لـProduction ولا للإنترنت؛ FastAPI هو الـGateway |
| ADR-004 | Embeddings من نموذج embedding مخصص، لا من الـLLM التوليدي |
| ADR-005 | كل مخرَج AI يؤثر على محتوى تعليمي يحتاج مراجعة بشرية |
| ADR-006 | n8n ينسّق ولا يملك business logic |
| ADR-007 | النص العربي الأصلي محفوظ؛ التطبيع مرتبط بالمهمة |
| ADR-008 | توليد الأسئلة مُسنَد إلى مصدر ومفحوص للتكرار قبل الاعتماد |
| ADR-009 | الحسابات التحليلية deterministic؛ الـLLM يشرحها ولا يحسبها |
| ADR-010 | التطوير المحلي على نسخ مضبوطة قبل أي تكامل حيّ |

### 4.2 ADR-011 — لا Redis في الأساس

**القرار:** طوابير الـLab تعمل على Postgres (`database` queue driver)؛ لا Redis حتى تظهر حاجة محددة.

**السبب:** Production نفسها تستخدم `database` driver (جدول `jobs`). الـLab لديه Postgres أصلًا.
Redis يستحق وجوده عند الحاجة إلى distributed locks أو rate limiting أو أحمال عالية —
ولا شيء من ذلك ينطبق على مختبر بجهاز واحد. الفائدة: خدمة أقل، ~200 MB أقل، ومفهوم أقل للتعلّم
في وقت واحد.

**إعادة النظر:** عند P6 (حدود Telegram). وحتى هناك، قد تكفي Postgres advisory locks + جدول
token bucket.

### 4.3 ADR-012 — n8n يُؤجَّل إلى P6، ودوره محدود

**القرار:** لا n8n في P0. يُدخَل في P6 للأجزاء التي شكلها فعلًا orchestration — جدولة، نداء
API، تنبيه عند الفشل، ربط أنظمة — وكل business rule يبقى في Laravel.

**السبب — بصراحة:** n8n هدف تعلّم معلن، لكن قيمته الهندسية هنا محدودة، لأن v1.0 نفسها تقول
في §17 Workflow E إن الاعتماد وسجل التدقيق يجب أن يكونا داخل Filament. إذا كان الاعتماد في
Filament والمنطق في Laravel، فما يتبقى لـn8n هو الجدولة والتنبيه. هذا كافٍ لتحقيق هدف التعلّم
دون قلب المسؤوليات.

**البديل المقبول:** Laravel Scheduler + Jobs يؤدّي الوظيفة نفسها. الاختيار بين الاثنين
قرار تعلّم، لا قرار هندسي — وهذا مذكور صريحًا حتى لا يُقاس المشروع على n8n.

### 4.4 ADR-013 — Laravel يملك الـSchema؛ FastAPI stateless

**القرار:** كل migrations لجداول الـLab في Laravel. FastAPI يستقبل payload ويعيد JSON،
ولا يكتب في جداول الـLab.

**استثناء واحد ضيّق ومعلن:** كتابة الـembeddings بالجملة (bulk) يجوز أن تذهب مباشرة من
FastAPI، وفقط إلى `question_embeddings` و`document_chunk_embeddings`، وهذان الجدولان
يبقى Laravel هو من يهاجرهما.

**السبب:** في v1.0 §3.1 يظهر FastAPI وLaravel كِلاهما يكتب إلى PostgreSQL. مالكان للـschema
يعني drift مؤكّد وتصحيحًا مؤلمًا لاحقًا.

### 4.5 ADR-014 — تعريف "Agents" في هذا البرنامج

الـbrief يطلب «Local AI models & agents»، وv1.0 §37 تمنع «Complex multi-agent architecture».
لا تناقض إذا حددنا المصطلح:

**Agent هنا = حلقة محدودة الأدوات، بسقف خطوات، وبوابة بشرية.** ليست منظومة عملاء مستقلة.

| النوع | التصنيف |
|-------|---------|
| Duplicate adjudication، Quality review، Classification | **ليست agents** — نداء واحد بمخرَج structured |
| Trainer Copilot session (P9) | **agent حقيقي محدود** — يستطيع نداء `retrieve_source` و`search_similar_questions` و`validate_draft` في حلقة، بسقف ~6 خطوات، وينتهي إلزامًا بـdraft + citations لمراجعة بشرية |
| كتابة في Production، نشر، حذف، تغيير جواب صحيح | **ممنوع لأي agent** |

### 4.6 ADR-015 — الـLab يملك سطح التفاعل (جديد وجوهري)

**القرار:** بما أن Production read-only، فإن الـLab **يملك سطحًا للتفاعل خاصًا به**:
بوت Telegram وصفحات تدريب عامة مستضافة على الـLab. هذا المحتوى **غير رسمي وغير مُدرَّج**
(ungraded)، عام، وموجّه لأعلى القمع التسويقي. التحويل يحدث بالربط الخارجي إلى صفحات الدورات
في Production عبر روابط متتبَّعة.

**لماذا هذا ضروري:** v1.0 تقول «Production serves students» و«AI Lab prepares and recommends».
مع read-only، لا توجد قناة يسلّم الـLab من خلالها أي شيء إلى Production. فإما أن يملك الـLab
سطحًا، أو لا يصل أي شيء إلى أي طالب. وهذا ليس تنازلًا فقط: التدريب العام المجاني هو منتج
أعلى قمع بطبيعته، ولا يوجد سبب لوضعه خلف تسجيل الدخول.

**الحدود الصريحة:** الـLab لا يمنح شهادة، ولا يحسب نتيجة رسمية، ولا يخزّن PII، ولا يصادق
هوية طالب، ولا يدّعي أنه نتيجة معتمدة. الاختبارات الرسمية تبقى في Production وحدها.

**نقطة الالتقاء:** Phase D — عند فتح Production، يُدمج السطحان.

---

# Part II — Production Reality

هذا الجزء هو ما كان ناقصًا في v1.0. كل ما بعده مبني عليه.
كل سطر هنا مصدره `docs/schema/injazedu-db-schema.md`.

## 5. خريطة المفاهيم → الواقع

| المفهوم | الواقع في Production | معالجة الـLab |
|---------|---------------------|----------------|
| نص السؤال | `questions.name` — **LONGTEXT**، قد يحتوي HTML | `raw_text` / `clean_text` / `search_text` |
| الشرح (explanation) | **لا يوجد عمود.** المتاح `questions.description` (TEXT) و`questions.hint` (TEXT) | `explanation_raw` ← `description`، `hint_raw` ← `hint`. **قِس نسبة الامتلاء قبل الاعتماد عليه** |
| الخيارات | `options.name`، `options.points`، `options.order` | `option_index` مشتق من (`order` ASC، ثم `id` ASC) |
| مفاتيح A/B/C/D | **لا وجود لها** | تُصطنع في الـLab فقط للعرض والـprompts، ولا تُعاد إلى Production |
| الجواب الصحيح | **لا يوجد عمود.** يُستنتج: `options.points > 0` | `correct_option_ids[]` + علَم `correct_count` |
| سؤال ← اختبار | `questions.section_id → sections.quiz_id`. **اختبار واحد فقط لكل سؤال** | لا many-to-many. انظر §2.1 |
| الدورة | `quizzes.course_id` (nullable) | `NULL` ⇒ اختبار عام/مفتوح |
| التخصص | `quizzes.category_id` → شجرة `categories` (`parent_id` INT، self-ref، **بلا FK**) | نسخ الشجرة؛ الانتباه لعدم تطابق النوع INT / BIGINT |
| المحاضرة | `quizzes.lecture_id` | مؤشر على post-lecture quiz |
| الوسائط | `quiz_files` — ENUM(video, image, audio)، مرتبط بـ**section أو question**؛ وأيضًا `<img>` داخل `questions.name` | فحص المسارين معًا |
| النص المشترك (passage) | `sections.name` + `sections.description` + `quiz_files.section_id` | كائن `stimulus` مستقل — §8 |
| المدة | `quizzes.duration` INT، default 10 | للاختبار كله فقط. **لا يوجد توقيت لكل سؤال** |
| مؤلف السؤال | **لا يوجد.** فقط `quizzes.user_id` | النسبة على مستوى الاختبار لا السؤال |
| المحاولات | `results(total_points, user_id, quiz_id)` | لا رقم محاولة؛ يُشتق بترتيب `created_at` لكل (user, quiz) |
| الأجوبة | `question_result(result_id, question_id, option_id, points)` | `option_id` **NOT NULL** ⇒ **لا يمكن تسجيل سؤال متروك** |
| صحة الجواب | لا عمود `is_correct` | `points > 0` هو الإشارة |
| Soft deletes | `deleted_at` على quizzes / sections / questions / options / results | نسخ كل الصفوف مع `source_deleted_at`، واستثناؤها من التحليل النشط |
| التسجيل في دورة | **غامض**: `course_user` مقابل `orders` + `course_order.expiry_date` | **يُحسم بالاستعلام قبل أي تحليل** — §6.2 |
| قنوات Telegram | `courses.telegram_channel` / `telegram_group` / `telegram_private` (VARCHAR) | نسخها إلى جدول channels مع علَم `is_public` صريح |
| الأدوار | Spatie بأسماء جداول مخصصة: `user_roles`، `user_permissions`، `role_permissions`، morph key = `user_id` | أي أداة تفترض الأسماء الافتراضية ستفشل |

### 5.1 قواعد استنتاج الجواب الصحيح (إلزامية)

هذه أهم قاعدة في الـETL كلها، لأن كل شيء لاحق يعتمد عليها:

```text
correct_option_ids = [o.id for o in options if o.deleted_at IS NULL and o.points > 0]
correct_count      = len(correct_option_ids)

correct_count == 1  →  single_correct        (الحالة المتوقعة)
correct_count == 0  →  BROKEN_NO_KEY         (سؤال معطوب — لا يُستخدم، ويُصعَّد)
correct_count >  1  →  MULTI_KEY             (إما multi-select أو partial credit — يُحسم بالقياس)
```

`MULTI_KEY` يحتاج قرارًا: هل النظام يدعم أكثر من جواب صحيح، أم أن هذه أخطاء إدخال؟
الاستعلام 4 في §6.1 (توزيع `points`) يجيب: إذا كانت القيم كلها 0/1 فهي أخطاء غالبًا؛
إذا كانت متدرجة فهي partial credit مقصود.

### 5.2 قاعدة اشتقاق ترتيب الخيارات

`options.order` نوعه INT وقيمته الافتراضية **0**، فقد يتكرر داخل السؤال نفسه.
الترتيب العشوائي يعني أن "الخيار B" يتغير بين تشغيل وآخر — وهذا يفسد الـprompts والـhashes
والمراجعة البشرية.

```text
ORDER BY `order` ASC, id ASC   -- إلزامي وثابت في كل مكان
```

`order` كلمة محجوزة في MySQL → backticks دائمًا.

---

## 6. حزمة استعلامات الفحص (Profiling Query Pack)

**هذه أول مهمة عملية في البرنامج، وتُنفَّذ قبل بناء أي شيء.**
v1.0 قالت «استورد 25 ألف سؤال» دون أن تقيس البنك أولًا. لا نبني على رقم غير مُقاس.

تُشغَّل على **النسخة المحلية** من MySQL. كلها SELECT فقط.

### 6.1 استعلامات البنك

```sql
-- 1) حجم البنك الحقيقي
SELECT COUNT(*) AS total,
       SUM(deleted_at IS NULL) AS active,
       SUM(deleted_at IS NOT NULL) AS soft_deleted
FROM questions;

-- 2) توزيع عدد الخيارات لكل سؤال
SELECT opt_count, COUNT(*) AS questions FROM (
  SELECT q.id, COUNT(o.id) AS opt_count
  FROM questions q
  LEFT JOIN options o ON o.question_id = q.id AND o.deleted_at IS NULL
  WHERE q.deleted_at IS NULL
  GROUP BY q.id
) t GROUP BY opt_count ORDER BY opt_count;

-- 3) *** الأهم *** سلامة الجواب الصحيح
SELECT correct_count, COUNT(*) AS questions FROM (
  SELECT q.id, SUM(CASE WHEN o.points > 0 THEN 1 ELSE 0 END) AS correct_count
  FROM questions q
  LEFT JOIN options o ON o.question_id = q.id AND o.deleted_at IS NULL
  WHERE q.deleted_at IS NULL
  GROUP BY q.id
) t GROUP BY correct_count ORDER BY correct_count;

-- 4) توزيع points: هل 0/1 أم درجات متفاوتة؟
SELECT points, COUNT(*) AS options_count
FROM options WHERE deleted_at IS NULL
GROUP BY points ORDER BY points;

-- 5) تكرار قيم order داخل السؤال (خطر اشتقاق المفاتيح)
SELECT COUNT(*) AS questions_with_order_ties FROM (
  SELECT question_id FROM options WHERE deleted_at IS NULL
  GROUP BY question_id, `order` HAVING COUNT(*) > 1
) t;

-- 6) نسبة امتلاء description / hint (بديل explanation)
SELECT COUNT(*) AS total,
       SUM(description IS NOT NULL AND TRIM(description) <> '') AS has_description,
       SUM(hint IS NOT NULL AND TRIM(hint) <> '')               AS has_hint
FROM questions WHERE deleted_at IS NULL;

-- 7) عام مقابل خاص بدورة
SELECT CASE WHEN course_id IS NULL THEN 'general' ELSE 'course' END AS kind,
       COUNT(*) AS quizzes, SUM(status = 1) AS active
FROM quizzes WHERE deleted_at IS NULL GROUP BY kind;

-- 8) عدد الأسئلة لكل اختبار (لفهم أحجام الاختبارات الفعلية)
SELECT questions_per_quiz, COUNT(*) AS quizzes FROM (
  SELECT z.id, COUNT(q.id) AS questions_per_quiz
  FROM quizzes z
  JOIN sections s  ON s.quiz_id = z.id     AND s.deleted_at IS NULL
  JOIN questions q ON q.section_id = s.id  AND q.deleted_at IS NULL
  WHERE z.deleted_at IS NULL
  GROUP BY z.id
) t GROUP BY questions_per_quiz ORDER BY questions_per_quiz;

-- 9) HTML والصور داخل نص السؤال
SELECT COUNT(*) AS total,
       SUM(name LIKE '%<img%')          AS has_img_tag,
       SUM(name LIKE '%<%')             AS has_any_html,
       SUM(CHAR_LENGTH(name) > 1000)    AS long_stems,
       MAX(CHAR_LENGTH(name))           AS longest_stem
FROM questions WHERE deleted_at IS NULL;

-- 10) الوسائط: على مستوى السؤال أم القسم؟
SELECT type,
       SUM(question_id IS NOT NULL) AS at_question,
       SUM(section_id  IS NOT NULL) AS at_section,
       COUNT(*) AS total
FROM quiz_files GROUP BY type;

-- 11) نظرة أولى على التكرار الحرفي (بلا تطبيع بعد)
SELECT COUNT(*) AS duplicate_groups,
       SUM(c) - COUNT(*) AS redundant_questions
FROM (
  SELECT MD5(name) AS h, COUNT(*) AS c
  FROM questions WHERE deleted_at IS NULL
  GROUP BY h HAVING COUNT(*) > 1
) t;

-- 12) الأقسام التي تحمل نصًا مشتركًا (passage) — مهم لـSTEP/IELTS
SELECT COUNT(*) AS sections_total,
       SUM(description IS NOT NULL AND CHAR_LENGTH(description) > 200) AS long_stimulus,
       SUM(name IS NOT NULL AND TRIM(name) <> '') AS named
FROM sections WHERE deleted_at IS NULL;
```

### 6.2 استعلامات البيانات السلوكية (أساس P3)

```sql
-- 13) حجم بيانات الإجابات المتاحة للإحصاء
SELECT COUNT(*) AS answers,
       COUNT(DISTINCT result_id)   AS results,
       COUNT(DISTINCT question_id) AS questions_with_data
FROM question_result;

-- 14) كم سؤالًا لديه عدد إجابات يكفي للإحصاء؟
SELECT bucket, COUNT(*) AS questions FROM (
  SELECT question_id, CASE
    WHEN COUNT(*) >= 100 THEN 'a_100_plus'
    WHEN COUNT(*) >=  30 THEN 'b_30_to_99'
    WHEN COUNT(*) >=  10 THEN 'c_10_to_29'
    ELSE 'd_under_10' END AS bucket
  FROM question_result GROUP BY question_id
) t GROUP BY bucket ORDER BY bucket;

-- 15) *** حسم غموض التسجيل *** course_user أم course_order؟
SELECT 'course_user' AS src, COUNT(*) AS rows_,
       COUNT(DISTINCT user_id) AS users_, COUNT(DISTINCT course_id) AS courses_
FROM course_user
UNION ALL
SELECT 'course_order', COUNT(*),
       COUNT(DISTINCT o.user_id), COUNT(DISTINCT co.course_id)
FROM course_order co JOIN orders o ON o.id = co.order_id;

-- 16) من هم مستخدمو course_user؟ مدربون أم طلاب؟
SELECT r.name AS role, COUNT(DISTINCT cu.user_id) AS users_in_course_user
FROM course_user cu
JOIN user_roles ur ON ur.user_id = cu.user_id
JOIN roles r       ON r.id = ur.role_id
GROUP BY r.name ORDER BY users_in_course_user DESC;

-- 17) تغطية قنوات Telegram (أساس P6)
SELECT COUNT(*) AS courses,
       SUM(telegram_channel IS NOT NULL AND TRIM(telegram_channel) <> '') AS has_channel,
       SUM(telegram_group   IS NOT NULL AND TRIM(telegram_group)   <> '') AS has_group,
       SUM(telegram_private IS NOT NULL AND TRIM(telegram_private) <> '') AS has_private
FROM courses WHERE deleted_at IS NULL;

-- 18) هل جدول book_course القديم مهجور فعلًا؟
SELECT COUNT(*) AS book_course_rows FROM book_course;
```

### 6.3 ما يتغير حسب النتائج

| النتيجة | الأثر على الخطة |
|---------|------------------|
| `correct_count = 0` كثير (>2%) | تصحيح الأسئلة المعطوبة يصبح **أول مخرَج** في البرنامج، قبل أي dedup |
| `correct_count > 1` كثير | حسم دلالة multi-key قبل الـETL؛ يؤثر على الـhash وعلى الـpolls في P6 |
| `has_description` منخفض (<30%) | مسار الشرح في P9 يبدأ من الصفر؛ لا يمكن استخدامه كـfew-shot examples |
| `has_img_tag` مرتفع (>10%) | مسار الوسائط يصبح مشروعًا فرعيًا لا حالة هامشية |
| أسئلة كثيرة بأقل من 10 إجابات | P3 يقتصر على البنك النشط؛ يُذكر حجم التغطية صريحًا |
| `course_user` كله مدربون | التسجيل = `course_order`؛ يُثبَّت في الوثائق ويُبنى عليه |
| `long_stimulus` كبير | §8 (passage-based) يرتفع من "إضافة" إلى "متطلب أساسي" |
| العدد الفعلي ≠ 25,000 | تُحدَّث كل التقديرات في §13 |

---

## 7. ما يمكن حسابه وما لا يمكن — مع Production read-only

الجدول الذي كان يجب أن يكون في v1.0 قبل وعود Project 8.

### 7.1 متاح اليوم من بيانات Production الموجودة

| المقياس | المصدر |
|---------|--------|
| عدد مرات ظهور السؤال | `COUNT(question_result)` لكل `question_id` |
| نسبة الإجابة الصحيحة (p-value) | `AVG(points > 0)` لكل سؤال |
| توزيع اختيار المشوّشات | `COUNT(*) GROUP BY option_id` |
| معامل التمييز (point-biserial) | ارتباط درجة السؤال بدرجة الاختبار من `results.total_points` |
| توزيع الدرجات لكل اختبار | `results.total_points` |
| عدد المحاولات لكل (طالب، اختبار) | `COUNT(results)` مجمّعًا |
| الأداء حسب الدورة / التخصص | عبر `quizzes.course_id` / `category_id` |
| ترتيب المحاولات (أولى مقابل متكررة) | ترتيب `results.created_at` |
| تقدير تقريبي لمدة الاختبار | `results.updated_at - created_at` — **تقريب ضعيف، يُوسم كذلك** |

### 7.2 غير متاح — ويحتاج Phase D

| المقياس | السبب البنيوي |
|---------|----------------|
| زمن الإجابة لكل سؤال | لا عمود توقيت في `question_result` |
| زمن بدء/تسليم الاختبار | لا `started_at` / `submitted_at` في `results` |
| الأسئلة المتروكة (skipped) | `question_result.option_id` هو **NOT NULL** — الصف لا يُنشأ أصلًا |
| نسبة الترك (abandonment) | لا يوجد سجل لاختبار بدأ ولم يكتمل — تُسجَّل النتائج المكتملة فقط |
| سؤال الانسحاب (drop-off) | يتبع ما سبق |
| تغيير الإجابة | لا سجل تاريخي |
| `question_viewed` وأحداث الواجهة | لا instrumentation |
| تحويل Telegram → اختبار في Production | لا تتبّع روابط في Production |

### 7.3 متاح بالكامل — على أسطح يملكها الـLab

هذا ما يجعل P6/P7 ذات قيمة حقيقية رغم القيد: **الـLab يستطيع قياس كل شيء على سطحه الخاص.**

```text
practice_started      answer_changed        cta_clicked
item_viewed           item_flagged         link_clicked
answer_selected       practice_completed   poll_answered (Telegram)
                      practice_abandoned
```

**الحدّ الصريح:** القمع يُقاس حتى النقرة الخارجة إلى Production، ولا نرى الشراء.
إغلاق هذه الحلقة يحتاج Phase D. **يُذكر هذا في كل تقرير تحويل** حتى لا يُقرأ الرقم خطأً.

---

## 8. الأسئلة المبنية على نص مشترك (STEP / IELTS / القدرات اللفظية)

الـbrief يذكر أقسام STEP وIELTS. v1.0 تجاهلتهما تمامًا، ونموذج السؤال فيها MCQ مسطّح.
لكن هذه الاختبارات تقوم على **مجموعات أسئلة تشترك في نص أو تسجيل واحد**.

**الخبر الجيد:** Production يدعم ذلك بنيويًا:

```text
sections.name         → عنوان النص
sections.description  → النص المشترك (TEXT)
quiz_files.section_id → صوت للاستماع / صورة
questions.section_id  → الأسئلة التابعة للنص
```

**ما يجب أن يفعله الـLab:**

1. `stimulus` كائن أول الدرجة، مشتق من الـsection، له `raw/clean/search` وembedding خاص.
2. `item_set` = (stimulus + أسئلته)، ويُتعامل معه كوحدة في الاختيار والنشر.
3. **الـdedup يعمل على مستويين**: تكرار النص، وتكرار السؤال داخل نص. سؤالان متطابقان
   نصيًا لكن على نصّين مختلفين **ليسا تكرارًا** — هذا خطأ شائع سيولّد false positives كثيرة
   إن لم يُعالج.
4. حدّ الـ2K token في EmbeddingGemma يعني أن نصًا طويلًا **لن يدخل كاملًا** في embedding واحد.
   القاعدة: embedding للسؤال يُبنى من (السؤال + الخيارات + **مقتطف** من النص)، لا النص كله.
5. عناصر الاستماع (`quiz_files.type = 'audio'`) خارج نطاق المعالجة النصية بالكامل، وتُوسم
   `requires_media_review` وتُخرَج من مسارات النص.

**نتيجة على النشر:** أسئلة النصوص المشتركة **غير مناسبة لـTelegram polls** (حدّ 300 حرف للسؤال).
تُنشر كرابط إلى صفحة تدريب في P7.

---

## 9. مشاكل معروفة في Production يجب أن يعرفها الـETL

لا نصلح هذه (Production read-only)، لكن تجاهلها يُنتج بيانات خاطئة في الـLab.

| # | الملاحظة | تأثيرها على الـETL |
|---|----------|--------------------|
| 1 | `users.interested_course` INT بلا FK ولا علاقة Eloquent | إشارة تسويقية مهملة؛ قد تفيد P7 — تُفحص ولا يُعتمد عليها |
| 2 | `categories.parent_id` INT بينما `id` هو BIGINT UNSIGNED، بلا FK | نسخ الشجرة بحذر؛ توقّع أيتامًا (orphans) وحلقات |
| 3 | `orders.signature` يُستخدم أيضًا لتخزين كود الكوبون | لا علاقة له بالأسئلة؛ لا يُنقَل أصلًا |
| 4 | `book_course` مهجور (استعلام 18) | لا يُنقَل |
| 5 | `quiz_files` بلا soft delete — الحذف نهائي | مرجع وسائط قد يكون ميتًا؛ يُتحقق من وجود الملف |
| 6 | `Lecture.$fillable` لا يشمل `youtube_id` / `upload_status` | لا يخصّ الأسئلة؛ ملاحظة فقط |
| 7 | `Section.$fillable` لا يشمل `description` | العمود موجود وقد يكون ممتلئًا رغم ذلك — **يُقرأ ولا يُفترض فراغه** (مهم لـ§8) |
| 8 | `orders.uuid` بلا UNIQUE؛ `amount`/`currency` نصوص | خارج النطاق |
| 9 | Spatie بأسماء جداول غير افتراضية | أي استعلام أدوار يستخدم `user_roles` لا `model_has_roles` |
| 10 | `quizzes.status` TINYINT، و`questions` بلا status | لا حالة على مستوى السؤال — حالة الـLab هي الحالة الوحيدة |
| 11 | `sorte_order` (خطأ إملائي متكرر) | يُنسخ بالاسم الصحيح في الـLab مع توثيق الأصل |
| 12 | Telescope مُفعَّل على Production | لا يُنقَل؛ قد يحتوي بيانات حساسة |

---

# Part III — Platform

## 10. المعمارية المحلية (مضبوطة على 16 GB)

```text
Mac — M1 Pro, 16 GB
┌────────────────────────────────────────────────────────────┐
│  Laravel 12 + Filament (native)          ← يملك الـSchema  │
│  ├── Question Inventory & Profiling                        │
│  ├── Duplicate Review Console                              │
│  ├── Item Statistics Dashboard                             │
│  ├── Quality & Taxonomy Review                             │
│  ├── Moderator Publishing Console                          │
│  ├── PDF Library                                           │
│  ├── Trainer Copilot                                       │
│  └── Queue Worker (database driver)                        │
│                    │                                        │
│                    ▼ HTTP (JSON فقط)                        │
│  FastAPI (native, uvicorn)                ← stateless      │
│  ├── Arabic normalization                                  │
│  ├── Hashing & lexical similarity                          │
│  ├── Embeddings (+ الـprefixes الإلزامية)                   │
│  ├── LLM calls (structured output + validation)             │
│  ├── PDF extraction / OCR orchestration                    │
│  └── Retrieval helpers                                     │
│           │                              │                  │
│           ▼                              ▼                  │
│  Ollama (native, Metal)          PostgreSQL 17 + pgvector   │
│  ├── gemma4:e2b-it-qat                  (Docker, memory-capped)│
│  └── embeddinggemma:300m-qat-q4_0                            │
│                                                             │
│  MySQL 8 (Docker, read-only mount)  ← نسخة Production       │
│                                       تُقرأ ولا تُعدَّل      │
│  [n8n — يُضاف في P6 فقط]                                     │
│  [Redis — غير موجود؛ ADR-011]                                │
└────────────────────────────────────────────────────────────┘
```

**قرار Native مقابل Docker:** على 16 GB، Docker Desktop نفسه يستهلك 1–2 GB. لذلك:
Postgres وMySQL في Docker (نحتاج pgvector ونسخة MySQL معزولة)، وكل ما تبقى native.
بديل أخف: OrbStack بدل Docker Desktop، أو Postgres عبر Homebrew مع pgvector.

## 11. بنية المستودع

```text
injazedu-ai-lab/
├── apps/
│   ├── lab/                    Laravel 12 + Filament  (يملك كل migrations)
│   └── ai-service/             FastAPI (stateless)
├── infrastructure/
│   ├── docker/                 compose + Dockerfiles
│   ├── postgres/               init.sql (CREATE EXTENSION vector)
│   └── n8n/                    يُضاف في P6
├── data/
│   ├── snapshots/              ← .gitignore — نسخ Production (حساسة)
│   ├── fixtures/               بيانات صناعية للتطوير
│   └── exports/                حِزَم الأسئلة المعتمدة (P9)
├── storage/
│   ├── documents/              PDF الأصلية (private)
│   └── extracted/              نواتج الاستخراج
├── sql/
│   └── profiling/              حزمة §6 كملفات قابلة للتشغيل
├── evals/
│   ├── duplicate-detection/    embedder + adjudicator eval sets
│   ├── question-quality/
│   ├── arabic/
│   ├── pdf/
│   └── generation/
├── prompts/                    registry مُصدَّر من DB للمراجعة في Git
├── docs/
│   ├── plans/core/             هذا المستند
│   ├── schema/                 مرجع Production
│   ├── architecture/
│   ├── ADR/
│   └── runbooks/
├── docker-compose.yml
└── README.md
```

## 12. النماذج المحلية

### 12.1 الوسوم — مُتحقَّق منها

تم التحقق من هذه الوسوم مقابل `ollama.com` و`ai.google.dev` بتاريخ 2026-08-17.
**وسوم v1.0 صحيحة ولا تحتاج تصحيحًا:**

```bash
ollama pull embeddinggemma:300m-qat-q4_0    # 239 MB — 2K context
ollama pull gemma4:e2b-it-qat               # نموذج العمل
```

عائلة Gemma 4 المتاحة: `e2b`، `e4b`، `12b`، `26b`، `31b` — متعددة الوسائط (نص + صورة)،
بسياق 128K–256K، وبأنماط تفكير قابلة للضبط.

### 12.2 EmbeddingGemma — تفاصيل حاسمة غائبة عن v1.0

| الخاصية | القيمة | الأثر |
|---------|--------|-------|
| الأبعاد | **768**، مع Matryoshka → 512 / 256 / 128 | 512 يوفّر 33% من التخزين — **يُقاس قبل الاعتماد** |
| السياق | **2K tokens فقط** | النصوص الطويلة تُقتطع — يجب تسجيل حالات الاقتطاع |
| **الـPrefixes** | **إلزامية** | إغفالها يُخفّض جودة الاسترجاع بشكل ملموس |

الصيغ الصحيحة:

```text
# للتشابه والتكرار (متناظر — يُستخدم على الطرفين)
task: sentence similarity | query: {text}

# لمقاطع المستندات في RAG
title: {heading | "none"} | text: {chunk}

# لاستعلامات RAG
task: search result | query: {text}
```

**قاعدة إلزامية:** الـprefix جزء من عقد الـembedding. تغييره **يُبطل كل الـvectors المخزَّنة**.
لذلك كل صف vector يحمل `embedding_config_version` يشمل (model tag + prefix template + dimension
+ normalization). بدون هذا، أول تغيير prefix سيُنتج مقارنات صامتة الخطأ.

### 12.3 ميزانية الذاكرة على 16 GB

| المكوّن | تقدير |
|---------|-------|
| macOS + المحرر + المتصفح | 4–6 GB |
| Ollama + `gemma4:e2b-it-qat` | ~3 GB |
| Ollama + `embeddinggemma:300m-qat-q4_0` | ~0.3 GB |
| Postgres (Docker، `shared_buffers=512MB`، سقف 1.5 GB) | ~1.5 GB |
| MySQL (Docker، عند الـETL فقط) | ~1 GB |
| Laravel + queue worker | ~0.4 GB |
| FastAPI | ~0.3 GB |
| **المجموع** | **~11–13 GB** |

النتيجة العملية:

- `e2b-it-qat` هو نموذج العمل الدائم.
- `e4b` (~5.5 GB) و`12b` **لا يعملان مع الـstack كاملًا**. يُقاسان في **جلسات معزولة**:
  إيقاف Docker والمتصفح والـworkers.
- إعدادات Ollama:

```bash
OLLAMA_MAX_LOADED_MODELS=2      # chat + embed معًا فقط
OLLAMA_NUM_PARALLEL=1
OLLAMA_KEEP_ALIVE=5m
```

- `num_ctx=4096` كأساس. **نصيحة v1.0 صحيحة، والسبب يستحق التوضيح:** Gemma 4 يدعم 128K،
  لكن KV cache بسياق 128K يتجاوز حجم أوزان النموذج نفسها. السياق الصغير قرار ذاكرة لا بخل.

### 12.4 بروتوكول القياس — الـEmbedders أولًا

v1.0 تقيس النماذج التوليدية ولا تقيس الـembedders. **هذا مقلوب:** جودة كشف التكرار — وهو
أثقل مشروع في البرنامج — يحكمها الـembedder، والـLLM يرى فقط ما يُرسله إليه الـembedder.
embedder ضعيف يعني أزواجًا صحيحة لا تُرشَّح أبدًا، ولا يستطيع أي LLM إنقاذها.

**قياس الـEmbedders (على 400 زوج مصنّف بشريًا):**

| النموذج | Recall@20 | Precision@T | تخزين | زمن/1K |
|---------|-----------|-------------|-------|--------|
| `embeddinggemma:300m-qat-q4_0` (768) | | | | |
| نفسه، مقتطع إلى 512 (Matryoshka) | | | | |
| `bge-m3` | | | | |
| `multilingual-e5-large` | | | | |

المعيار الحاسم: **Recall@20 على أزواج التكرار العربية.** الترشيح لا يمكن تعويضه لاحقًا.

**قياس النماذج التوليدية:**

| الاختبار | e2b | e4b | 12b |
|----------|-----|-----|-----|
| دقة حكم التكرار (مقابل تصنيف بشري) | | | |
| الالتزام بـJSON Schema (% بلا إعادة) | | | |
| جودة العربية (تقييم مدرب) | | | |
| الالتزام بالمصدر (grounding) | | | |
| زمن الاستجابة الوسيط | | | |
| الذاكرة المقيمة | | | |

مسموح باختيار نموذج مختلف لكل مهمة (مثلًا e2b للتصنيف، e4b للتوليد) — **بعد** القياس لا قبله.

## 13. ميزانيات السعة (Throughput & Capacity)

القسم الأهم الذي كان غائبًا تمامًا عن v1.0. على 16 GB، السعة قيد حقيقي لا تفصيل.

### 13.1 الـEmbeddings

```text
25,000 سؤال × 2 embeddings (stem + full) = 50,000 نداء
embeddinggemma 300m q4 على M1 Pro ≈ 30–80/ثانية
⇒ 10–30 دقيقة.  آمن تمامًا.
```

### 13.2 حُكم الـLLM — القيد الحقيقي

```text
top-K = 20 لكل سؤال على 25K  →  ~250,000 زوج غير موجّه
بعد استثناء التطابق التام وحدّ التشابه  →  تقدير 20,000–60,000 زوج

e2b، مخرَج JSON قصير (~150 token)  ≈  3–6 ثانية/زوج
30,000 زوج × 4 ثوان  =  ~33 ساعة متصلة
```

**الاستنتاج: لا يمكن تمرير كل الـcandidates على LLM.** لذلك الاستراتيجية بالنطاقات:

| النطاق | المعالجة | التكلفة |
|--------|----------|---------|
| تطابق hash تام | عنقود آلي، بلا LLM | صفر |
| `sim ≥ T_high` | عنقود `probable_duplicate` آليًا + تدقيق بشري لـ5% عينة | صفر LLM |
| `T_low < sim < T_high` | **حكم LLM** — هذا هو النطاق الوحيد | الهدف: ≤ 5,000 زوج ≈ 6 ساعات (دفعة ليلية) |
| `sim ≤ T_low` | إسقاط | صفر |

`T_high` و`T_low` **لا تُثبَّت الآن** — تُعايَر على مجموعة التقييم لتحقيق الهدف في §21.
هذه هي «Uncertainty Band» وهي ما يجعل المشروع منتهيًا بدل مفتوح.

**قواعد التشغيل:** الدفعات الثقيلة تعمل ليلًا؛ وضع `--low-memory` يوقف الـworker أثناء القياس.

### 13.3 ميزانية المراجعة البشرية

| المهمة | الحجم | الزمن/الوحدة | المجموع | المراجع |
|--------|-------|--------------|---------|---------|
| مجموعة تقييم التكرار | 400 زوج | 45–90 ث | **5–10 ساعات** | مدرشن + مدرب للحكم النهائي |
| مراجعة أزواج النطاق غير المؤكد | ≤ 1,500 زوج | 30–60 ث | **12–25 ساعة** | مدرشن |
| مجموعة تقييم الجودة | 300 سؤال | 60–120 ث | **5–10 ساعات** | مدرب |
| تصعيد التكرار المتعارض | حسب الاكتشاف | 2–5 د | متغيّر — **أولوية عالية** | مدرب |
| اعتماد النشر (P6) | 1–3/يوم | 2–3 د | **~15 د/يوم** | مدرشن |
| مراجعة drafts (P9) | 100 مهمة | 3–8 د | **5–13 ساعة** | مدرب |

**المجموع التقريبي: 30–60 ساعة عمل بشري موزّعة على البرنامج.** هذا رقم قابل للإدارة إذا
جُدول، وقاتل إذا افتُرض. لذلك: **حصص مراجعة مجدولة، لا مراجعة عند الحاجة.**

**تقليل الحجم بـActive Learning:** بعد أول 150 زوجًا مصنَّفًا، يُعاد تدريب الحدود، ويُراجَع
البشر فقط ما هو قريب من حدّ القرار أو ما اختلف فيه الـLLM مع الـembedder. هذا يقلّل حجم
المراجعة إلى النصف تقريبًا مقابل نفس دقة المعايرة.

### 13.4 pgvector

```text
50,000 vector × 768 بُعد × 4 bytes ≈ 154 MB
```

- حجم صغير: **المسح التام (exact scan) كافٍ ابتداءً — لا index.**
- HNSW (`m=16، ef_construction=64`) يُضاف عند الحاجة لزمن استجابة تفاعلي فقط.
- `vector(768)`، تطبيع إلى وحدة الطول، ومسافة cosine (`vector_cosine_ops`).
- `halfvec(768)` يخفض التخزين للنصف إن لزم.
- إن نجح Matryoshka 512 في القياس → توفير 33% مجانًا.

## 14. حماية البيانات والخصوصية والملكية

### 14.1 النسخة المحلية من Production — قواعد إلزامية

النسخة الموجودة على الـMac تحتوي بيانات شخصية حقيقية:
`users` (إيميلات، جوالات)، `orders` (بيانات بطاقات جزئية)، `certificates.id_number`
(**هوية وطنية**)، `complaints`، `social_providers`.

```text
[ ] تُحفَظ على قرص مشفَّر بـFileVault.
[ ] لا توجد أبدًا داخل مجلد المستودع.
[ ] لا توجد أبدًا في مجلد يُزامن مع iCloud / Dropbox / Google Drive.
[ ] مسارها مضاف إلى global gitignore.
[ ] تُجدَّد بنسخة جديدة بدل تركها تتقادم، مع تسجيل snapshot_taken_at.
[ ] لا تُنسخ إلى أي جهاز آخر ولا إلى VPS.
```

### 14.2 قائمة السماح للـETL

الـLab **لا يستقبل PII أبدًا**. الـETL يعمل بقائمة سماح صريحة، لا باستثناءات.

**مسموح بالكامل:**
```text
categories, courses (metadata فقط), chapters, lectures (العنوان والترتيب فقط),
quizzes, sections, questions, options, quiz_files
```

**مسموح بعد إخفاء الهوية:**
```text
results, question_result
  → user_id يُستبدل بـ student_ref = HMAC-SHA256(pepper, user_id)
  → الـpepper في .env فقط، غير مُودَع في Git، ولا يُخزَّن في قاعدة الـLab
```

هذا يسمح بعدّ سلوك كل طالب (محاولات، تقدّم، تمييز) **دون معرفة هويته** — وهو كل ما تحتاجه
التحليلات. لا يحتاج أي نموذج معرفة اسم طالب لتحليل أداء سؤال.

**ممنوع نقلها إطلاقًا:**
```text
users, orders, course_order, book_order, coupons, certificates, complaints,
complaint_responses, social_providers, personal_access_tokens, paymob_logs,
zoom_users, audits, telescope_*, google_oauth_tokens, failed_jobs, settings
```

### 14.3 سطح الـLab العام (P7) — لا PII بالتصميم

- لا تسجيل دخول، لا إيميل، لا جوال، لا اسم.
- معرّف جلسة أول-طرف عشوائي، بلا ربط بهوية.
- الأحداث تُخزَّن بالمعرّف المجهول فقط.
- لا يُجمع أي شيء لا يُستخدم في مقياس معلن.

### 14.4 حقن الأوامر عبر المستندات (Prompt Injection)

نصوص PDF وOCR **بيانات لا أوامر**. الكتب مصادر شبه موثوقة، لكن القاعدة تُطبَّق دائمًا:

- الـchunks المسترجَعة تُوضع في حقل بيانات محدّد بفواصل واضحة، لا في تعليمات النظام.
- الـsystem prompt يذكر صريحًا أن المحتوى المسترجَع مرجع فقط وأن أي تعليمات داخله تُتجاهل.
- مخرَج الـgenerator يُتحقَّق منه بـSchema، ولا يُنفَّذ منه شيء.

### 14.5 الملكية الفكرية ومصدر الأسئلة

هذه مخاطرة حقيقية يجب تسميتها. بنك من 25,000 سؤال في مجال اختبارات رسمية **قد يحتوي أسئلة
منقولة حرفيًا من مواد رسمية محمية**.

الموقف العملي:

- يُسجَّل `source_origin` لكل سؤال: `authored` / `book_derived` / `unknown` / `suspected_official`.
- الحقل الافتراضي `unknown` — ولا يُدّعى غير ذلك بلا دليل.
- عند توفّر أي مادة رسمية لدى الفريق، يُشغَّل مطابِق حرفي للكشف عن التطابقات ووسمها.
- التوليد في P9 يُسنَد إلى **كتب الدورات المرخّصة**، ولا يُطلب من النموذج إعادة إنتاج
  أسئلة اختبار حقيقية.
- يُوصى بمراجعة قانونية لمصدر البنك الحالي. هذا قرار إدارة لا هندسة، لكن الخطة توفّر البيانات
  التي تُبنى عليها.

### 14.6 النسخ الاحتياطي لقاعدة الـLab

المراجعة البشرية هي أغلى ما في النظام، وهي تسكن في قاعدة الـLab. فقدان 800 زوج مُراجَع = أسابيع.

```text
[ ] pg_dump ليلي إلى قرص مشفَّر محلي.
[ ] نسخة أسبوعية خارج الجهاز.
[ ] تمرين استرجاع واحد على الأقل قبل بدء أي مراجعة بشرية جماعية.
```

---

# Part IV — المشاريع

كل مشروع بنفس القالب: الهدف، الاعتماديات، المدخلات، النطاق، خارج النطاق، جداول الـLab،
المخرجات، تقدير الجهد، حدود القبول/الرفض، المخاطر، معايير الاعتماد.

تقديرات الجهد بـ**أيام عمل مركّز لمطوّر واحد** — لا أيام تقويمية.

---

## 15. P0 — AI Lab Foundation

### الهدف
بيئة محلية موحّدة تسمح ببناء كل ما بعدها دون لمس Production — **بأصغر عدد ممكن من الخدمات**.

### الاعتماديات
لا شيء.

### النطاق

```text
Laravel 12 + Filament        (native)
FastAPI                      (native, uvicorn)
PostgreSQL 17 + pgvector     (Docker، سقف ذاكرة)
MySQL 8                      (Docker، لقراءة نسخة Production فقط)
Ollama                       (native، Metal)
Queue worker                 (database driver — ADR-011)
Logging + health checks
```

### خارج النطاق
**Redis** (ADR-011) — **n8n** (ADR-012) — أي اتصال بـProduction — أي معالجة بيانات حقيقية.

### فحوص الصحة المطلوبة

```text
Laravel  → PostgreSQL
Laravel  → FastAPI
Laravel  → Queue (job ينفَّذ فعلًا)
FastAPI  → PostgreSQL
FastAPI  → Ollama (chat)
FastAPI  → Ollama (embed)
Laravel  → MySQL snapshot (read-only)
pgvector → حفظ واسترجاع vector واحد بنجاح
```

### قواعد التطوير

```text
[ ] كل الأسرار في .env، ولا يُرفع .env إلى Git.
[ ] لا credentials من Production محليًا.
[ ] Ollama على 11434 محليًا فقط، لا يُعرَّض.
[ ] PostgreSQL وMySQL لا يُعرَّضان.
[ ] مستخدم MySQL للنسخة المحلية بصلاحية SELECT فقط.
[ ] PRODUCTION_WRITE_ENABLED=false موجود من اليوم الأول كـkill switch.
```

### المخرجات
stack يعمل — endpoints للصحة — `.env.example` — `docker-compose.yml` — README للتنصيب —
سجلات أساسية — اختبار queue — اختبار Ollama — اختبار embedding مع الـprefix الصحيح.

### تقدير الجهد
**3–5 أيام**

### حدود القبول/الرفض
- إذا لم يستقر Ollama مع النموذجين معًا داخل ميزانية الذاكنة (§12.3) → يُخفَّض `num_ctx`
  أو تُستخدَم دفعات embedding منفصلة عن الـchat.
- إذا تجاوزت الـstack 13 GB في السكون → تُنقل Postgres إلى Homebrew ويُلغى Docker Desktop.

### المخاطر
| المخاطرة | التخفيف |
|----------|---------|
| Docker Desktop يستهلك ذاكرة مفرطة | OrbStack أو Postgres native |
| إغراء إضافة خدمات "لأنها ستُحتاج لاحقًا" | ADR-011/012 مكتوبان لهذا السبب تحديدًا |

### معايير الاعتماد
```text
[ ] الـstack يبدأ بشكل متوقّع بأمر واحد.
[ ] Laravel ينشئ job، والـworker ينفّذه.
[ ] FastAPI ينادي Ollama ويعيد JSON صالحًا.
[ ] embedding بـ768 بُعدًا يُحفَظ في pgvector ويُسترجَع.
[ ] إعادة التشغيل لا تفقد بيانات PostgreSQL.
[ ] نسخة MySQL تُقرأ ولا تقبل الكتابة.
```

---

## 16. P1 — Production Profiling & Question Mirror

### الهدف
**قياس** البنك أولًا، ثم إنشاء مرآة محلية أمينة له.

v1.0 بدأت بـ«استورد 25 ألف سؤال». v2.0 تبدأ بـ«اعرف ماذا لديك فعلًا» — لأن كل تقدير في
هذا المستند مبني على أرقام غير مُقاسة بعد.

### الاعتماديات
P0.

### المدخلات
النسخة المحلية من MySQL. حزمة الاستعلامات في §6.

### النطاق

**المرحلة 1 — الفحص (1–2 يوم):**
تشغيل §6 كاملة، وتوليد تقرير مكتوب فيه: العدد الحقيقي، سلامة الجواب الصحيح، توزيع الخيارات،
نسبة HTML/الصور، حجم البيانات السلوكية، **وحسم غموض التسجيل** (استعلام 15/16).
تحديث §13 بالأرقام الحقيقية قبل المضي.

**المرحلة 2 — الـETL (4–6 أيام):**
MySQL → PostgreSQL، بقائمة السماح في §14.2، مع:
- الحفاظ على معرّفات Production كما هي.
- نسخ الصفوف المحذوفة منطقيًا مع `source_deleted_at`.
- اشتقاق `correct_option_ids` و`option_index` بقواعد §5.1 و§5.2.
- `payload_hash` محدَّد بدقة: SHA256 على تسلسل مُطبَّع (JSON مرتّب المفاتيح) من
  (`name`, `description`, `hint`, والخيارات مرتّبة بـ`option_index` مع `name` و`points`).
- `import_errors` يسجّل كل شذوذ بدل إخفائه.
- idempotent: إعادة التشغيل تُحدِّث المتغيّر ولا تُنشئ تكرارًا.

**المرحلة 3 — لوحة الجرد (2 أيام):**
شاشة Filament تعرض الأعداد والتوزيعات والمشاكل، وتسمح بالانتقال من رقم إلى الأسئلة نفسها.

### خارج النطاق
تطبيع عربي (P2) — كشف تكرار (P2) — أي تصنيف — أي اتصال حيّ بـProduction.

### جداول الـLab

```text
source_snapshots        (snapshot_taken_at, source_row_counts, notes)
source_courses          (+ category tree)
source_categories
source_quizzes          (course_id NULL ⇒ general)
source_sections         (+ stimulus fields — §8)
source_questions        (raw_text, source_deleted_at, payload_hash, source_origin)
source_question_options (option_index, points, is_correct_derived)
source_media            (من quiz_files — النوع والمستوى والمسار)
source_results          (student_ref مُعمَّى)
source_answers          (من question_result — student_ref)
import_runs
import_errors
```

**ملاحظة:** لا يوجد `source_question_exam_links` — لأن العلاقة أب واحد (§2.1).
هذا تصحيح مباشر لـv1.0.

### فحوص التحقق عند الاستيراد

```text
Missing options            Empty question stem
Zero correct options       Multiple correct options
Duplicate option text      order ties
Broken/unbalanced HTML     Stem is only an image
Orphan section/quiz        Category orphan (parent_id مفقود)
Stimulus بلا أسئلة         سؤال بلا section
```

### المخرجات
تقرير الفحص المكتوب — سكربتات ETL قابلة للتكرار — الجداول ممتلئة — لوحة جرد —
سجل أخطاء مرئي — **§13 محدَّثة بأرقام حقيقية**.

### تقدير الجهد
**7–10 أيام**

### حدود القبول/الرفض
- إذا كانت نسبة `correct_count = 0` أعلى من 2% → **يتوقف مسار الـdedup**، ويصبح تصحيح
  الأسئلة المعطوبة أول مخرَج يُسلَّم للفريق. سؤال بلا جواب صحيح يمسّ طالبًا الآن.
- إذا كان `has_description` أقل من 30% → مسار الشرح في P9 يبدأ من الصفر، ويُحذف من التقديرات
  استخدامه كأمثلة few-shot.

### المخاطر
| المخاطرة | التخفيف |
|----------|---------|
| النسخة المحلية قديمة | تسجيل `snapshot_taken_at` وإظهاره في كل تقرير |
| HTML فاسد يُفشل الـparsing | عزل الأخطاء في `import_errors` والمتابعة، لا إيقاف الدفعة |
| تسرُّب PII عبر خطأ في الـETL | قائمة سماح صريحة + اختبار يفشل إذا ظهر عمود ممنوع |

### معايير الاعتماد
```text
[ ] تقرير الفحص مكتوب ومراجَع قبل بناء الـETL.
[ ] كل الأسئلة تُستورد، ومعرّف Production محفوظ في كل صف.
[ ] لا صف واحد يُعدَّل في Production ولا في النسخة المحلية.
[ ] الاستيراد قابل للتكرار وidempotent (payload_hash).
[ ] أخطاء التحقق مرئية وقابلة للتصفية في الواجهة.
[ ] لا PII في قاعدة الـLab — يثبته اختبار آلي.
[ ] كل رقم في اللوحة قابل للنقر للوصول إلى الأسئلة نفسها.
```

---

## 17. P2 — Arabic Normalization & Duplicate Intelligence

### الهدف
كشف التكرار الحقيقي دون حذف أي بيانات، **وضمن ميزانية حسابية منتهية**.

### الاعتماديات
P1.

### الطبقات النصية (محفوظة من v1.0 — صحيحة)

```text
raw_text     ← كما هو في Production، لا يُعدَّل أبدًا
clean_text   ← تنظيف تقني فقط: HTML، مسافات، Unicode. المعنى محفوظ
search_text  ← نسخة المقارنة والبحث
```

`search_text` مسموح فيه: تطبيع Unicode (NFC)، حذف التطويل `ـ`، حذف التشكيل، تطبيع المسافات،
تطبيع علامات الترقيم، توحيد الأرقام العربية/اللاتينية، تطبيع صور الألف المختارة،
وحذف علامات الخيارات عند الحاجة.

**قاعدة عربية إلزامية (محفوظة من v1.0):** لا تُطبَّق تحويلات تغيّر المعنى.
`ة → ه` **ليس** تطبيعًا عامًا مقبولًا.

### الشلال (Cascade) — بميزانية

| الطبقة | الأداة | الكلفة | المخرَج |
|--------|--------|--------|---------|
| 0 | `question_text_hash` = SHA256(search_text) | صفر | تكرار حرفي |
| 1 | `question_with_options_hash` = SHA256(search_text ⊹ الخيارات المطبَّعة المرتَّبة) | صفر | تكرار بفروق تنسيق |
| 2 | Postgres `pg_trgm` + GIN index على `search_text` | منخفضة | مرشّحون لفظيون |
| 3 | pgvector top-K=20 على embeddings | متوسطة | مرشّحون دلاليون |
| 4 | **LLM في النطاق غير المؤكد فقط** | عالية — انظر §13.2 | حكم مُهيكل |

**الـembeddings** (بالـprefix الإلزامي من §12.2):
```text
stem_embedding  ← السؤال وحده
full_embedding  ← السؤال + الخيارات
```
كلاهما بصيغة `task: sentence similarity | query: {…}`، وكل صف يحمل `embedding_config_version`.

**للأسئلة المرتبطة بنص مشترك (§8):** يُضاف مقتطف من النص لا النص كله (حدّ 2K).
سؤالان متطابقان على نصّين مختلفين **ليسا تكرارًا** — قاعدة إلزامية في الحكم.

### مخرَج الحكم (Structured JSON)

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

أنواع العلاقة:
```text
exact_duplicate       formatting_duplicate   semantic_duplicate
same_objective_variant related_not_duplicate  conflicting_duplicate
not_related
```

### مسار التصعيد العاجل — `conflicting_duplicate`

**هذا أهم مخرَج فوري في البرنامج كله، وv1.0 لم تعطه مسارًا.**

سؤالان بنفس النص وجوابان صحيحان مختلفان = **خطأ محتوى يمسّ الطلاب الآن**. أحدهما خاطئ،
ومن أجاب عليه صحيحًا حُسِب عليه خطأ (أو العكس).

```text
conflicting_duplicate مكتشَف
  → طابور أولوية عالية للمدربين (لا ينتظر بقية المراجعة)
  → قرار المدرب: أي الجوابين صحيح
  → تقرير مباشر للفريق للتصحيح في Production admin
  → تتبّع: كم طالبًا تأثّر (من source_answers)
```

يتعزّز هذا كثيرًا بـP3: **معامل تمييز سالب** يشير إلى السؤال نفسه من زاوية أخرى مستقلة.
تقاطع الإشارتين = دليل قوي جدًا على خطأ في المفتاح.

### العناقيد — لا حذف

```text
duplicate_candidates       (pair, sim scores, band, llm verdict)
duplicate_clusters         (canonical_question_id, relation_type, status)
duplicate_cluster_members
duplicate_reviews          (human decision, reviewer, timestamp, notes)
```

**لماذا لا نحذف:** `question_result` يرتبط بـ`questions` بـFK، والأسئلة مرتبطة باختبارات
تاريخية ونتائج وتحليلات. الحذف يُفسد التاريخ.

### شاشة المراجعة (Filament)

```text
سؤال A            |  سؤال B
الخيارات + الجواب  |  الخيارات + الجواب
التشابه: 0.94   |  حكم AI: semantic_duplicate (91%)
إحصاء A: p=0.71 |  إحصاء B: p=0.34    ← من P3 عند توفره
[نفسه] [تنويعة صحيحة] [ليس تكرارًا] [متعارض!] [تخطّي]
```

عرض إحصاء P3 داخل شاشة المراجعة يُسرّع القرار البشري كثيرًا: فرق p-value الكبير بين
"سؤالين متطابقين" يعني أنهما ليسا متطابقين فعلًا في نظر الطلاب.

### خارج النطاق
حذف أي سؤال — تعديل نص Production — التصنيف والـtaxonomy (P5) — التوليد (P9).

### المخرجات
مطبِّع عربي مع اختبارات وحدة — hashes — فهرس trgm — embeddings كاملة — مرشّحون —
عناقيد — شاشة مراجعة — **مجموعة تقييم 400 زوج مصنَّفة بشريًا** — تقرير معايرة الحدود —
طابور التكرار المتعارض.

### تقدير الجهد
**10–15 يومًا** (بالإضافة إلى 17–35 ساعة مراجعة بشرية — §13.3)

### حدود القبول/الرفض
- **البوابة:** على مجموعة الـ400 زوج، يجب تحقيق **precision ≥ 0.90 عند recall ≥ 0.70**
  لفئة (exact + semantic duplicate) **قبل** التشغيل على البنك كامل.
- إذا لم يتحقق ذلك بـEmbeddingGemma → يُقاس `bge-m3` و`multilingual-e5-large` (§12.4)
  **قبل** لمس الـprompts. المشكلة في الترشيح غالبًا لا في الحكم.
- إذا تجاوز عدد أزواج النطاق غير المؤكد 8,000 → تُشدّ الحدود، لأن الميزانية تتجاوز 10 ساعات LLM.

### المخاطر
| المخاطرة | التخفيف |
|----------|---------|
| جودة العربية في الـembedder غير كافية | القياس قبل الالتزام؛ بدائل محدَّدة مسبقًا |
| انفجار عدد الأزواج | نطاقات صريحة + سقف معلن + `log` لما استُبعِد |
| اختلاف المراجعين البشريين | قياس الاتفاق بينهم؛ الخلاف يُحال إلى مدرب |
| تغيير الـprefix يُبطل الـvectors صامتًا | `embedding_config_version` إلزامي |
| false positives من أسئلة النصوص المشتركة | قاعدة "نص مختلف ⇒ ليس تكرارًا" في الحكم والـblocking |

### معايير الاعتماد
```text
[ ] التكرار الحرفي يُكتشف deterministically وبلا LLM.
[ ] embeddings لكل سؤال صالح، بالـprefix الصحيح ومع config version.
[ ] بحث الجيران الأقرب في pgvector يعمل.
[ ] الـLLM لا يرى إلا أزواج النطاق غير المؤكد — ويُثبت ذلك بعدّاد.
[ ] الإنسان يستطيع تجاوز حكم الـAI، والقرار محفوظ مع صاحبه.
[ ] لا سؤال مصدر يُحذف.
[ ] مقاييس التقييم (precision/recall/الاتفاق البشري) مسجَّلة ومنشورة.
[ ] كل تكرار متعارض في طابور أولوية عالية مع عدد الطلاب المتأثرين.
```

---

## 18. P3 — Item Statistics from Existing Results

### الهدف
استخراج جودة الأسئلة من **سلوك الطلاب الحقيقي الموجود بالفعل** — بـSQL فقط، بلا AI،
بلا أحداث جديدة، وبلا أي تعديل على Production.

### لماذا هذا المشروع مبكر
v1.0 دفنت هذا في Project 8 خلف ستة مشاريع. لكنه:

- **متاح اليوم بالكامل** — البيانات موجودة في `results` و`question_result`.
- **لا يحتاج AI ولا embeddings ولا PDF ولا taxonomy.**
- **يجد أخطاء المفاتيح فورًا** — معامل تمييز سالب = المتفوقون يخطئون فيه = المفتاح غالبًا خاطئ.
- **أعلى قيمة لكل مجهود في البرنامج كله.**

### الاعتماديات
P1 (المرحلة 2 — `source_results` و`source_answers`).

### المقاييس والصيَغ

**معامل الصعوبة (p-value):**
```text
p = (عدد الإجابات على السؤال بـ points > 0) / (إجمالي الإجابات على السؤال)
```

**توزيع المشوّشات:**
```text
لكل option_id: نسبة اختياره من إجمالي الإجابات على السؤال
```

**معامل التمييز (point-biserial):**
```text
r_pbis = ((M₁ − M₀) / SD_total) × √(p × q)

M₁ = متوسط الدرجة الكلية لمن أجاب صحيحًا
M₀ = متوسط الدرجة الكلية لمن أجاب خطأً
p  = نسبة الإجابة الصحيحة،  q = 1 − p
SD_total = الانحراف المعياري للدرجات الكلية
```

**تصحيح مهم:** تُستخدَم الدرجة الكلية **مطروحًا منها درجة السؤال نفسه** (corrected total)،
وإلا تضخّم المعامل ذاتيًا. هذه تفصيلة تُنسى كثيرًا وتُفسد الترتيب.

**حدود دنيا للعدد:**
```text
n < 10   → لا يُنشَر أي مقياس
n < 30   → p-value فقط، بلا r_pbis
n ≥ 30   → المقاييس كاملة
```

### قواعد الوسم (Flags)

| القاعدة | الدلالة | الأولوية |
|---------|---------|----------|
| `r_pbis < 0` | **المتفوقون يخطئون فيه ⇒ المفتاح خاطئ غالبًا** | **عاجل** |
| مشوّش يُختار أكثر من المفتاح | مفتاح مشكوك فيه | **عاجل** |
| `p < 0.20` | صعب جدًا أو خاطئ | عالية |
| `p > 0.95` | سهل جدًا / لا يميّز | متوسطة |
| `0 ≤ r_pbis < 0.10` | لا يميّز بين المستويات | متوسطة |
| مشوّش يُختار بأقل من 2% | مشوّش ميت — يُعاد كتابته | منخفضة |
| تباعد كبير في p داخل عنقود تكرار | العنقود ليس تكرارًا فعليًا | متوسطة |

### التقاطع مع P2 — أقوى إشارة في البرنامج

```text
سؤال له r_pbis سالب       (P3 — دليل سلوكي)
        ∩
عضو في عنقود conflicting_duplicate  (P2 — دليل نصي)
        ⇓
احتمال عالٍ جدًا لخطأ في المفتاح — أولوية قصوى للمدرب
```

إشارتان مستقلتان تمامًا تتفقان على نفس السؤال. هذا أقوى بكثير من أي منهما وحده.

### دور الـAI
**لا شيء في الحساب.** لاحقًا (بعد P4) يمكن للـLLM أن **يصوغ** التقرير للمدرب،
مستندًا إلى الأرقام المحسوبة، دون أن يحسب أو يخترع رقمًا (ADR-009).

### خارج النطاق
زمن الإجابة، الترك، سؤال الانسحاب، تغيير الإجابة — **كلها غير متاحة** (§7.2).
أي تعديل على Production. أي حكم على طالب.

### جداول الـLab
```text
item_statistics       (question_id, n, p_value, r_pbis, computed_at, snapshot_id)
option_statistics     (option_id, selection_rate, n)
quiz_statistics       (quiz_id, starts_proxy, mean, median, sd, n)
item_flags            (question_id, flag_type, severity, evidence_json)
```

### المخرجات
حزمة SQL/Python للحسابات — لوحة Filament (ترتيب حسب الوسم والأولوية) — تقرير
"أسئلة تحتاج مراجعة عاجلة" — تصدير للمدربين — تقرير تغطية يوضح **كم من البنك لديه بيانات كافية**.

### تقدير الجهد
**4–6 أيام**

### حدود القبول/الرفض
- إذا كان أقل من 20% من البنك لديه `n ≥ 30` → المشروع يبقى ذا قيمة لكن نطاقه يُعلَن صريحًا،
  ولا يُقدَّم كتقييم للبنك كله.
- كل رقم يجب أن يكون قابلًا لإعادة الإنتاج من الصفوف الخام؛ اختبار يتحقق من ذلك على عيّنة.

### المخاطر
| المخاطرة | التخفيف |
|----------|---------|
| عدد إجابات صغير يُنتج أرقامًا مضلّلة | حدود دنيا صريحة + إظهار `n` بجانب كل رقم دائمًا |
| خلط المحاولات المتكررة | تمييز المحاولة الأولى بترتيب `created_at` وتقرير الاثنين |
| اختبارات مختلفة لها صعوبة مختلفة | `r_pbis` داخل الاختبار الواحد فقط، لا عبر اختبارات |
| قراءة الأرقام كحكم على مدرب | التقارير على مستوى السؤال، ولا تُرتَّب حسب المدرب |

### معايير الاعتماد
```text
[ ] كل مقياس قابل لإعادة الإنتاج من الصفوف الخام.
[ ] الحدود الدنيا للعدد مطبَّقة، وn معروض مع كل رقم.
[ ] الأسئلة ذات التمييز السالب مرتَّبة في تقرير عاجل.
[ ] نسبة تغطية البنك بالبيانات معلَنة صريحًا.
[ ] المدرب يستطيع الانتقال من الرقم إلى السؤال ومراجعته.
[ ] لا يوجد أي مقياس محسوب بواسطة LLM.
```

---

## 19. P4 — Question Quality Audit

### الهدف
جودة الأسئلة **بمعزل عن الـtaxonomy** — لأن الجودة لا تحتاج تصنيفًا، والتغطية تحتاجه.
(هذا فصل لما كان مدموجًا في Project 4 من v1.0.)

### الاعتماديات
P1 و P2. يستفيد كثيرًا من P3.

### الطبقة الأولى — فحوص deterministic (بلا LLM)

```text
السؤال غير فارغ                جواب صحيح موجود
الجواب ينتمي للخيارات          لا خيارات مكرَّرة نصيًا
عدد الخيارات متوافق            HTML سليم
العلاقات صحيحة                 لا تسريب للجواب في نص السؤال (فحص نصي)
طول السؤال والخيارات منطقي     لا خيارات "كل ما سبق" مع مفتاح متعدد
```

### الطبقة الثانية — مراجعة AI

Gemma 4 يفحص ما لا يُفحص برمجيًا: الغموض، احتمال تعدد الصحيح، ضعف المشوّشات،
تسريب الجواب، ركاكة العربية، نقص السياق، تعارض الشرح مع الجواب.

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

**الـAI لا يعتمد سؤالًا نهائيًا أبدًا** (ADR-005).

### الطبقة الثالثة — الدليل السلوكي من P3
وسوم P3 تُدمَج كمُدخَل مستقل. حين يتفق الـAI والإحصاء على نفس السؤال، ترتفع الأولوية.
حين يختلفان، يُعرَض الخلاف للمراجع البشري — وهذا في ذاته إشارة مفيدة.

### الترتيب بالأولوية
لا تُراجَع 25,000 سؤال. تُرتَّب:
```text
1. متعارض في المفتاح (P2 ∩ P3)
2. بلا جواب صحيح
3. تمييز سالب
4. AI يرى تعدد صحيح أو غموضًا + بيانات سلوكية سيّئة
5. مشوّشات ميتة
6. الباقي — عيّنة فقط
```

### خارج النطاق
التصنيف والـtaxonomy والتغطية (P5) — تعديل نص Production — التوليد (P9).

### جداول الـLab
```text
quality_checks       (question_id, check_type, passed, details_json)
ai_quality_reviews   (question_id, prompt_version, model, output_json, latency_ms)
quality_decisions    (question_id, reviewer_id, decision, notes, created_at)
```

### المخرجات
محرّك الفحوص الحتمية — prompt مراجعة الجودة (مُصدَّر ومُرقَّم) — **مجموعة تقييم 300 سؤال
بتصنيف بشري** — تقرير اتفاق AI/بشر — طابور مراجعة مرتَّب بالأولوية — لوحة الجودة.

### تقدير الجهد
**6–9 أيام** (+ 5–10 ساعات مراجعة مدرب)

### حدود القبول/الرفض
- **البوابة:** اتفاق ≥ 0.80 مع التصنيف البشري على حقلَي `single_correct_answer` و
  `ambiguity_detected` قبل عرض وسوم الـAI للمدربين كتوصيات.
- إذا لم يتحقق → تُعرَض الفحوص الحتمية ووسوم P3 فقط، ويُخفى مخرَج الـAI حتى تحسّن الـprompt
  أو النموذج. **عرض وسوم غير موثوقة على مدرب يُفقد الثقة في النظام كله**، وهي خسارة أغلى
  من فائدة الوسم.

### المخاطر
| المخاطرة | التخفيف |
|----------|---------|
| ثقة زائدة في وسوم الـAI | البوابة أعلاه + وسم كل مخرَج AI بأنه "توصية" |
| إغراق المدربين | ترتيب صارم بالأولوية + سقف يومي |
| ركاكة عربية يحكم عليها نموذج | التقييم البشري هو الحكم (v1.0 §20 — محفوظ) |

### معايير الاعتماد
```text
[ ] الفحوص الحتمية تعمل أولًا وتُنتج نتائج قبل أي نداء AI.
[ ] مخرَج الـAI يلتزم بـJSON Schema ويُتحقَّق منه قبل القبول.
[ ] تجاوزات البشر محفوظة مع صاحبها وسببها.
[ ] بوابة الاتفاق 0.80 مُقاسة ومسجَّلة.
[ ] الطابور مرتَّب بالأولوية لا بالـid.
[ ] الصعوبة المتوقَّعة من الـAI موسومة "predicted" وتُميَّز عن p-value المقاس من P3.
```

---

## 20. P5 — Taxonomy & Coverage Map

### الهدف
تصنيف مُعتمَد للبنك، وخريطة فجوات (Gap Map) تُوجّه التوليد لاحقًا.

### لماذا مشروع مستقل
**لا يوجد أي taxonomy في Production** — لا موضوع، لا مهارة، لا هدف تعليمي، لا مستوى معرفي،
لا صعوبة. v1.0 جعلت ثلاثة مشاريع تعتمد على taxonomy لم تُسنِد إليها مالكًا ولا موعدًا.

**القاعدة (محفوظة من v1.0 وصحيحة):** الفريق يعتمد الـtaxonomy أولًا، **ثم** يصنّف الـAI
داخل قائمة مغلقة. لا يُسمح للنموذج باختراع فروع.

### الاعتماديات
P1، P2 (للتصنيف على مستوى العنقود لا السؤال المفرد — أوفر كثيرًا).
**واعتماد بشري خارجي** — وهذا هو المسار الحرج.

### المرحلة 1 — التأليف البشري (لا برمجة)

مخرَج بشري، يؤلّفه أهل التخصص، مُرقَّم الإصدار:

```text
Specialization → Topic → Subtopic → Skill → Learning Objective
Cognitive Level   (مثال: تذكّر / فهم / تطبيق / تحليل)
Difficulty Bands  (سهل / متوسط / صعب — معرَّفة بحدود p-value من P3، لا بالحدس)
```

**نقطة مهمة:** بعد P3 صارت لدينا صعوبة **مقاسة** فعلًا. لذلك تُعرَّف نطاقات الصعوبة
بحدود p-value حقيقية بدل تقدير بشري — وهذه ميزة مباشرة لتقديم P3.

`categories` في Production تُستخدم كجذر التخصصات (مع الحذر من `parent_id` — §9).

### المرحلة 1ب — مواصفة Qiyas / STEP / IELTS

v1.0 تستخدم "Qiyas-style" في كل مكان بلا تعريف. المطلوب مواصفة مكتوبة لكل عائلة اختبار:

```text
عدد الأسئلة        الأقسام وتوزيعها
التوقيت            وزن كل موضوع
مزيج الصعوبة       صيغ الأسئلة المستخدمة
طريقة التقدير      هل توجد نصوص مشتركة؟
```

يؤلّفها من يعرف الاختبار الحقيقي، وتُخزَّن مُرقَّمة الإصدار.
**بدون هذه المواصفة، "Qiyas-style" كلمة تسويقية لا مواصفة هندسية.**

### المرحلة 2 — التصنيف بالـAI
تصنيف داخل القائمة المغلقة، **على مستوى العنقود** (canonical) لا كل سؤال — يوفّر
نسبة كبيرة من نداءات الـLLM.

### المرحلة 3 — خريطة التغطية

```text
الموضوع: استراتيجيات التدريس

أسئلة معتمدة: 850

تذكّر:  430        تكرار حرفي:     110
فهم:    260        تكرار دلالي:      70
تطبيق:  140        تحتاج مراجعة:     95
تحليل:   20        لها مصدر معتمد:  320
                   بلا مصدر:        530

صعوبة مقاسة (من P3):  سهل 40% / متوسط 45% / صعب 15%
الفجوة: نقص في التطبيق والتحليل، ونقص في الصعب
```

### المخرجات
`taxonomy` مُرقَّمة الإصدار — مواصفات الاختبارات — تصنيف البنك — خريطة الفجوات —
لوحة التغطية — تعريف نطاقات الصعوبة بحدود p-value.

### تقدير الجهد
**4–6 أيام برمجة** + **2–4 أسابيع زمن منقضٍ** لانتظار التأليف البشري (المسار الحرج).

**توصية جدولة:** يبدأ طلب تأليف الـtaxonomy **في وقت P2**، لا عند P5. وإلا سيتوقف
البرنامج انتظارًا لقرار بشري كان يمكن أن ينضج بالتوازي. هذه أهم ملاحظة جدولة في المستند.

### حدود القبول/الرفض
- بلا taxonomy معتمدة بشريًا → **P5 لا يبدأ**، ولا يُسمح للـAI باختراع تصنيف.
  P4 وP3 وP6 تكمل بدونها.
- إذا تعذّر الحصول على مواصفة اختبار → تُبنى مواصفة أولية من الاختبارات الفعلية الموجودة
  في Production (استعلام 8 في §6.1)، وتُوسم "مستنبطة، غير معتمدة".

### المخاطر
| المخاطرة | التخفيف |
|----------|---------|
| الـtaxonomy لا تُؤلَّف أبدًا | تبدأ مبكرًا؛ يُقبل إصدار v0.1 ناقص بدل انتظار الكمال |
| الـAI يخرج عن القائمة | تحقّق برمجي: أي تصنيف خارج القائمة يُرفض آليًا |
| تصنيف واحد يُفرض على سؤال متعدد الجوانب | السماح بأكثر من موضوع، بواحد أساسي |

### معايير الاعتماد
```text
[ ] الـtaxonomy معتمدة بشريًا ومُرقَّمة الإصدار قبل أي تصنيف AI.
[ ] كل تصنيف AI داخل القائمة المغلقة — يُثبته تحقّق برمجي.
[ ] نطاقات الصعوبة معرَّفة بـp-value مقاس من P3، لا بالحدس.
[ ] الفجوات قابلة للتحديد ولتصديرها كمُدخَل لـP9.
[ ] مواصفة اختبار واحدة على الأقل مكتوبة ومعتمدة.
```

<!-- SENTINEL:PART4B -->


