<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\Docker;
use Sharryy\Docker\Volumes\Volume;

test('can create, find, list and remove a volume', function () {
    $docker = new Docker;
    $volumes = $docker->volumes();

    $volume = $volumes->create('sandbox-test-vol');

    expect($volume)->toBeInstanceOf(Volume::class)
        ->and($volume->name())->toBe('sandbox-test-vol')
        ->and($volume->mountpoint())->not->toBeEmpty()
        ->and($volume->driver())->toBe('local');

    expect($volumes->exists('sandbox-test-vol'))->toBeTrue()
        ->and($volumes->find('sandbox-test-vol'))->toBeInstanceOf(Volume::class)
        ->and(array_map(fn ($v) => $v->name(), $volumes->list()))->toContain('sandbox-test-vol');

    $volume->remove(force: true);

    expect($volumes->exists('sandbox-test-vol'))->toBeFalse();
});

test('a volume persists data across containers', function () {
    $docker = new Docker;
    $volume = $docker->volumes()->create('sandbox-test-persist');

    $writer = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['sh', '-c', 'echo persisted > /data/file.txt'])
        ->withVolume('sandbox-test-persist', '/data')
        ->create();
    $writer->start();
    $writer->wait();
    $writer->remove(true);

    $reader = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['cat', '/data/file.txt'])
        ->withVolume('sandbox-test-persist', '/data')
        ->create();
    $reader->start();
    $reader->wait();

    expect($reader->result()->output())->toBe('persisted');

    $reader->remove(true);
    $volume->remove(force: true);
});

test('returns null when finding a missing volume', function () {
    $docker = new Docker;

    expect($docker->volumes()->find('definitely-missing-volume'))->toBeNull();
});
