# Safety Runbook

**Implements**: FR-006 / FR-006a · **Entity**: data-model §2 · **Read by**:
`scripts/preflight-check.sh` (condition 2 — blocking)

## ⚠️ Warning

This file names a **location**, never key material. Nothing written here may contain the FileVault
recovery key itself, or anything from which it could be derived. If the key's location changes,
update `recovery_key_location` and `attested_on` in the same commit.

## FileVault Recovery-Key Custody Record

```text
recovery_key_location: xlsx on my Google Drive — private account
attested_on: 2026-08-20
attested_by: abdullah
```

- `recovery_key_location` — **where** the recovery key is stored (off this machine). Preflight
  fails while this holds the placeholder token `<UNSET — preflight will fail until edited>`.
- `attested_on` — ISO date the operator verified custody. Must parse as a date.
- `attested_by` — who confirmed custody.

This record is committed **deliberately**: it must survive the loss of the machine it describes,
which is the only circumstance the recovery key exists for.
