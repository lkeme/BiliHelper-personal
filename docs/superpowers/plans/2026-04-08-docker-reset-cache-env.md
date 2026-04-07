# Docker Reset-Cache Env Flags Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `RESET_CACHE` and `PURGE_AUTH` Docker environment variables that map to the existing CLI cache-reset flags without creating a second runtime path.

**Architecture:** Keep all cache-reset semantics in the existing CLI flags and only add a thin transport layer in `docker/entrypoint.sh`. The entrypoint should translate env vars to `--reset-cache` / `--purge-auth` only for the default `run` startup path with no custom command, while Dockerfile, compose files, and docs expose the new knobs.

**Tech Stack:** POSIX shell in `docker/entrypoint.sh`, existing PHP CLI commands, Dockerfile env defaults, Docker Compose YAML, Markdown docs, temporary local verification scripts that must be deleted before commit.

---

### Task 1: Map Docker Env Vars to Existing CLI Flags in the Entrypoint

**Files:**
- Modify: `docker/entrypoint.sh`
- Test: `tools/tmp-docker-reset-cache-entrypoint.sh`

- [ ] **Step 1: Write the failing entrypoint verification script**

Create `tools/tmp-docker-reset-cache-entrypoint.sh` that:

- extracts the flag-building behavior from `docker/entrypoint.sh`
- simulates these cases:
  - `RESET_CACHE=0`, `PURGE_AUTH=0`
  - `RESET_CACHE=1`, `PURGE_AUTH=0`
  - `RESET_CACHE=1`, `PURGE_AUTH=1`
  - `RESET_CACHE=0`, `PURGE_AUTH=1`
- asserts the resulting default app command becomes:
  - `php /app/app.php`
  - `php /app/app.php --reset-cache`
  - `php /app/app.php --reset-cache --purge-auth`
  - `php /app/app.php`
- asserts custom commands are left untouched

The first version should fail because the entrypoint does not yet append any env-derived flags.

```sh
#!/bin/sh
set -eu

ENTRYPOINT_PATH="$(dirname "$0")/../docker/entrypoint.sh"

# expected to fail until env flag translation exists
sh "$ENTRYPOINT_PATH" run
```

- [ ] **Step 2: Run the script to verify it fails**

Run: `sh ./tools/tmp-docker-reset-cache-entrypoint.sh`
Expected: assertion failure showing `RESET_CACHE` / `PURGE_AUTH` are ignored.

- [ ] **Step 3: Implement env-to-flag mapping in the entrypoint**

Modify `docker/entrypoint.sh` to:

- add a truthy-value helper that accepts:
  - `1`
  - `true`
  - `yes`
  - `on`
- add a helper that returns the extra CLI args:
  - `--reset-cache`
  - optionally `--purge-auth`
- log one concise info line when flags are applied
- apply these flags only when:
  - mode is `run`
  - no custom command arguments were provided
- leave arbitrary custom commands untouched

- [ ] **Step 4: Re-run the script to verify it passes**

Run: `sh ./tools/tmp-docker-reset-cache-entrypoint.sh`
Expected: all simulated cases pass and custom-command passthrough remains unchanged.

- [ ] **Step 5: Remove the temporary script and commit**

Run:

```bash
git add docker/entrypoint.sh
git commit -m "feat: map docker env flags to cache reset args"
```

Before commit:

- delete `tools/tmp-docker-reset-cache-entrypoint.sh`
- verify it is not staged

### Task 2: Publish the New Env Vars in Image and Compose Defaults

**Files:**
- Modify: `docker/Dockerfile`
- Modify: `docker-compose.yml`
- Modify: `docker-compose.test.yaml`
- Test: `tools/tmp-docker-env-doc-check.php`

- [ ] **Step 1: Write the failing env exposure check**

Create `tools/tmp-docker-env-doc-check.php` that loads the Docker files as text and asserts:

- `docker/Dockerfile` declares `RESET_CACHE` and `PURGE_AUTH`
- `docker-compose.yml` documents both vars in `environment:`
- `docker-compose.test.yaml` documents both vars in `environment:`

The first run should fail because those vars do not yet exist in the Docker files.

```php
<?php
declare(strict_types=1);

foreach ([
    'docker/Dockerfile',
    'docker-compose.yml',
    'docker-compose.test.yaml',
] as $path) {
    $text = file_get_contents(__DIR__ . '/../' . $path);
    assert(str_contains($text, 'RESET_CACHE'));
    assert(str_contains($text, 'PURGE_AUTH'));
}
```

- [ ] **Step 2: Run the script to verify it fails**

Run: `rtk php tools/tmp-docker-env-doc-check.php`
Expected: assertion failure because the env vars are not present.

- [ ] **Step 3: Update image and compose defaults**

Modify:

- `docker/Dockerfile`
  - add default env definitions for `RESET_CACHE` and `PURGE_AUTH`
- `docker-compose.yml`
  - add commented/default examples for both vars
- `docker-compose.test.yaml`
  - add commented/default examples for both vars

Keep the examples simple and aligned with the spec:

```yaml
RESET_CACHE: 0
PURGE_AUTH: 0
```

- [ ] **Step 4: Re-run the script to verify it passes**

Run: `rtk php tools/tmp-docker-env-doc-check.php`
Expected: all Docker files now expose the new env vars.

- [ ] **Step 5: Remove the temporary script and commit**

Run:

```bash
git add docker/Dockerfile docker-compose.yml docker-compose.test.yaml
git commit -m "chore: expose docker reset-cache env vars"
```

Before commit:

- delete `tools/tmp-docker-env-doc-check.php`
- verify it is not staged

### Task 3: Update User-Facing Documentation

**Files:**
- Modify: `README.md`
- Modify: `docs/DOC.md`
- Test: `tools/tmp-docker-reset-cache-doc-check.php`

- [ ] **Step 1: Write the failing doc check**

Create `tools/tmp-docker-reset-cache-doc-check.php` that asserts the docs mention:

- `RESET_CACHE`
- `PURGE_AUTH`
- the equivalence to `--reset-cache`
- the equivalence to `--purge-auth`

The first run should fail because the docs do not yet mention the new env vars.

```php
<?php
declare(strict_types=1);

foreach (['README.md', 'docs/DOC.md'] as $path) {
    $text = file_get_contents(__DIR__ . '/../' . $path);
    assert(str_contains($text, 'RESET_CACHE'));
    assert(str_contains($text, 'PURGE_AUTH'));
}
```

- [ ] **Step 2: Run the script to verify it fails**

Run: `rtk php tools/tmp-docker-reset-cache-doc-check.php`
Expected: assertion failure because the docs are not updated yet.

- [ ] **Step 3: Update README and DOC**

In `README.md`:

- extend the Docker hint section with a short example showing:
  - `RESET_CACHE=1`
  - `PURGE_AUTH=1`

In `docs/DOC.md`:

- document the two env vars in the Docker usage section
- explain:
  - `RESET_CACHE=1` → same as `--reset-cache`
  - `RESET_CACHE=1` + `PURGE_AUTH=1` → same as `--reset-cache --purge-auth`
- clarify that custom commands are not rewritten

- [ ] **Step 4: Re-run the script to verify it passes**

Run: `rtk php tools/tmp-docker-reset-cache-doc-check.php`
Expected: both docs mention the new env behavior clearly.

- [ ] **Step 5: Remove the temporary script and commit**

Run:

```bash
git add README.md docs/DOC.md
git commit -m "docs: explain docker reset-cache env flags"
```

Before commit:

- delete `tools/tmp-docker-reset-cache-doc-check.php`
- verify it is not staged

### Task 4: Final Verification and Integration Commit

**Files:**
- Verify: `docker/entrypoint.sh`
- Verify: `docker/Dockerfile`
- Verify: `docker-compose.yml`
- Verify: `docker-compose.test.yaml`
- Verify: `README.md`
- Verify: `docs/DOC.md`

- [ ] **Step 1: Run focused verification commands**

Run:

```bash
rtk php app.php --help
sh ./docker/entrypoint.sh run php --version
```

Expected:

- CLI help still works
- custom commands still pass through untouched

- [ ] **Step 2: Run one explicit default-run verification**

Use a temporary shell command to simulate:

```bash
RESET_CACHE=1 PURGE_AUTH=1 sh ./docker/entrypoint.sh run
```

The verification only needs to confirm the generated startup log or final executed arg shape. If a temporary helper script is used, delete it before finishing.

- [ ] **Step 3: Inspect git state**

Run: `rtk git status --short`
Expected:

- only intended Docker/docs files are modified
- no temporary test scripts remain

- [ ] **Step 4: Create the final integration commit**

Run:

```bash
git add docker/entrypoint.sh docker/Dockerfile docker-compose.yml docker-compose.test.yaml README.md docs/DOC.md
git commit -m "feat: support docker cache reset env flags"
```
