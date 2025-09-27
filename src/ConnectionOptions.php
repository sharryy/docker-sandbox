<?php

namespace Sharryy\Docker;

use RuntimeException;
use GuzzleHttp\RequestOptions;

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

    public static function fromSocket(string $socket = '/var/run/docker.sock', string $apiVersion = 'v1.41'): self
    {
        if (! file_exists($socket)) {
            throw new RuntimeException("Docker socket not found at: {$socket}");
        }

        return new self('http://localhost', [
            CURLOPT_UNIX_SOCKET_PATH => $socket
        ], $apiVersion);
    }

    public static function fromTcp(string $host, int $port = 2375, string $apiVersion = 'v1.41'): self
    {
        return new self("http://{$host}:{$port}", [], $apiVersion);
    }

    public static function fromTls(
        string $host,
        int $port = 2376,
        string $apiVersion = 'v1.41',
        string $caCert = null,
        string $clientCert = null,
        string $clientKey = null
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
