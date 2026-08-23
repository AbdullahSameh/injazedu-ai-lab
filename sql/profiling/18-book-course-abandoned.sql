-- §6.2 query 18 — هل جدول book_course القديم مهجور فعلًا؟
--
-- Tables read : book_course
-- Allowlist   : PROFILE-ONLY — book_course is on profile_tables
--               (apps/lab/config/lab.php, added 2026-08-23, P0 §3.2):
--               READ AS COUNTS ONLY, never copied into the Lab.
--               Before the split this query was blocked by a rule written about
--               copying; with the split in place NO query in this pack is blocked.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.2.

-- 18) هل جدول book_course القديم مهجور فعلًا؟
SELECT COUNT(*) AS book_course_rows FROM book_course;
