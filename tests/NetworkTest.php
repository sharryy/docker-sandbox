<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\Docker;
use Sharryy\Docker\Networks\Network;

test('can create, find, list and remove a network', function () {
    $docker = new Docker;
    $networks = $docker->networks();

    $network = $networks->create('sandbox-test-net', internal: true);

    expect($network)->toBeInstanceOf(Network::class)
        ->and($network->id())->not->toBeEmpty()
        ->and($network->name())->toBe('sandbox-test-net');

    expect($networks->find('sandbox-test-net'))->toBeInstanceOf(Network::class)
        ->and(array_map(fn ($n) => $n->name(), $networks->list()))->toContain('sandbox-test-net');

    expect($network->inspect()['Internal'])->toBeTrue();

    $network->remove();

    expect($networks->find('sandbox-test-net'))->toBeNull();
});

test('can connect and disconnect a container', function () {
    $docker = new Docker;
    $network = $docker->networks()->create('sandbox-test-attach');

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['sleep', '30'])
        ->withNetworkMode('bridge')
        ->create();
    $container->start();

    $network->connect($container->id());

    $containers = $network->inspect(true)['Containers'] ?? [];
    expect(array_keys($containers))->toContain($container->id());

    $network->disconnect($container->id(), force: true);

    expect(array_keys($network->inspect(true)['Containers'] ?? []))
        ->not->toContain($container->id());

    $container->kill();
    $container->remove(true);
    $network->remove();
});

test('returns null when finding a missing network', function () {
    $docker = new Docker;

    expect($docker->networks()->find('definitely-missing-network'))->toBeNull();
});
