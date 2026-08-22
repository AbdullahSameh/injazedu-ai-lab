# Quickstart — Source Access & Lab Runtime

The runnable path from the previous increment's green state to this one's. Every command runs on
this machine; nothing connects to `injazedu.co`.

```bash
PHP84=/opt/homebrew/opt/php@8.4/bin/php
```

---

## 0. Prove the one open assumption

```bash
$PHP84 -r '
  $p = new PDO("mysql:host=127.0.0.1;port=3306;dbname=injazedu", "root", "");
  echo $p->query("SELECT COUNT(*) FROM questions")->fetchColumn(), PHP_EOL;'
# expect: 29142
```

If this fails, **stop and ask.** Do not enable TLS on loopback, create a database user, or change the
authentication plugin — those are decisions the operator has not made (notes.md N1).

## 1. Source access

```bash
scripts/verify-injazedu-access.sh
```

Eleven allowlisted tables readable, each reported by name, and `questions` = 29142. There is no
inverted write check here — `root` can write, and the script does not pretend otherwise. The write
guarantee lives in the application and is proven in step 3.

## 2. Model runtime

Start this early; the second pull is 4.1 GB.

```bash
curl -fsSL https://ollama.com/install.sh | sh

ollama pull embeddinggemma:300m-qat-q4_0     # 227.5 MB
ollama pull gemma4:e2b-it-qat                # 4,135.5 MB

scripts/verify-model-runtime.sh --with-memory
```

The official macOS app registers as a login item and starts hidden after installation. Keep its
defaults until the measurement says otherwise.

The memory figure is the point of this step. Compare it against P0 §12.3 and the 13 GB idle ceiling.
An overrun is a go/no-go trigger — write the decision down. Limits get pinned only if the number
demands it.

## 3. The application

```bash
$PHP84 $(which composer) create-project laravel/laravel apps/lab "^13.0"
cd apps/lab
$PHP84 $(which composer) require filament/filament:"^5.0"
$PHP84 artisan filament:install --panels
$PHP84 artisan key:generate
$PHP84 artisan migrate
$PHP84 artisan make:filament-user
cd ../..
```

Use `$PHP84` for **resolution as well as execution** — Composer resolves against the running
interpreter (notes.md N2). Never `brew link`.

Then wire, per `data-model.md`:

- `apps/lab/.env` and `.env.example` — both key groups (§3)
- `config/database.php` — `pgsql` default, `injazedu` with an empty write host list (guard 1)
- `app/Providers/AppServiceProvider.php` — the read-only query listener (guard 2)
- `config/lab.php` + `app/Support/SourceReader.php` — the allowlist (guard 3)
- `config/logging.php` — the `lab` channel

```bash
scripts/verify-lab-app.sh
```

This checks the app runs 8.4 while the machine still links 8.2, migrations applied, the probe job's
row present with a worker pid that is not the dispatcher's, the two env files agreeing on the shared
password, and the panel requiring auth — then hands off to `$PHP84 artisan test` for the three
guardrail tests.

## 4. Re-prove the boundary

```bash
scripts/verify-repo-boundary.sh
```

The framework brought thousands of dependency files and its own ignore rules. This must still pass.

## 5. Increment green

```bash
scripts/preflight-check.sh \
  && scripts/verify-repo-boundary.sh \
  && scripts/verify-data-layer.sh \
  && scripts/verify-injazedu-access.sh \
  && scripts/verify-model-runtime.sh \
  && scripts/verify-lab-app.sh \
  && echo "INCREMENT GREEN"
```

Then reboot and re-run the last two — a runtime that works until the machine restarts is exactly the
failure worth catching.

---

## What is still not true

No AI service exists. Nothing calls a model. No embedding contract is fixed and no vector has been
stored. No backup has been restored. `php artisan lab:health` does not exist, and neither does
`README.md`. The snapshot refresh policy is still undecided.
