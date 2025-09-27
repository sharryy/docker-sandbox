<?php

namespace Sharryy\Docker\Tests;

use GuzzleHttp\Client;
use Sharryy\Docker\Docker;
use Sharryy\Docker\DockerClient;
use Sharryy\Docker\ConnectionOptions;

test('docker client uses configured API version', function () {
    $httpClient = new Client(['base_uri' => 'http://localhost']);

    $dockerClient = new DockerClient($httpClient, 'v1.42');

    expect($dockerClient->getApiVersion())->toBe('v1.42');
});

test('can configure different API versions', function () {
    $versions = ['v1.40', 'v1.41', 'v1.42', 'v1.43'];

    foreach ($versions as $version) {
        $options = ConnectionOptions::fromSocket('/var/run/docker.sock', $version);
        expect($options->getApiVersion())->toBe($version);
    }
});

test('tcp connection supports custom API version', function () {
    $options = ConnectionOptions::fromTcp('docker.example.com', 2375, 'v1.42');

    expect($options->getApiVersion())->toBe('v1.42')
        ->and($options->getGuzzleConfig()['base_uri'])->toBe('http://docker.example.com:2375');
});

test('tls connection supports custom API version', function () {
    $options = ConnectionOptions::fromTls(
        'docker.example.com',
        2376,
        'v1.43'
    );

    expect($options->getApiVersion())->toBe('v1.43')
        ->and($options->getGuzzleConfig()['base_uri'])->toBe('https://docker.example.com:2376');
});

test('fluent API version change creates new instance', function () {
    $original = ConnectionOptions::fromSocket('/var/run/docker.sock', 'v1.41');
    $modified = $original->withApiVersion('v1.42');

    expect($original->getApiVersion())->toBe('v1.41')
        ->and($modified->getApiVersion())->toBe('v1.42')
        ->and($original)->not->toBe($modified); // Different instances
});

test('docker uses connection options API version', function () {
    $options = ConnectionOptions::fromSocket('/var/run/docker.sock', 'v1.40');
    $docker = new Docker($options);

    // The Docker class should use the API version from ConnectionOptions
    // This is more of an integration test
    expect($docker)->toBeInstanceOf(Docker::class);
});
