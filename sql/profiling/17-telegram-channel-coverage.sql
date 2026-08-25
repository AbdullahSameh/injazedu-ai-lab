-- §6.2 query 17 — تغطية قنوات Telegram (أساس P6)
--
-- Tables read : courses
-- Allowlist   : copy — courses is on source_tables (apps/lab/config/lab.php):
--               readable and copyable. No profile-only table involved.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.2.

-- 17) تغطية قنوات Telegram (أساس P6)
SELECT COUNT(*) AS courses,
       SUM(telegram_channel IS NOT NULL AND TRIM(telegram_channel) <> '') AS has_channel,
       SUM(telegram_group   IS NOT NULL AND TRIM(telegram_group)   <> '') AS has_group,
       SUM(telegram_private IS NOT NULL AND TRIM(telegram_private) <> '') AS has_private
FROM courses WHERE deleted_at IS NULL;
