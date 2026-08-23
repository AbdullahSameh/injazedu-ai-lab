-- §6.1 query 5 — تكرار قيم order داخل السؤال (خطر اشتقاق المفاتيح)
--
-- Tables read : options
-- Allowlist   : copy — options is on source_tables (apps/lab/config/lab.php):
--               readable and copyable. No profile-only table involved.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.1.

-- 5) تكرار قيم order داخل السؤال (خطر اشتقاق المفاتيح)
SELECT COUNT(*) AS questions_with_order_ties FROM (
  SELECT question_id FROM options WHERE deleted_at IS NULL
  GROUP BY question_id, `order` HAVING COUNT(*) > 1
) t;
