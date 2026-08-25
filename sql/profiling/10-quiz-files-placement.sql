-- §6.1 query 10 — الوسائط: على مستوى السؤال أم القسم؟
--
-- Tables read : quiz_files
-- Allowlist   : copy — quiz_files is on source_tables (apps/lab/config/lab.php):
--               readable and copyable. No profile-only table involved.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.1.

-- 10) الوسائط: على مستوى السؤال أم القسم؟
SELECT type,
       SUM(question_id IS NOT NULL) AS at_question,
       SUM(section_id  IS NOT NULL) AS at_section,
       COUNT(*) AS total
FROM quiz_files GROUP BY type;
