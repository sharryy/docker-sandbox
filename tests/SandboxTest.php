<?php

namespace Sharryy\Docker\Tests;

use InvalidArgumentException;
use Sharryy\Docker\Preset;
use Sharryy\Docker\Sandbox;

afterEach(fn () => Sandbox::flushCustomPresets());

test('runs code through the built-in php preset', function () {
    $result = (new Sandbox)->php('<?php echo 21 * 2;');

    expect($result->output())->toBe('42')
        ->and($result->successful())->toBeTrue();
});

test('can register and use a custom preset', function () {
    Sandbox::register('php-strict', new Preset('php:8.2-cli', 'script.php', 'php'));

    $result = (new Sandbox)->run('php-strict', '<?php echo "custom!";');

    expect($result->output())->toBe('custom!');
});

test('a custom preset can override a built-in', function () {
    Sandbox::register('php', new Preset('php:8.2-cli', 'over.php', 'php'));

    $result = (new Sandbox)->php('<?php echo "overridden";');

    expect($result->output())->toBe('overridden');
});

test('throws for an unknown preset', function () {
    expect(fn () => (new Sandbox)->run('cobol', 'IDENTIFICATION DIVISION.'))
        ->toThrow(InvalidArgumentException::class);
});

test('runs python and node through built-in presets', function () {
    $sandbox = new Sandbox;

    expect($sandbox->python('print(6 * 7)')->output())->toBe('42')
        ->and($sandbox->node('console.log(6 * 7)')->output())->toBe('42');
})->skip(! getenv('DOCKER_PULL_TESTS'), 'Set DOCKER_PULL_TESTS=1 to pull the python/node images.');
