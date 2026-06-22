<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\Docker;

test('sandboxed code runs as a non-root user', function () {
    $docker = new Docker;

    $output = $docker->run('php:8.2-cli', '<?php echo trim(shell_exec("id -u"));');

    expect($output)->toBe('65534');
});

test('sandboxed code has no network access', function () {
    $docker = new Docker;

    $code = '<?php $c = @fsockopen("1.1.1.1", 80, $e, $s, 2); echo $c === false ? "blocked" : "open";';

    $output = $docker->run('php:8.2-cli', $code);

    expect($output)->toBe('blocked');
});

test('sandboxed code can still write to the tmpfs scratch area', function () {
    $docker = new Docker;

    $code = '<?php file_put_contents("/tmp/x", "ok"); echo file_get_contents("/tmp/x");';

    $output = $docker->run('php:8.2-cli', $code);

    expect($output)->toBe('ok');
});

test('builder applies security hardening to the container config', function () {
    $docker = new Docker;

    $container = $docker->containers()
        ->from('php:8.2-cli')
        ->withCommand(['php', '-v'])
        ->withUser('65534:65534')
        ->asReadOnly()
        ->withTmpfs('/tmp')
        ->withPidsLimit(64)
        ->dropCapabilities()
        ->withoutNewPrivileges()
        ->withMemoryLimit('128m')
        ->withoutSwap()
        ->withUlimit('nofile', 256)
        ->create();

    $details = $container->inspect();
    $host = $details['HostConfig'];

    expect($host['ReadonlyRootfs'])->toBeTrue()
        ->and($host['PidsLimit'])->toBe(64)
        ->and($host['CapDrop'])->toContain('ALL')
        ->and($host['SecurityOpt'])->toContain('no-new-privileges')
        ->and($host['Memory'])->toBe(134217728)
        ->and($host['MemorySwap'])->toBe(134217728)
        ->and($host['Tmpfs'])->toHaveKey('/tmp')
        ->and($host['Ulimits'][0])->toMatchArray(['Name' => 'nofile', 'Soft' => 256, 'Hard' => 256])
        ->and($details['Config']['User'])->toBe('65534:65534');

    $container->remove(true);
});
