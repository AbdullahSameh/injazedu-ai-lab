-- §6.1 query 6 — نسبة امتلاء description / hint (بديل explanation)
--
-- Tables read : questions
-- Allowlist   : copy — questions is on source_tables (apps/lab/config/lab.php):
--               readable and copyable. No profile-only table involved.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.1.

-- 6) نسبة امتلاء description / hint (بديل explanation)
SELECT COUNT(*) AS total,
       SUM(description IS NOT NULL AND TRIM(description) <> '') AS has_description,
       SUM(hint IS NOT NULL AND TRIM(hint) <> '')               AS has_hint
FROM questions WHERE deleted_at IS NULL;
