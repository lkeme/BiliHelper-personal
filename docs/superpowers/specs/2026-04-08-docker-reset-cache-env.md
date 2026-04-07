# Docker Reset-Cache Env Flags

**Goal:** Let Docker and Docker Compose trigger the existing CLI cache-reset behavior by environment variables, without introducing a second runtime mode.

**Scope:** Add environment-variable to CLI-flag mapping only in the Docker default startup path, and document the behavior in the Docker-facing docs and compose examples.

## Problem

The project already supports:

- `--reset-cache`
- `--purge-auth`

for local CLI execution.

This is easy to use locally:

```sh
php app.php --reset-cache
php app.php --reset-cache --purge-auth
```

But it is awkward in Docker / Docker Compose because the default startup path is hidden inside `docker/entrypoint.sh`, and users should not need to override the command just to clear cache before a run.

## Final Behavior

Two environment variables are added for Docker startup:

- `RESET_CACHE`
- `PURGE_AUTH`

### Mapping rules

- if `RESET_CACHE` is truthy, append `--reset-cache`
- if `PURGE_AUTH` is truthy **and** `RESET_CACHE` is truthy, append `--purge-auth`
- if `PURGE_AUTH` is truthy but `RESET_CACHE` is not truthy, ignore `PURGE_AUTH`

Truthiness should accept:

- `1`
- `true`
- `yes`
- `on`

Case-insensitive handling is preferred.

## Boundary

This mapping must apply only when Docker uses the default `run` startup path that eventually executes:

```sh
php /app/app.php
```

It must **not** silently rewrite arbitrary custom commands passed by the user.

This means:

- `entrypoint.sh run` with no custom command: env flags are applied
- `entrypoint.sh run php /app/app.php m:d ...`: env flags may be applied only if this still resolves to the app entry command in a controlled way
- custom commands unrelated to app bootstrap must be passed through unchanged

If implementation simplicity requires a stricter rule, prefer this narrower contract:

- env flags are applied only when no custom command arguments are provided

That is the recommended implementation choice.

## No Compatibility Split

The design must not add a second cache-reset implementation.

Docker env vars are only a transport layer that maps to the existing CLI flags:

- `--reset-cache`
- `--purge-auth`

All cache-clearing behavior remains owned by the existing CLI command flow.

## Files Expected to Change

- `docker/entrypoint.sh`
- `docker/Dockerfile`
- `docker-compose.yml`
- `docker-compose.test.yaml`
- `README.md`
- `docs/DOC.md`

## Examples

### Compose

```yaml
environment:
  BRANCH: master
  CAPTCHA: 1
  RESET_CACHE: 1
  PURGE_AUTH: 0
```

Equivalent runtime behavior:

```sh
php /app/app.php --reset-cache
```

### Compose with auth purge

```yaml
environment:
  RESET_CACHE: 1
  PURGE_AUTH: 1
```

Equivalent runtime behavior:

```sh
php /app/app.php --reset-cache --purge-auth
```

## Logging Recommendation

Docker startup should emit one concise info log when env flags are applied, for example:

- `Docker 启动参数: 附加 --reset-cache`
- `Docker 启动参数: 附加 --reset-cache --purge-auth`

This is useful for confirming actual container behavior during troubleshooting.

## Non-Goals

Out of scope:

- adding a new CLI mode
- introducing a generic arbitrary `APP_FLAGS` passthrough
- changing the meaning of `--reset-cache`
- changing the meaning of `--purge-auth`
