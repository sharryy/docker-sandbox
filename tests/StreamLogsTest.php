<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\Docker;

test('can follow container output as a stream', function () {
    $docker = new Docker;

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['sh', '-c', 'echo first; sleep 1; echo second'])
        ->create();

    $container->start();

    $chunks = [];
    $container->streamLogs(function (string $text, string $stream) use (&$chunks) {
        $chunks[] = $text;
    });

    $combined = implode('', $chunks);

    expect($combined)->toContain('first')
        ->and($combined)->toContain('second');

    $container->remove();
});

test('routes stderr to the stderr stream while following', function () {
    $docker = new Docker;

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['php', '-r', 'fwrite(STDERR, "err-line"); echo "out-line";'])
        ->create();

    $container->start();

    $streams = ['stdout' => '', 'stderr' => ''];
    $container->streamLogs(function (string $text, string $stream) use (&$streams) {
        $streams[$stream] .= $text;
    });

    expect($streams['stdout'])->toContain('out-line')
        ->and($streams['stderr'])->toContain('err-line');

    $container->remove();
});
