# Sandbox — run code safely in Docker containers

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sharryy/sandbox.svg?style=flat-square)](https://packagist.org/packages/sharryy/sandbox)
[![Tests](https://img.shields.io/github/actions/workflow/status/sharryy/sandbox/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sharryy/sandbox/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/sharryy/sandbox.svg?style=flat-square)](https://packagist.org/packages/sharryy/sandbox)

A simple, secure and fluent PHP API for running untrusted code in isolated
Docker containers. It talks directly to the Docker Engine API over a unix
socket or TCP/TLS — no shelling out to the `docker` CLI — and makes the
*easiest* way to run code also the *most locked-down* one.

## Installation

```bash
composer require sharryy/sandbox
```

Requires PHP 8.5+, the cURL extension, and access to a Docker daemon (Docker
Desktop, Colima, Lima, or a remote daemon over TCP/TLS).

## Quick start

```php
use Sharryy\Docker\Sandbox;

$sandbox = new Sandbox();

$result = $sandbox->php('<?php echo 2 + 2;');

$result->output();      // "4"
$result->exitCode();    // 0
$result->successful();  // true
$result->duration();    // seconds
```

`python` and `node` presets ship out of the box:

```php
$sandbox->python('print(6 * 7)')->output();      // "42"
$sandbox->node('console.log(6 * 7)')->output();  // "42"
```

### Register your own preset

```php
use Sharryy\Docker\{Sandbox, Preset};

// Preset(image, filename, interpreter)
Sandbox::register('ruby', new Preset('ruby:3.3-slim', 'main.rb', 'ruby'));

$sandbox->run('ruby', 'puts "hello"')->output(); // "hello"
```

A custom preset with the same name overrides a built-in one.

## Secure by default

Every `Sandbox`/`run()` execution is locked down. The container has **no
network**, runs as a **non-root user**, on a **read-only root filesystem**
with only a small writable `/tmp` tmpfs, with **all Linux capabilities
dropped**, **no-new-privileges**, **no swap**, and a **process limit** (so a
fork bomb can't take down the host). Missing images are pulled automatically,
and a timeout kills runaway code:

```php
use Sharryy\Docker\Exceptions\ProcessTimeoutException;

try {
    $sandbox->php('<?php while (true) {}', timeout: 5);
} catch (ProcessTimeoutException $e) {
    // the container was killed and removed
}
```

`ExecutionResult` exposes `output()`, `errorOutput()`, `exitCode()`,
`successful()`, `failed()`, `timedOut()`, `oomKilled()` and `duration()`.

## Connecting to a daemon

```php
use Sharryy\Docker\{Docker, ConnectionOptions};

// Auto-discovers the socket (DOCKER_HOST, default, Colima, Docker Desktop)
$docker = new Docker();

// Or be explicit:
$docker = new Docker(ConnectionOptions::fromSocket('/var/run/docker.sock'));
$docker = new Docker(ConnectionOptions::fromTcp('127.0.0.1', 2375));
$docker = new Docker(ConnectionOptions::fromTls('docker.example.com', 2376,
    caCert: '/certs/ca.pem', clientCert: '/certs/cert.pem', clientKey: '/certs/key.pem'));
```

The API version is negotiated with the daemon automatically.

## Building containers

For long-lived or custom containers, use the fluent builder:

```php
$container = $docker->containers()
    ->from('redis:alpine')
    ->withName('cache')
    ->withCommand(['redis-server'])
    ->withPort(6379, 6379)
    ->withMemoryLimit('256m')
    ->withCpuLimit(0.5)
    // security hardening (all opt-in here)
    ->withUser('1000:1000')
    ->asReadOnly()
    ->withTmpfs('/tmp')
    ->withPidsLimit(128)
    ->dropCapabilities()
    ->withoutNewPrivileges()
    ->withoutSwap()
    ->withUlimit('nofile', 1024)
    ->create();

$container->start();
```

## Interacting with a container

```php
$container->status();        // 'created' | 'running' | 'exited' | 'paused' | ...
$container->isRunning();
$container->logs();          // combined stdout + stderr
$container->inspect();       // full inspect payload
$container->stats();         // one-shot CPU/memory/network snapshot

$container->pause();
$container->unpause();
$container->restart();
$container->rename('new-name');
$container->stop()->remove();

// Run a command and read its result
$result = $container->exec(['redis-cli', 'ping']);
$result->output();   // "PONG"
$result->exitCode(); // 0

// Follow output in real time
$container->streamLogs(function (string $text, string $stream) {
    // $stream is "stdout" or "stderr"
    echo $text;
});
```

### Moving files in and out

```php
use Sharryy\Docker\Support\Tar;

$container->putFiles([
    'app/main.php' => '<?php echo "hi";',
    'app/lib.php'  => '<?php /* ... */',
]);

$tar = $container->getArchive('/app'); // raw tar of the directory
```

## Images

```php
$images = $docker->images();

$images->exists('php:8.2-cli');
$images->pull('php:8.2-cli');                 // optional registry auth arg
$images->list();                              // repo tags
$images->remove('php:8.2-cli', force: true);
$images->prune();                             // remove dangling images
```

## Finding & listing containers

```php
$container = $docker->containers()->find('cache');   // by id or name, or null
$all = $docker->containers()->list(all: true);       // array of Container objects
```

## Testing

The test suite runs against a real Docker daemon:

```bash
composer test
```

Network-heavy tests (pulling images in `run()`, and the python/node presets)
are skipped unless `DOCKER_PULL_TESTS=1` is set.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Sharryy](https://github.com/sharryy)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
