-- Fixture — declares a forbidden table on purpose
--
-- Tables read : users
-- Allowlist   : copy — deliberately false, for ProfileDeclarationTest.
--
-- Used to prove lab:profile refuses BEFORE any SQL executes (FR-002, SC-002).

SELECT COUNT(*) AS total FROM users;
