# Changelog

All notable changes to `docker-sandbox` will be documented in this file.

## v0.1.0 - 2026-06-22

Initial release.

- Fluent connection management over a unix socket or TCP/TLS, with automatic
  socket discovery and Docker API version negotiation.
- `Sandbox` facade with `php`, `python` and `node` presets, plus custom preset
  registration, for running untrusted code with secure defaults.
- Secure-by-default execution: no network, non-root user, read-only rootfs with
  a writable tmpfs, dropped capabilities, no-new-privileges, no swap, a process
  limit, an enforced timeout, and automatic image pulling.
- `ExecutionResult` with separated stdout/stderr, exit code, OOM and timeout
  flags, and duration.
- Container builder and lifecycle (create/start/stop/restart/kill/pause/unpause/
  rename/remove), inspection, stats, `exec`, real-time log streaming, and file
  transfer in and out via tar archives.
- `ImageManager` for pulling, listing, removing and pruning images.
- A typed exception hierarchy under `Sharryy\Docker\Exceptions`.
