<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Source COPY allowlist — governs COPYING INTO the Lab, not reading
    |--------------------------------------------------------------------------
    |
    | The ten tables of the InjazEdu MySQL source the Lab may COPY INTO its
    | own database. This list is the copy check: P1's ETL must call
    | App\Support\SourceReader::assertCopyable() before writing any row.
    |
    | It does NOT govern reading — reading is source_tables ∪ profile_tables.
    | Never merge the two lists into one union check: reading a count is not
    | storing a row, and the split between them is the safety property
    | (P0 §3.2, ADR-021 revised 2026-08-23 and 2026-08-26, FR-001).
    |
    | `results` carries user_id — copyable by design, never storable as-is:
    | the ETL converts it to student_ref on the way in.
    |
    | `question_result` LEFT this list on 2026-08-26 (ADR-022). The answer-
    | event table is unbounded behavioural data and is never mirrored; it is
    | read as aggregates into source_item_stats and source_option_stats.
    | Its place on profile_tables is what makes "no raw answer rows are
    | stored" a structural guarantee rather than a convention.
    |
    */

    'source_tables' => [
        'categories',
        'courses',
        'chapters',
        'lectures',
        'quizzes',
        'sections',
        'questions',
        'options',
        'quiz_files',
        'results',
    ],

    /*
    |--------------------------------------------------------------------------
    | Profile allowlist — governs READING AS COUNTS, never copying
    |--------------------------------------------------------------------------
    |
    | Seven InjazEdu tables that may be READ (as counts/aggregates) but may
    | NEVER be copied into the Lab database. Six were added 2026-08-23
    | (P0 §3.2) so §6 queries 15, 16 and 18 could run in P1.
    |
    | `question_result` joined them on 2026-08-26 (ADR-022): 13.8M answer
    | events, read only as GROUP BY results. Everything the program needs
    | from it — p_value, the point-biserial inputs, the distractor
    | distribution — is an aggregate bounded by the QUESTION count, not the
    | answer count, which is what lets this Lab point at a far larger
    | platform. Its rows stay in the source.
    |
    | This list is NOT a copy check: assertCopyable() accepts source_tables
    | alone. A table on this list is read-only in the strongest sense — its
    | rows stay in the source, always (P0 §3.2, ADR-021 revised, FR-001).
    |
    */

    'profile_tables' => [
        'course_user',
        'course_order',
        'orders',
        'user_roles',
        'roles',
        'book_course',
        'question_result',
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshot provenance (FR-006)
    |--------------------------------------------------------------------------
    |
    | The date the local copy of the InjazEdu database was taken. Carried in
    | apps/lab/.env as SNAPSHOT_TAKEN_AT.
    |
    */

    'snapshot_taken_at' => env('SNAPSHOT_TAKEN_AT'),

    /*
    |--------------------------------------------------------------------------
    | Destructive-command guard (2026-08-27)
    |--------------------------------------------------------------------------
    |
    | `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, and any
    | raw DROP DATABASE / DROP SCHEMA ... CASCADE statement are refused
    | (App\Exceptions\DestructiveOperationBlocked, AppServiceProvider::boot())
    | unless the resolved database name is in this list. Only the disposable
    | injazedu_lab_test belongs here — never injazedu_lab. This is what a
    | destructive command run without --env=testing hits instead of the
    | developer's real, manually-imported data.
    |
    */

    'safe_destructive_databases' => array_filter(explode(
        ',',
        env('LAB_SAFE_DESTRUCTIVE_DATABASES', 'injazedu_lab_test')
    )),

    /*
    |--------------------------------------------------------------------------
    | Import (FR-022)
    |--------------------------------------------------------------------------
    |
    | Shared configuration for `php artisan lab:import`. `chunk_size` is the
    | default `--chunk` batch size for the ~13.8M-row behavioural tables,
    | tuned by measurement, not assumed correct. `source_system` is the
    | constant every mirror table's `source_system` column carries — one
    | value, because this Lab has exactly one source.
    |
    */

    'import' => [
        'chunk_size' => 10000,
        'source_system' => 'injazedu_production',

        /*
        | The multi-key decision (FR-061), recorded 2026-08-26 by the
        | operator from queries 3 and 4: 34 questions carry more than one
        | correct option (33 at 2, 1 at 4 — 0.118% of active questions), and
        | they are **data-entry errors, not a supported question type**. A
        | valid question has exactly one correct option;
        | `answer_key_state = multi_key` is a review flag, never an
        | answerable item. Nothing is repaired or deleted in P1.
        |
        | Recorded in §13 of the program plan beside the measurement it came
        | from. App\Jobs\Import\BackfillAnswerKeyState refuses to run while
        | this is null — that refusal is what stops a question leaving
        | `pending` on a guess (SC-020).
        */
        'multi_key_policy' => 'data_entry_error',
    ],

    /*
    |--------------------------------------------------------------------------
    | Student pseudonymization (FR-019, FR-037)
    |--------------------------------------------------------------------------
    |
    | HMAC-SHA256(pepper, user_id) is the only identity a behavioural row
    | carries as `student_ref`. Confirmed stored in apps/lab/.env, outside
    | Git and off this machine (P1 plan §8 item B): once ~1.1 M student_ref
    | values exist, changing this orphans every one of them and there is no
    | backup. App\Support\Derive\StudentRefHasher throws rather than hash
    | against an empty or missing value.
    |
    */

    'student_ref_pepper' => env('STUDENT_REF_PEPPER'),

    /*
    |--------------------------------------------------------------------------
    | Profiling (FR-004)
    |--------------------------------------------------------------------------
    |
    | Where `php artisan lab:profile` finds the eighteen §6 query files and
    | where it generates the human-readable report. The report is generated
    | from `source_snapshots.profiling_results` alone (FR-005) — this path is
    | an output location, never a second source of truth.
    |
    */

    'profiling' => [
        'sql_path' => base_path('../../sql/profiling'),
        'report_path' => base_path('../../docs/reports/p1-profiling.md'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding contract (FR-004, data-model.md §3)
    |--------------------------------------------------------------------------
    |
    | One opaque string, carried by every vector from P2 onward. Changing any
    | component — model tag, prefix template, dimension, or normalization —
    | silently invalidates every stored vector (§12.2). This is configuration,
    | not housekeeping.
    |
    | The prefix has exactly one owner: the service applies it, callers send
    | raw text. A caller that pre-applies it produces wrong vectors with no
    | error.
    |
    */

    'embedding' => [
        'config_version' => env('EMBEDDING_CONFIG_VERSION'),
        'prefix_template' => 'task: sentence similarity | query: {text}',
        'dimension' => 768,
        'models' => [
            'chat' => 'gemma4:e2b-it-qat',
            'embedding' => 'embeddinggemma:300m-qat-q4_0',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI service (contracts/ai-service.md)
    |--------------------------------------------------------------------------
    |
    | The stateless FastAPI service on loopback — the Lab's only door to the
    | model runtime. The timeout covers the measured cold service runtime
    | probe (6.378 s chat + 1.645 s embed; notes N7) with headroom.
    |
    */

    'ai_service' => [
        'base_url' => env('AI_SERVICE_URL'),
        'timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Console locales (FR-047)
    |--------------------------------------------------------------------------
    |
    | Arabic is the default (config('app.locale')) and stays first — the
    | console is Arabic-first, not Arabic-only. The switch stores the
    | viewer's choice in the session; App\Http\Middleware\SetLocale reads
    | it and refuses anything not in this list. Technical identifiers never
    | move — see the per-key note in lang/{ar,en}/console.php.
    |
    */

    'locales' => [
        'ar' => 'العربية',
        'en' => 'English',
    ],

    /*
    |--------------------------------------------------------------------------
    | Duplicate intelligence (spec 006-p2-duplicate-intelligence)
    |--------------------------------------------------------------------------
    |
    | `lab:dedup`'s tuning surface: the candidate-generation floors, the
    | normalization rules (FR-139 – FR-141), and the calibration/triage
    | constants (FR-144 – FR-150). Every threshold here is either measured
    | (notes.md N1/N2/N10) or explicitly a starting value tuned later by a
    | named task — see the per-key notes.
    |
    */

    'dedup' => [

        /*
        | Candidate generation (FR-043 – FR-049).
        */
        'trgm_floor' => 0.55,
        'top_k' => 20,
        'chunk_size' => 5000,

        /*
        | Pair-derived closure size guard (FR-119, plan.md decision 4/5).
        | Applies ONLY to closures built from duplicate_candidates pairs
        | (Phase 9's AutoClusterHighBand) — NEVER to hash clusters, where a
        | legitimate 538-member group exists (median 3, p99 15). This is a
        | starting value; T092 logs the real pair-derived component-size
        | distribution before it is tuned.
        */
        'closure_size_guard' => 50,

        /*
        | The standing conflict-backlog queue (FR-151, FR-152). Soft — the
        | console never blocks on it, it only says what remains per tier.
        */
        'daily_review_cap' => 10,

        /*
        | Verdict retry/rationing (FR-122 – FR-124, FR-080).
        */
        'verdict_max_attempts' => 3,

        /*
        | Projected uncertain-band ceiling (FR-062, FR-063). If the
        | calibrated thresholds would put more than this many pairs in
        | `band = 'uncertain'` across the full candidate pool, T_low is
        | raised and recalibrated — logged, never silent.
        */
        'uncertain_band_ceiling' => 8000,

        /*
        | Target size of the labelled evaluation set once calibration
        | settles (informational — the real ceiling is eval_wave_sizes'
        | cumulative sum, see calibration.eval_cumulative_ceiling below).
        */
        'verdict_target_pairs' => 5000,

        /*
        | Generated reports (FR-058, FR-066, FR-091, FR-092) — pure
        | functions over stored rows, regenerated identically, never
        | hand-edited.
        */
        'report_paths' => [
            'eval_set' => base_path('../../docs/reports/p2-eval-set.md'),
            'calibration' => base_path('../../docs/reports/p2-calibration.md'),
            'conflicting_duplicates' => base_path('../../docs/reports/p2-conflicting-duplicates.md'),
        ],

        /*
        |----------------------------------------------------------------------
        | Normalization (FR-139 – FR-141, FR-155) — CLOSED at human gate H
        | (T003), 2026-08-29, against a 35-row sample of the real mirror
        |----------------------------------------------------------------------
        |
        | Option-label alphabets: BOTH scripts are required, not optional.
        | 3,604 active options begin with a Latin label against 1,200 with
        | an Arabic one, and 9.9% of the bank has no Arabic character at all
        | (notes.md N10) — an Arabic-only stripper would miss the majority
        | case. Stripping is anchored to the leading marker only
        | (`^\s* LABEL \s* DELIM \s+`) and never touches a letter inside the
        | text (FR-139).
        |
        | ArabicNormalizer::search() MUST run option-label stripping BEFORE
        | Alef-form normalization (FR-011, amended 2026-08-29) — this list's
        | four Hamza-Alef forms (أ إ آ ا) must still be distinct when the
        | stripper runs, or a labelled أ/إ/آ option is silently missed once
        | Alef normalization has already folded it to bare ا. Alef-form
        | normalization itself is scoped to أ/إ/آ → ا ONLY — ى is never
        | folded, in this layer or the fuzzy one below.
        |
        */
        'option_label_alphabets' => [
            'ar' => ['أ', 'إ', 'آ', 'ا', 'ب', 'ج', 'د', 'هـ', 'ه'],
            'la' => ['A', 'B', 'C', 'D', 'E', 'a', 'b', 'c', 'd', 'e'],
            'digit' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '١', '٢', '٣', '٤', '٥'],
        ],

        'option_label_delimiters' => ['.', ')', '-', ':', '،', ','],

        /*
        | The fourth, explicitly-named recall-only form (FR-141). This fold
        | MUST NEVER reach clean_text, search_text, question_text_hash,
        | question_with_options_hash, media_fingerprint, any cluster key or
        | any identity decision — it may only widen candidate recall.
        | Constitution IV v2.5.0 admits exactly this and no more. Measured
        | yield at the stem grain: ~12 additional stems collapse (notes.md
        | N10) — small and cheap, which is why `ى/ي` is deliberately not
        | shipped alongside it.
        |
        | CLOSED at gate H (2026-08-29): no further entries without a
        | measured yield on real data AND a dedicated isolation test
        | (FR-141, FR-143) — this is not a place to add "obvious" typo
        | tolerances on judgement alone.
        */
        'fuzzy_fold_enabled' => true,
        'fuzzy_fold_map' => [
            'ة' => 'ه',
        ],

        /*
        |----------------------------------------------------------------------
        | Progressive calibration (FR-144 – FR-146) and triage (FR-150)
        |----------------------------------------------------------------------
        */
        'eval_wave_sizes' => [100, 100, 100, 100],
        'eval_ci_confidence' => 0.95,
        'eval_positive_class_floor' => 30,
        'eval_cumulative_ceiling' => 400,

        /*
        | Off by default (FR-147 – FR-149). When enabled, the /verdict
        | endpoint is called once per eval pair and the result is stored
        | ONLY in duplicate_eval_pairs.ai_* — never human_relation, never a
        | candidate row, never the positive class.
        */
        'ai_prelabel_enabled' => false,

        /*
        | Conflict-backlog priority tiers (FR-150). Computed by SQL from the
        | measured affected_student_count distribution at these percentiles
        | — the cut values themselves are logged with each run, not fixed
        | here. Measured 2026-08-28: p50=141, p75=282, p90=686, max=6,966
        | (notes.md N10).
        */
        'conflict_tier_percentiles' => [0.50, 0.75, 0.90],
    ],

];
