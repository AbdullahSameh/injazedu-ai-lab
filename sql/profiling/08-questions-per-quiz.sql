-- §6.1 query 8 — عدد الأسئلة لكل اختبار (لفهم أحجام الاختبارات الفعلية)
--
-- Tables read : quizzes, sections, questions
-- Allowlist   : copy — all three are on source_tables (apps/lab/config/lab.php):
--               readable and copyable. No profile-only table involved.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.1.

-- 8) عدد الأسئلة لكل اختبار (لفهم أحجام الاختبارات الفعلية)
SELECT questions_per_quiz, COUNT(*) AS quizzes FROM (
  SELECT z.id, COUNT(q.id) AS questions_per_quiz
  FROM quizzes z
  JOIN sections s  ON s.quiz_id = z.id     AND s.deleted_at IS NULL
  JOIN questions q ON q.section_id = s.id  AND q.deleted_at IS NULL
  WHERE z.deleted_at IS NULL
  GROUP BY z.id
) t GROUP BY questions_per_quiz ORDER BY questions_per_quiz;
