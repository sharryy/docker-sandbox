# Contributing

Thanks for your interest in improving this package! Contributions are welcome.

## Requirements

- PHP 8.3+ with the `curl` extension
- A running Docker daemon — the test suite spins up real containers
  (Docker Desktop, Colima, and Lima are all fine)

## Getting started

```bash
git clone https://github.com/sharryy/docker-sandbox
cd docker-sandbox
composer install
```

## Before opening a pull request

Please make sure the following pass:

```bash
composer test      # Pest, runs against a real Docker daemon
composer analyse   # PHPStan (level max)
composer format    # Laravel Pint
```

Network-heavy tests that pull images from Docker Hub are skipped by default;
run them with `DOCKER_PULL_TESTS=1 composer test`.

## Guidelines

- Branch off `main` and keep pull requests focused.
- Add or update tests for any behaviour you change.
- Match the existing code style (Pint enforces it).
- Update the `README` / `CHANGELOG` when you add user-facing features.
