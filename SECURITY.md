# Security Policy

## Supported Versions

Security fixes are applied to the latest released version.

## Reporting a Vulnerability

Please **do not** open a public issue for security vulnerabilities.

Instead, report them privately via either:

- GitHub's [private vulnerability reporting](https://github.com/sharryy/docker-sandbox/security/advisories/new), or
- email to **ibneadam388@gmail.com**.

You will receive a response as soon as possible. Please give us a reasonable
amount of time to address the issue before any public disclosure.

## Scope

This package runs untrusted code inside hardened Docker containers. The hardening
applied by `Sandbox`/`run()` (no network, non-root, read-only rootfs, dropped
capabilities, no-new-privileges, pid/memory limits) reduces risk but does **not**
replace a properly secured Docker daemon and host. Reports about weakening or
bypassing these defaults are especially welcome.
