<?php

namespace Sharryy\Docker;

use GuzzleHttp\Client;

class Docker
{
    private Client $client;
    private ConnectionOptions $options;

    public function __construct(?ConnectionOptions $options = null)
    {
        $this->options = $options ?? ConnectionOptions::fromSocket();

        $this->client = new Client($this->options->getGuzzleConfig());
    }

    public function containers(): ContainerManager
    {
        return new ContainerManager($this->client);
    }

    public function run(string $image, string $code): string
    {
        return $this->containers()->run($image, $code);
    }
}
