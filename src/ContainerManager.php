<?php

namespace Sharryy\Docker;

use GuzzleHttp\Client;

readonly class ContainerManager
{
    public function __construct(private Client $client)
    {
    }

    public function from(string $image): ContainerBuilder
    {
        return new ContainerBuilder($this->client, $image);
    }

    public function find(string $idOrName): ?Container
    {
        try {
            $response = $this->client->get("/v1.41/containers/{$idOrName}/json");
            $data = json_decode($response->getBody()->getContents(), true);

            return new Container(
                $this->client,
                $data['Id'],
                $data['Name'] ? ltrim($data['Name'], '/') : null
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    public function list(bool $all = false): array
    {
        $response = $this->client->get('/v1.41/containers/json', [
            'query' => ['all' => $all],
        ]);

        $containers = json_decode($response->getBody()->getContents(), true);

        return array_map(
            fn($data) => new Container(
                $this->client,
                $data['Id'],
                isset($data['Names'][0]) ? ltrim($data['Names'][0], '/') : null
            ),
            $containers
        );
    }

    public function run(string $image, string $code, int $timeout = 30): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'docker-php-');

        file_put_contents($tempFile, $code);

        try {
            $container = $this->from($image)
                ->withCommand(['php', '/code.php'])
                ->withVolume($tempFile, '/code.php', 'ro')
                ->withNetworkMode('none')
                ->withMemoryLimit('128m')
                ->run();

            $output = $container->logs();
            $container->remove();

            return $output;
        } finally {
            unlink($tempFile);
        }
    }
}
