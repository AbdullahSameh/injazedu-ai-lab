-- §6.1 query 7 — عام مقابل خاص بدورة
--
-- Tables read : quizzes
-- Allowlist   : copy — quizzes is on source_tables (apps/lab/config/lab.php):
--               readable and copyable. No profile-only table involved.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.1.

-- 7) عام مقابل خاص بدورة
SELECT CASE WHEN course_id IS NULL THEN 'general' ELSE 'course' END AS kind,
       COUNT(*) AS quizzes, SUM(status = 1) AS active
FROM quizzes WHERE deleted_at IS NULL GROUP BY kind;
