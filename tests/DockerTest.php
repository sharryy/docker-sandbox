<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\ConnectionOptions;
use Sharryy\Docker\Docker;
use Sharryy\Docker\Exceptions\ProcessTimeoutException;

test('can run simple PHP code in container', function () {
    $docker = new Docker;

    $result = $docker->run('php:8.2-cli', '<?php echo "Hello from Docker!";');

    expect($result->output())->toBe('Hello from Docker!')
        ->and($result->successful())->toBeTrue()
        ->and($result->exitCode())->toBe(0)
        ->and($result->duration())->toBeGreaterThan(0.0);
});

test('can use custom connection options', function () {
    $connectionOptions = ConnectionOptions::fromSocket();

    $docker = new Docker($connectionOptions);

    $result = $docker->run('php:8.2-cli', '<?php echo "Connected via socket!";');

    expect($result->output())->toBe('Connected via socket!');
});

test('can run PHP code with calculations', function () {
    $docker = new Docker;

    $result = $docker->run('php:8.2-cli', '<?php echo 2 + 2;');

    expect($result->output())->toBe('4');
});

test('reports a non-zero exit code without throwing', function () {
    $docker = new Docker;

    $result = $docker->run('php:8.2-cli', '<?php echo "bye"; exit(5);');

    expect($result->exitCode())->toBe(5)
        ->and($result->failed())->toBeTrue()
        ->and($result->output())->toBe('bye');
});

test('captures error output on stderr separately from stdout', function () {
    $docker = new Docker;

    $code = '<?php fwrite(STDERR, "to-stderr"); echo "to-stdout";';

    $result = $docker->run('php:8.2-cli', $code);

    expect($result->output())->toBe('to-stdout')
        ->and($result->errorOutput())->toBe('to-stderr');
});

test('terminates code that exceeds the timeout', function () {
    $docker = new Docker;

    $code = '<?php while (true) {}';

    expect(fn () => $docker->run('php:8.2-cli', $code, timeout: 2))
        ->toThrow(ProcessTimeoutException::class);
});
