<?php

namespace Sharryy\Docker;

use GuzzleHttp\Client;
use Sharryy\Docker\Containers\ContainerManager;
use Sharryy\Docker\Images\ImageManager;
use Sharryy\Docker\Networks\NetworkManager;
use Sharryy\Docker\Volumes\VolumeManager;

final class Docker
{
    private DockerClient $client;

    public function __construct(private ?ConnectionOptions $options = null)
    {
        $this->options = $options ?? ConnectionOptions::fromSocket();

        $httpClient = new Client($this->options->getGuzzleConfig());

        $this->client = new DockerClient($httpClient, $this->options->getApiVersion());
    }

    public function containers(): ContainerManager
    {
        return new ContainerManager($this->client);
    }

    public function images(): ImageManager
    {
        return new ImageManager($this->client);
    }

    public function networks(): NetworkManager
    {
        return new NetworkManager($this->client);
    }

    public function volumes(): VolumeManager
    {
        return new VolumeManager($this->client);
    }

    public function run(string $image, string $code, int $timeout = 30): ExecutionResult
    {
        return $this->containers()->run($image, $code, $timeout);
    }
}
