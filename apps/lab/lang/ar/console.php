<?php

/**
 * FR-047: reviewer-facing text is Arabic; technical identifiers
 * (`payload_hash`, `answer_key_state`'s raw values, the thirteen import
 * error codes) stay English and are never translated here.
 */
return [

    'nav' => [
        'bank_group' => 'بنك الأسئلة',
        'inventory' => 'نظرة عامة',
        'questions' => 'الأسئلة',
        'sections' => 'الأقسام',
        'quizzes' => 'الاختبارات',
        'courses' => 'الدورات',
        'import_errors' => 'أخطاء الاستيراد',
    ],

    'snapshot_header' => [
        'snapshot_taken_at' => 'تاريخ اللقطة',
        'mirrored_questions' => 'الأسئلة في النسخة المحلية',
        'last_import_run' => 'آخر عملية استيراد',
        'none_yet' => 'لا يوجد بعد',
    ],

    'suppression' => [
        'partial' => '١٠–٢٩ (مموّه)',
        'hidden' => '—',
    ],

    'answer_key_state' => [
        'pending' => 'قيد التحديد',
        'single_correct' => 'إجابة واحدة صحيحة',
        'broken_no_key' => 'بلا إجابة صحيحة',
        'multi_key' => 'أكثر من إجابة صحيحة',
    ],

    'inventory' => [
        'title' => 'نظرة عامة على البنك',
        'quality_cards' => 'جودة البنك',
        'active' => 'أسئلة نشطة',
        'soft_deleted' => 'أسئلة محذوفة (حذف ناعم)',
        'no_explanation' => 'بلا شرح',
        'has_html' => 'تحتوي HTML',
        'has_img' => 'تحتوي صورة',
        'media_review' => 'بحاجة لمراجعة الوسائط',
        'shared_text_sections' => 'أقسام بنص مشترك',
        'answer_key_integrity' => 'سلامة مفتاح الإجابة',
        'option_count_distribution' => 'توزيع عدد الخيارات',
        'by_category' => 'حسب الفئة',
        'by_course' => 'حسب الدورة',
        'by_quiz' => 'حسب الاختبار',
        'view_all' => 'عرض الكل (:count)',
        'view_import_errors' => 'الانتقال إلى شاشة أخطاء الاستيراد',
        'limits_title' => 'ما لا يمكن لهذه الشاشة تأكيده',
        'limits_media' => 'مسارات الوسائط غير مُتحقَّق منها — لا يُضمن أن الملف المشار إليه موجود فعلاً.',
        'limits_answer_gap' => 'صف الإجابة الغائب لا يميّز بين "لم تتم الإجابة" و"لم يُعرض السؤال على الطالب أصلاً".',
    ],

    'question' => [
        'singular' => 'سؤال',
        'source_id' => 'المعرّف',
        'stem' => 'نص السؤال',
        'explanation' => 'الشرح',
        'hint' => 'تلميح',
        'options' => 'الخيارات',
        'correct' => 'صحيحة؟',
        'correct_option_count' => 'عدد الإجابات الصحيحة',
        'answer_key_state' => 'حالة مفتاح الإجابة',
        'options_count' => 'عدد الخيارات',
        'has_html' => 'يحتوي HTML',
        'has_img' => 'يحتوي صورة',
        'media_review' => 'يحتاج مراجعة وسائط',
        'no_explanation' => 'بلا شرح',
        'deleted' => 'محذوف',
        'category' => 'الفئة',
        'course' => 'الدورة',
        'no_course' => '(بدون دورة)',
    ],

    'section' => [
        'singular' => 'قسم',
        'source_id' => 'المعرّف',
        'name' => 'الاسم',
        'stimulus' => 'النص المشترك',
        'stimulus_length' => 'طول النص المشترك',
        'has_stimulus' => 'نص مشترك؟',
        'is_long_stimulus' => 'نص طويل؟',
        'questions_count' => 'عدد الأسئلة',
        'view_questions' => 'عرض أسئلة هذا القسم',
    ],

    'quiz' => [
        'singular' => 'اختبار',
        'source_id' => 'المعرّف',
        'title' => 'الاختبار',
        'name' => 'الاسم',
        'duration' => 'المدة',
        'sections_count' => 'عدد الأقسام',
        'view_sections' => 'عرض أقسام هذا الاختبار',
    ],

    'course' => [
        'singular' => 'دورة',
        'source_id' => 'المعرّف',
        'title' => 'الدورة',
        'name' => 'الاسم',
        'status' => 'مفعّلة؟',
        'start_date' => 'تاريخ البدء',
        'exam_date' => 'تاريخ الامتحان',
        'quizzes_count' => 'عدد الاختبارات',
        'view_quizzes' => 'عرض اختبارات هذه الدورة',
    ],

    'import_errors' => [
        'singular' => 'خطأ استيراد',
        'code' => 'الرمز',
        'severity' => 'الخطورة',
        'severity_error' => 'خطأ',
        'severity_warning' => 'تحذير',
        'source_table' => 'الجدول',
        'source_id' => 'المعرّف',
        'message' => 'الرسالة',
        'context' => 'السياق',
        'run' => 'عملية الاستيراد',
        'created_at' => 'وقت التسجيل',
        'all_runs' => 'كل عمليات الاستيراد',
        'showing_run' => 'يعرض العملية رقم :id (:kind) — بدأت في :started_at',
        'view_question' => 'عرض السؤال',
    ],

];
