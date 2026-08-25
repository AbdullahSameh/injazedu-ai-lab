-- §6.1 query 11 — نظرة أولى على التكرار الحرفي (بلا تطبيع بعد)
--
-- Tables read : questions
-- Allowlist   : copy — questions is on source_tables (apps/lab/config/lab.php):
--               readable and copyable. No profile-only table involved.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.1.

-- 11) نظرة أولى على التكرار الحرفي (بلا تطبيع بعد)
SELECT COUNT(*) AS duplicate_groups,
       SUM(c) - COUNT(*) AS redundant_questions
FROM (
  SELECT MD5(name) AS h, COUNT(*) AS c
  FROM questions WHERE deleted_at IS NULL
  GROUP BY h HAVING COUNT(*) > 1
) t;
