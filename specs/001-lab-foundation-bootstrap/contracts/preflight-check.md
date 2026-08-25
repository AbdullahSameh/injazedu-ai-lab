# Contract — `scripts/preflight-check.sh`

**Implements**: FR-001…FR-008 · **Gates**: SC-001, SC-002 · **Phase**: المرحلة 0

## Invocation

```text
scripts/preflight-check.sh [--quiet]
```

No arguments required. `--quiet` suppresses per-condition lines and prints only the verdict; exit
status is unchanged. No flag skips a check — there is deliberately no `--force`.

## Exit codes

| Code | Meaning |
|---|---|
| `0` | All conditions satisfied. Safe to proceed. |
| `1` | One or more blocking conditions failed. **Work must not proceed.** |
| `2` | The check itself could not run (missing tool, unreadable runbook). Never treated as a pass. |

## Output contract

English (FR-028). One line per condition, in fixed order, each independently readable (FR-008):

```text
[ OK ] encryption            On
[ OK ] recovery key          off-machine, attested 2026-08-20
[ OK ] sync exposure         /Users/abdullah/Projects/injazedu-ai-lab — outside all sync roots
[ OK ] free disk             135 GB (threshold 20 GB)
[ OK ] container engine      responding (orbstack)

PREFLIGHT PASSED — machine is safe to hold the snapshot
```

Failure output names the condition, the measured value, and the remediation:

```text
[FAIL] encryption            Off
       The production snapshot is on an unencrypted disk.
       Remediation: System Settings > Privacy & Security > FileVault > Turn On.
       Store the recovery key off this machine, then record its location in
       docs/runbooks/safety.md.
       BLOCKING: no further phase may proceed (P0 §8 Item A).
...
PREFLIGHT FAILED — 1 of 5 conditions blocking
```

## Condition contracts

| # | Condition | Probe | Pass |
|---|---|---|---|
| 1 | Disk encryption | `fdesetup status` | matches `On` or `Encryption in progress`; anything else, including unparseable, fails |
| 2 | Recovery-key custody | parse `docs/runbooks/safety.md` | location present and not the placeholder token; `attested_on` parses as a date |
| 3 | Sync exposure | `realpath` repo root, test against sync roots (research R1) | no match; on match, print the matched root |
| 4 | Free disk | `df -g /` | ≥ 20 GB |
| 5 | Container engine | socket probe + `docker context ls` | daemon responds; distinguish `not installed` from `installed but not running` |

## Behavioural guarantees

- **Read-only.** Touches no snapshot, writes no file, mutates no git state.
- **Idempotent.** Repeated runs produce identical output for identical machine state.
- **No caching.** Every run recomputes; a prior pass is never reused.
- **Fails closed.** Any probe that errors is reported as a failure, never skipped or assumed passing.
- **bash 3.2 only** (research R2).

## Test cases

| Given | Expect |
|---|---|
| FileVault `Off` (**current machine state**) | exit `1`, condition 1 `[FAIL]`, remediation printed — this is **SC-002** |
| FileVault `On`, all others pass | exit `0`, `PREFLIGHT PASSED` |
| `safety.md` still holding the placeholder | exit `1`, condition 2 `[FAIL]` |
| Repo relocated under `~/Documents` with iCloud D&D on | exit `1`, condition 3 `[FAIL]`, matched root printed |
| OrbStack installed, daemon stopped (**current machine state**) | exit `1`, condition 5 reports `installed but not running`, not `not installed` |
| `fdesetup` absent from PATH | exit `2`, not `0` |
| Timed run | completes < 30 s — **SC-001** |
