# Quickstart — Service, Health Matrix & Guardrails

The runnable path from `002`'s green state to this one's. Every command runs on this machine; nothing
connects to `injazedu.co`.

```bash
REPO_ROOT=$(git rev-parse --show-toplevel)
PHP84=/opt/homebrew/opt/php@8.4/bin/php
```

The container engine, MySQL, and Ollama must already be up — `002` left them that way.

---

## 0. Confirm the ground you are standing on

```bash
"$REPO_ROOT/scripts/verify-data-layer.sh"
"$REPO_ROOT/scripts/verify-injazedu-access.sh"     # 11 tables, questions = 29142
"$REPO_ROOT/scripts/verify-model-runtime.sh"       # both tags, loopback binding
```

If any of these fails, fix that before starting here — every check below assumes them.

## 1. The service

```bash
cd "$REPO_ROOT/apps/ai-service"
uv sync --frozen
if [ ! -f .env ]; then
  cp .env.example .env
  echo "Fill .env with the Lab DB, runtime URL, and contract version before continuing."
fi
```

`pyproject.toml` and `uv.lock` are already committed. `uv sync --frozen` reproduces that environment
without rewriting either file, and the guarded copy never overwrites an existing local configuration.

`apps/ai-service/.env` holds **no MySQL key of any kind**. Every read of the source goes through
Laravel's guarded connection or does not happen (ADR-013).

Start it — by hand, in its own terminal. There is no supervisor and no login item this increment; the
one-command starter is المرحلة 11's (operator decision, 2026-08-22).

```bash
uv run uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

Then, from anywhere:

```bash
"$REPO_ROOT/scripts/verify-ai-service.sh"
```

Four endpoints answer, the socket is bound to loopback only, a non-loopback connection is refused, the
contract version matches the one in `apps/lab/.env`, and no MySQL key appears in the service's
environment.

### The contract, by hand

```bash
curl -s 127.0.0.1:8001/embed -H 'content-type: application/json' \
  -d '{"text":"ما هو الرقم الهيدروجيني للماء النقي؟"}' |
  python3 -c 'import json,sys,math; d=json.load(sys.stdin);
print(d["dimension"], round(math.sqrt(sum(x*x for x in d["vector"])),6),
      d["embedding_config_version"], d["truncated"])'
# expect: 768 1.0 eg300m-qat-q4_0/sim-v1/768/l2norm False
```

Then prove truncation is reported rather than swallowed — the runtime gives no error for this (N2):

```bash
python3 -c 'import json;print(json.dumps({"text":"سؤال طويل جدا "*800}))' > /tmp/long.json
curl -s 127.0.0.1:8001/embed -H 'content-type: application/json' -d @/tmp/long.json |
  python3 -c 'import json,sys; d=json.load(sys.stdin);
print("truncated:", d["truncated"], "tokens:", d["prompt_eval_count"], "/", d["context_length"])'
# expect: truncated: True  tokens: 2048 / 2048
```

## 2. The guardrails

```bash
cd "$REPO_ROOT/apps/lab"
$PHP84 artisan test --filter='ReadOnlyGuard|SourceTableAllowlist|ForbiddenTableRefusal|NoPiiInLabSchema'
```

Seventeen forbidden tables refused **by name**, one assertion each; the three write-blocking layers
each still blocking alone; no PII-capable column in the Lab schema.

Generate the pepper once. Not twice — regenerating it after P1 has stored `student_ref` values breaks
the link between old and new rows irreversibly.

```bash
$PHP84 -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'   # → STUDENT_REF_PEPPER in apps/lab/.env
```

Back it up off-machine (§8 item F). Nothing in this increment consumes it.

```bash
cd "$REPO_ROOT" && scripts/verify-repo-boundary.sh   # the pepper must not be committable
git grep -n "$(grep '^STUDENT_REF_PEPPER=' "$REPO_ROOT/apps/lab/.env" | cut -d= -f2)" || echo "not in tracked content ✓"
```

## 3. The health matrix

```bash
cd "$REPO_ROOT/apps/lab"
$PHP84 artisan migrate          # adds lab_vector_probes — vector(768), native in Laravel 13
$PHP84 artisan lab:health
```

Check 3 dispatches its probe to the isolated `lab-health` queue and launches its own one-shot worker;
no separately managed worker is needed for this command.

Expect ten rows and exit 0. Checks 9 and 10 print their expectation on the line — they pass **because**
the operation was refused:

```text
 9  Source write attempt     injazedu           must be refused   PASS  ReadOnlyViolation
10  Forbidden table          injazedu.users     must be refused   PASS  refused: users
```

```bash
echo $?    # 0
```

### Prove it fails honestly

```bash
(cd "$REPO_ROOT" && docker compose stop postgres) && $PHP84 artisan lab:health; echo "exit: $?"
(cd "$REPO_ROOT" && docker compose start postgres)
```

Checks 1, 3, 4, and 7 fail naming the affected PostgreSQL path — check 3 uses the approved
PostgreSQL-backed queue and probe table. The rest still report; exit is non-zero. A health command
that cannot fail is documentation, not a test.

## 4. The panel

```bash
$PHP84 artisan serve --port 8000
```

Log in, open **Lab Health**. The page shows **no status** until you press run — then the same ten
results. The result set itself is never persisted; checks 3 and 7 only update their fixed-id,
Laravel-owned probe rows. The Phase 5 cold browser run took **12.87 s** end to end; within it, the
chat and embedding checks reported 6.378 s and 1.645 s respectively (N7). A warm run is under a
second for both model checks (N5).

---

## What green means here

```text
Four endpoints answering on loopback, refusing everything else
A 768-dimension unit-norm vector carrying its contract version
Over-length input reporting its own truncation
Seventeen forbidden tables refused by name
Ten checks, two of them passing by being refused, exit 0
The same ten result statuses on demand in the panel, with the result set itself not persisted
```

## What is still missing

```text
No backup exists and no restore has been attempted   ← المرحلة 9
No full-stack memory figure, no go/no-go             ← المرحلة 10
No README, no runbooks, no profiling queries         ← المرحلة 11
```

P0 cannot be accepted until those three land. المرحلة 9 is next, and its restore drill must reproduce
check 7's vector round-trip after a restore to count as verified.
