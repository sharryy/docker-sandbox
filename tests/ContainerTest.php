<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\Docker;

test('can get container status', function () {
    $docker = new Docker;

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['php', '-r', 'sleep(1);'])
        ->create();

    expect($container->status())->toBe('created');

    $container->start();
    expect($container->isRunning())->toBeTrue();

    $container->wait();
    expect($container->isRunning())->toBeFalse();

    // Clean up
    $container->remove();
});

test('can execute command in running container', function () {
    $docker = new Docker;

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['php', '-r', 'while(true) { sleep(1); }'])
        ->create();

    $container->start();

    $output = $container->exec(['php', '-r', 'echo "Exec works!";']);
    expect($output)->toBe('Exec works!');

    // Clean up
    $container->stop()->remove();
});

test('can find container by id', function () {
    $docker = new Docker;
    $name = 'findable-container-'.uniqid();

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withName($name)
        ->withCommand(['php', '-r', 'echo "test";'])
        ->create();

    $foundContainer = $docker->containers()->find($container->id());

    expect($foundContainer)->not->toBeNull()
        ->and($foundContainer->id())->toBe($container->id());

    // Clean up
    $container->remove(true);
});

test('can list containers', function () {
    $docker = new Docker;

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['php', '-r', 'sleep(1);'])
        ->create();

    $container->start();

    $containers = $docker->containers()->list();
    $containerIds = array_map(fn ($c) => $c->id(), $containers);

    expect($containerIds)->toContain($container->id());

    // Clean up
    $container->stop()->remove();
});
