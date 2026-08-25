-- §6.1 query 12 — الأقسام التي تحمل نصًا مشتركًا (passage) — مهم لـSTEP/IELTS
--
-- Tables read : sections
-- Allowlist   : copy — sections is on source_tables (apps/lab/config/lab.php):
--               readable and copyable. No profile-only table involved.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.1.

-- 12) الأقسام التي تحمل نصًا مشتركًا (passage) — مهم لـSTEP/IELTS
SELECT COUNT(*) AS sections_total,
       SUM(description IS NOT NULL AND CHAR_LENGTH(description) > 200) AS long_stimulus,
       SUM(name IS NOT NULL AND TRIM(name) <> '') AS named
FROM sections WHERE deleted_at IS NULL;
