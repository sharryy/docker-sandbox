<?php

namespace Sharryy\Docker;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class DockerClient
{
    public function __construct(protected Client $client, protected string $apiVersion = 'v1.41')
    {
    }

    public function get(string $path, array $options = []): ResponseInterface
    {
        return $this->client->get($this->buildPath($path), $options);
    }

    public function post(string $path, array $options = []): ResponseInterface
    {
        return $this->client->post($this->buildPath($path), $options);
    }

    public function put(string $path, array $options = []): ResponseInterface
    {
        return $this->client->put($this->buildPath($path), $options);
    }

    public function delete(string $path, array $options = []): ResponseInterface
    {
        return $this->client->delete($this->buildPath($path), $options);
    }

    public function patch(string $path, array $options = []): ResponseInterface
    {
        return $this->client->patch($this->buildPath($path), $options);
    }

    private function buildPath(string $path): string
    {
        $path = ltrim($path, '/');

        return "/{$this->apiVersion}/{$path}";
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }
}
