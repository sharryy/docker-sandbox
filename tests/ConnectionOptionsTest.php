<?php

namespace Sharryy\Docker\Tests;

use Sharryy\Docker\ConnectionOptions;

test('can create connection from socket', function () {
    $options = ConnectionOptions::fromSocket('/var/run/docker.sock');

    $config = $options->getGuzzleConfig();

    expect($config['base_uri'])->toBe('http://localhost')
        ->and($config['curl'][CURLOPT_UNIX_SOCKET_PATH])->toBe('/var/run/docker.sock')
        ->and($options->getApiVersion())->toBe('v1.41');
});

test('can create connection with custom API version', function () {
    $options = ConnectionOptions::fromSocket('/var/run/docker.sock', 'v1.42');

    expect($options->getApiVersion())->toBe('v1.42');
});

test('can change API version with fluent method', function () {
    $options = ConnectionOptions::fromSocket('/var/run/docker.sock')
        ->withApiVersion('v1.40');

    expect($options->getApiVersion())->toBe('v1.40');
});

test('throws exception for non-existent socket', function () {
    ConnectionOptions::fromSocket('/non/existent/socket.sock');
})->throws(\RuntimeException::class, 'Docker socket not found');
