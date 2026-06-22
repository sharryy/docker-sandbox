<?php

namespace Sharryy\Docker;

use GuzzleHttp\RequestOptions;
use Sharryy\Docker\Exceptions\ConnectionException;

class ConnectionOptions
{
    private string $baseUri;

    private array $curlOptions;

    private string $apiVersion;

    private function __construct(
        string $baseUri = 'http://localhost',
        array $curlOptions = [],
        string $apiVersion = 'v1.41'
    ) {
        $this->baseUri = $baseUri;
        $this->curlOptions = $curlOptions;
        $this->apiVersion = $apiVersion;
    }

    public static function fromSocket(?string $socket = null, string $apiVersion = 'v1.41'): self
    {
        if ($socket === null) {
            $socket = self::discoverSocket();
        } elseif (! file_exists($socket)) {
            throw new ConnectionException("Docker socket not found at: {$socket}");
        }

        return new self('http://localhost', [
            CURLOPT_UNIX_SOCKET_PATH => $socket,
        ], $apiVersion);
    }

    /**
     * Locate a Docker socket without the caller having to know where it lives.
     *
     * Honours DOCKER_HOST first, then falls back to the standard daemon path
     * and the common Colima / Docker Desktop locations on macOS.
     */
    private static function discoverSocket(): string
    {
        $candidates = [];

        $dockerHost = getenv('DOCKER_HOST');
        if (is_string($dockerHost) && str_starts_with($dockerHost, 'unix://')) {
            $candidates[] = substr($dockerHost, strlen('unix://'));
        }

        $candidates[] = '/var/run/docker.sock';

        $home = getenv('HOME');
        if (is_string($home) && $home !== '') {
            $candidates[] = "{$home}/.colima/default/docker.sock";
            $candidates[] = "{$home}/.docker/run/docker.sock";
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && file_exists($candidate)) {
                return $candidate;
            }
        }

        throw new ConnectionException(
            'Could not find a Docker socket (tried: '.implode(', ', $candidates).'). '
            .'Set DOCKER_HOST or pass an explicit path to ConnectionOptions::fromSocket().'
        );
    }

    public static function fromTcp(string $host, int $port = 2375, string $apiVersion = 'v1.41'): self
    {
        return new self("http://{$host}:{$port}", [], $apiVersion);
    }

    public static function fromTls(
        string $host,
        int $port = 2376,
        string $apiVersion = 'v1.41',
        ?string $caCert = null,
        ?string $clientCert = null,
        ?string $clientKey = null
    ): self {
        $curlOptions = [];

        if ($caCert) {
            $curlOptions[CURLOPT_CAINFO] = $caCert;
        }

        if ($clientCert) {
            $curlOptions[CURLOPT_SSLCERT] = $clientCert;
        }

        if ($clientKey) {
            $curlOptions[CURLOPT_SSLKEY] = $clientKey;
        }

        return new self("https://{$host}:{$port}", $curlOptions, $apiVersion);
    }

    public function withApiVersion(string $version): self
    {
        return new self($this->baseUri, $this->curlOptions, $version);
    }

    public function getGuzzleConfig(): array
    {
        $config = [
            'base_uri' => $this->baseUri,
            RequestOptions::HEADERS => [],
        ];

        if (! empty($this->curlOptions)) {
            $config['curl'] = $this->curlOptions;
        }

        return $config;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }
}
