<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\Docker;
use Sharryy\Docker\Support\Tar;

test('can upload multiple files and read them back out', function () {
    $docker = new Docker;

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['php', '-v'])
        ->create();

    $container->putFiles([
        'one.txt' => 'AAA',
        'two.txt' => 'BBB',
    ]);

    expect($container->getArchive('/one.txt'))->toContain('AAA')
        ->and($container->getArchive('/two.txt'))->toContain('BBB');

    $container->remove(true);
});

test('uploaded files are runnable by the container', function () {
    $docker = new Docker;

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['php', '/app.php'])
        ->create();

    $container->putArchive('/', Tar::single('app.php', '<?php echo 6 * 7;'));
    $container->start();
    $container->wait(15);

    expect($container->result()->output())->toBe('42');

    $container->remove(true);
});
