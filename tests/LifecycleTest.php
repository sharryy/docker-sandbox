<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\Docker;

function longRunningContainer(Docker $docker)
{
    return $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['php', '-r', 'while (true) { sleep(1); }'])
        ->create();
}

test('can pause and unpause a container', function () {
    $docker = new Docker;
    $container = longRunningContainer($docker);
    $container->start();

    $container->pause();
    expect($container->status())->toBe('paused');

    $container->unpause();
    expect($container->isRunning())->toBeTrue();

    $container->stop()->remove();
});

test('can rename a container', function () {
    $docker = new Docker;
    $name = 'renamed-'.uniqid();
    $container = longRunningContainer($docker);
    $container->start();

    $container->rename($name);

    expect($docker->containers()->find($name))->not->toBeNull();

    $container->stop()->remove();
});

test('exposes container resource stats', function () {
    $docker = new Docker;
    $container = longRunningContainer($docker);
    $container->start();

    $stats = $container->stats();

    expect($stats)->toHaveKey('memory_stats')
        ->and($stats)->toHaveKey('cpu_stats');

    $container->stop()->remove();
});
