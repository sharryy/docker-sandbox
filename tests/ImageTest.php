<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\Docker;

test('detects whether an image exists locally', function () {
    $docker = new Docker;

    expect($docker->images()->exists('php:8.2-cli'))->toBeTrue()
        ->and($docker->images()->exists('sharryy/definitely-missing:nope'))->toBeFalse();
});

test('can pull, list and remove an image', function () {
    $docker = new Docker;
    $images = $docker->images();

    if ($images->exists('busybox:1.36')) {
        $images->remove('busybox:1.36', force: true);
    }

    expect($images->exists('busybox:1.36'))->toBeFalse();

    $images->pull('busybox:1.36');

    expect($images->exists('busybox:1.36'))->toBeTrue()
        ->and($images->list())->toContain('busybox:1.36');

    $images->remove('busybox:1.36', force: true);

    expect($images->exists('busybox:1.36'))->toBeFalse();
})->skip(! getenv('DOCKER_PULL_TESTS'), 'Set DOCKER_PULL_TESTS=1 to exercise network pulls from Docker Hub.');

test('run auto-pulls a missing image', function () {
    $docker = new Docker;
    $image = 'php:8.3-cli';

    if ($docker->images()->exists($image)) {
        $docker->images()->remove($image, force: true);
    }

    $result = $docker->run($image, '<?php echo "pulled";', 120);

    expect($result->output())->toBe('pulled')
        ->and($docker->images()->exists($image))->toBeTrue();
})->skip(! getenv('DOCKER_PULL_TESTS'), 'Set DOCKER_PULL_TESTS=1 to exercise network pulls in run().');
