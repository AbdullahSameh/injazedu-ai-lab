-- §6.1 query 9 — HTML والصور داخل نص السؤال
--
-- Tables read : questions
-- Allowlist   : copy — questions is on source_tables (apps/lab/config/lab.php):
--               readable and copyable. No profile-only table involved.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.1.

-- 9) HTML والصور داخل نص السؤال
SELECT COUNT(*) AS total,
       SUM(name LIKE '%<img%')          AS has_img_tag,
       SUM(name LIKE '%<%')             AS has_any_html,
       SUM(CHAR_LENGTH(name) > 1000)    AS long_stems,
       MAX(CHAR_LENGTH(name))           AS longest_stem
FROM questions WHERE deleted_at IS NULL;
