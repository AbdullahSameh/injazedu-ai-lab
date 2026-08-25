# Quickstart — Lab Foundation Bootstrap

**Feature**: P0 المراحل 0–2 · **Date**: 2026-08-20

The full install story is المرحلة 11's `README.md`. This is the developer path for *this increment*.

> ## ⛔ Step 0 — Blocking, and it currently fails
>
> ```bash
> fdesetup status     # measured 2026-08-20: "FileVault is Off."
> ```
>
> The production snapshot — 57,482 users, ~70K orders, **~24,408 API access tokens that may still be
> valid against the live platform** — is sitting on an unencrypted disk right now.
>
> 1. System Settings → Privacy & Security → FileVault → **Turn On**
> 2. Store the recovery key **off this machine**
> 3. Record where (never the key itself) in `docs/runbooks/safety.md`
>
> Encryption converts in the background; `Encryption in progress` is enough to proceed. Nothing below
> is accepted until this passes. — P0 §8 Item A, and the constitution's non-waivable section.

## 1. Start the container engine

```bash
open -a OrbStack          # measured: installed, daemon not running
docker ps                 # must respond
```

## 2. Preflight

```bash
scripts/preflight-check.sh
```

Five conditions, each reported separately. Exit `0` or nothing proceeds. Expect a `[FAIL]` on
encryption until step 0 is done.

## 3. Repository boundary

```bash
scripts/verify-repo-boundary.sh
```

13 assertions including one inverted case — `.env.example` must come back **not** ignored. If that
one fails, the ignore rules are over-broad and the committed template would vanish.

## 4. Lab database

```bash
cp .env.example .env
# set LAB_DB_PASSWORD to a locally generated value — it must never be committed

docker compose up -d postgres
scripts/verify-data-layer.sh --with-restart
```

Seven assertions. The `--with-restart` run stops and starts the service to prove the marker row
survives.

## Verify the whole increment

```bash
scripts/preflight-check.sh && \
scripts/verify-repo-boundary.sh && \
scripts/verify-data-layer.sh --with-restart && \
echo "INCREMENT GREEN"
```

## Traps worth knowing before you hit them

| Trap | Why it bites | Handling |
|---|---|---|
| `init.sql` runs **once**, at volume creation | Add an extension later and an existing volume silently ignores it — while a file-reading check reports success | Verification queries the **live** database (research R6) |
| Host `psql` is 14, server is 17 | Meta-commands like `\dx` query catalogs whose shape changed | All SQL runs in-container (research R3) |
| `mem_limit` is a legacy compose key | Silently dropped ⇒ green check, breached budget | The limit is read back from the running container (research R4) |
| Port 5432 is taken by `postgresql@14` (PID 1984) | It serves your other projects | The Lab binds 5433 and never touches 5432 (ADR-018) |
| iCloud Desktop & Documents sync is **on** | `~/Documents` and `~/Desktop` are relocated into the iCloud container; a naive "is there a OneDrive folder?" check returns a false safe | The repo lives in `~/Projects` — outside both. The check tests the resolved real path against all sync roots (research R1) |
| `/bin/bash` is **3.2** | No associative arrays, no `mapfile`, no `${var,,}` | Scripts target 3.2 (research R2) |

## What this increment does **not** give you

No snapshot connection, no `lab_ro` account, no application, no service, no models, no
`lab:health`, no backups, no README. Of P0 §13's 19 acceptance boxes this closes 3, advances 3,
renders 2 vacuously true, and leaves 11 untouched — see the *Acceptance Gate* table in `spec.md`.
Reporting this as "P0 complete" would be a constitutional violation.

**Next increment**: المرحلة 3 — read-only snapshot access and the ADR-020 grant. It has **no**
outstanding human blocker: §8 Item L (a password on the native database's `root` account) is
**optional** under ADR-021 — the account stays password-less, the residual risk is accepted there,
and the compensating controls (FileVault, loopback bind, root never stored in a file, the eleven-
table `lab_ro` grant) are unchanged.
