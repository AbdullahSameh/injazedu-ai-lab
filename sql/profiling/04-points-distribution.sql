-- §6.1 query 4 — توزيع points: هل 0/1 أم درجات متفاوتة؟
--
-- Tables read : options
-- Allowlist   : copy — options is on source_tables (apps/lab/config/lab.php):
--               readable and copyable. No profile-only table involved.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.1.

-- 4) توزيع points: هل 0/1 أم درجات متفاوتة؟
SELECT points, COUNT(*) AS options_count
FROM options WHERE deleted_at IS NULL
GROUP BY points ORDER BY points;
