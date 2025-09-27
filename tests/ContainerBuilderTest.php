<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\Docker;

test('can build container with fluent configuration', function () {
    $docker = new Docker();

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['php', '-v'])
        ->withMemoryLimit('256m')
        ->withCpuLimit(0.5)
        ->withEnvironment(['APP_ENV' => 'testing', 'DEBUG' => 'true'])
        ->withNetworkMode('bridge')
        ->create();

    expect($container->id())->toBeString()
        ->and($container->id())->not->toBeEmpty();

    // Clean up
    $container->remove(true);
});

test('can run container and get output', function () {
    $docker = new Docker();

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['php', '-r', 'echo "Hello from fluent API!";'])
        ->run();

    $output = $container->logs();

    expect($output)->toBe('Hello from fluent API!');

    // Clean up
    $container->remove();
});

test('can create named container', function () {
    $docker = new Docker();
    $name = 'test-container-' . uniqid();

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withName($name)
        ->withCommand(['php', '-r', 'echo "Named container";'])
        ->create();

    expect($container->name())->toBe($name);

    // Clean up
    $container->remove(true);
});

test('can set environment variables', function () {
    $docker = new Docker();

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withEnvironment(['MY_VAR' => 'test_value'])
        ->withCommand(['php', '-r', 'echo getenv("MY_VAR");'])
        ->run();

    $output = $container->logs();

    expect($output)->toBe('test_value');

    // Clean up
    $container->remove();
});

test('existing run method still works', function () {
    $docker = new Docker();

    $code = '<?php echo "Legacy method works!";';
    $output = $docker->run('php:8.2-cli', $code);

    expect($output)->toBe('Legacy method works!');
});