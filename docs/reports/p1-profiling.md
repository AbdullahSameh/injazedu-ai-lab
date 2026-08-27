# P1 Profiling Report

**snapshot_taken_at**: 2026-08-07
**run_at**: 2026-08-26T00:30:23Z
**mysql_version**: 9.1.0
**source_database_size_mb**: 2165.98

## 1. bank size

- file: `01-bank-size.sql`
- tables_read: questions
- allowlist: copy
- row_count: 1
- duration_ms: 111

| total | active | soft_deleted |
| --- | --- | --- |
| 29142 | 28747 | 395 |

## 2. options per question

- file: `02-options-per-question.sql`
- tables_read: questions, options
- allowlist: copy
- row_count: 9
- duration_ms: 232

| opt_count | questions |
| --- | --- |
| 0 | 27 |
| 1 | 15 |
| 2 | 159 |
| 3 | 560 |
| 4 | 27773 |
| 5 | 183 |
| 6 | 4 |
| 7 | 1 |
| 8 | 25 |

## 3. correct answer integrity

- file: `03-correct-answer-integrity.sql`
- tables_read: questions, options
- allowlist: copy
- row_count: 4
- duration_ms: 178

| correct_count | questions |
| --- | --- |
| 0 | 31 |
| 1 | 28682 |
| 2 | 33 |
| 4 | 1 |

## 4. points distribution

- file: `04-points-distribution.sql`
- tables_read: options
- allowlist: copy
- row_count: 11
- duration_ms: 33

| points | options_count |
| --- | --- |
| -3 | 1 |
| -1 | 3 |
| 0 | 86593 |
| 1 | 28777 |
| 2 | 1 |
| 4 | 63 |
| 5 | 265 |
| 7 | 1 |
| 8 | 1 |
| 20 | 10 |
| 25 | 4 |

## 5. option order ties

- file: `05-option-order-ties.sql`
- tables_read: options
- allowlist: copy
- row_count: 1
- duration_ms: 132

| questions_with_order_ties |
| --- |
| 29075 |

## 6. description hint fill

- file: `06-description-hint-fill.sql`
- tables_read: questions
- allowlist: copy
- row_count: 1
- duration_ms: 10

| total | has_description | has_hint |
| --- | --- | --- |
| 28747 | 336 | 329 |

## 7. general vs course quizzes

- file: `07-general-vs-course-quizzes.sql`
- tables_read: quizzes
- allowlist: copy
- row_count: 2
- duration_ms: 12

| kind | quizzes | active |
| --- | --- | --- |
| general | 187 | 180 |
| course | 3135 | 3056 |

## 8. questions per quiz

- file: `08-questions-per-quiz.sql`
- tables_read: quizzes, sections, questions
- allowlist: copy
- row_count: 43
- duration_ms: 40

| questions_per_quiz | quizzes |
| --- | --- |
| 1 | 9 |
| 2 | 25 |
| 3 | 131 |
| 4 | 146 |
| 5 | 680 |
| 6 | 133 |
| 7 | 143 |
| 8 | 150 |
| 9 | 86 |
| 10 | 935 |
| 11 | 83 |
| 12 | 76 |
| 13 | 60 |
| 14 | 54 |
| 15 | 118 |
| 16 | 42 |
| 17 | 15 |
| 18 | 19 |
| 19 | 20 |
| 20 | 79 |
| 21 | 8 |
| 22 | 6 |
| 23 | 2 |
| 24 | 6 |
| 25 | 10 |
| 28 | 2 |
| 29 | 2 |
| 30 | 5 |
| 33 | 1 |
| 35 | 1 |
| 36 | 1 |
| 40 | 1 |
| 45 | 1 |
| 50 | 11 |
| 51 | 1 |
| 52 | 3 |
| 55 | 3 |
| 56 | 1 |
| 60 | 2 |
| 88 | 2 |
| 93 | 1 |
| 100 | 1 |
| 106 | 1 |

## 9. html and media in stems

- file: `09-html-and-media-in-stems.sql`
- tables_read: questions
- allowlist: copy
- row_count: 1
- duration_ms: 45

| total | has_img_tag | has_any_html | long_stems | longest_stem |
| --- | --- | --- | --- | --- |
| 28747 | 0 | 0 | 3 | 2203 |

## 10. quiz files placement

- file: `10-quiz-files-placement.sql`
- tables_read: quiz_files
- allowlist: copy
- row_count: 2
- duration_ms: 13

| type | at_question | at_section | total |
| --- | --- | --- | --- |
| image | 5582 | 0 | 5582 |
| audio | 0 | 4 | 4 |

## 11. literal duplicates md5

- file: `11-literal-duplicates-md5.sql`
- tables_read: questions
- allowlist: copy
- row_count: 1
- duration_ms: 61

| duplicate_groups | redundant_questions |
| --- | --- |
| 4689 | 17331 |

## 12. sections shared stimulus

- file: `12-sections-shared-stimulus.sql`
- tables_read: sections
- allowlist: copy
- row_count: 1
- duration_ms: 2

| sections_total | long_stimulus | named |
| --- | --- | --- |
| 3315 | 0 | 3315 |

## 13. answer data volume

- file: `13-answer-data-volume.sql`
- tables_read: question_result
- allowlist: copy
- row_count: 1
- duration_ms: 6317

| answers | results | questions_with_data |
| --- | --- | --- |
| 13776378 | 1109201 | 27946 |

## 14. answers per question buckets

- file: `14-answers-per-question-buckets.sql`
- tables_read: question_result
- allowlist: copy
- row_count: 4
- duration_ms: 4448

| bucket | questions |
| --- | --- |
| a_100_plus | 8431 |
| b_30_to_99 | 9636 |
| c_10_to_29 | 6199 |
| d_under_10 | 3680 |

## 15. enrolment source course user vs order

- file: `15-enrolment-source-course-user-vs-order.sql`
- tables_read: course_user, course_order, orders
- allowlist: profile-only
- row_count: 2
- duration_ms: 177

| src | rows_ | users_ | courses_ |
| --- | --- | --- | --- |
| course_user | 249 | 17 | 219 |
| course_order | 71228 | 28292 | 228 |

## 16. course user roles

- file: `16-course-user-roles.sql`
- tables_read: course_user, user_roles, roles
- allowlist: profile-only
- row_count: 2
- duration_ms: 5

| role | users_in_course_user |
| --- | --- |
| trainer | 17 |
| user | 17 |

## 17. telegram channel coverage

- file: `17-telegram-channel-coverage.sql`
- tables_read: courses
- allowlist: copy
- row_count: 1
- duration_ms: 6

| courses | has_channel | has_group | has_private |
| --- | --- | --- | --- |
| 228 | 13 | 224 | 218 |

## 18. book course abandoned

- file: `18-book-course-abandoned.sql`
- tables_read: book_course
- allowlist: profile-only
- row_count: 1
- duration_ms: 1

| book_course_rows |
| --- |
| 0 |

