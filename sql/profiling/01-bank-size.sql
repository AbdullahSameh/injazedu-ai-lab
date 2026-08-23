-- §6.1 query 1 — حجم البنك الحقيقي
--
-- Tables read : questions
-- Allowlist   : copy — questions is on source_tables (apps/lab/config/lab.php):
--               readable and copyable. No profile-only table involved.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.1.

-- 1) حجم البنك الحقيقي
SELECT COUNT(*) AS total,
       SUM(deleted_at IS NULL) AS active,
       SUM(deleted_at IS NOT NULL) AS soft_deleted
FROM questions;
