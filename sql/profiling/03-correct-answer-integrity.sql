-- §6.1 query 3 — *** الأهم *** سلامة الجواب الصحيح
--
-- Tables read : questions, options
-- Allowlist   : copy — both are on source_tables (apps/lab/config/lab.php):
--               readable and copyable. No profile-only table involved.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.1.

-- 3) *** الأهم *** سلامة الجواب الصحيح
SELECT correct_count, COUNT(*) AS questions FROM (
  SELECT q.id, SUM(CASE WHEN o.points > 0 THEN 1 ELSE 0 END) AS correct_count
  FROM questions q
  LEFT JOIN options o ON o.question_id = q.id AND o.deleted_at IS NULL
  WHERE q.deleted_at IS NULL
  GROUP BY q.id
) t GROUP BY correct_count ORDER BY correct_count;
