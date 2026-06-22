<?php

namespace Sharryy\Docker;

use Sharryy\Docker\Exceptions\DockerException;
use Sharryy\Docker\Exceptions\ProcessTimeoutException;
use Sharryy\Docker\Support\Tar;

readonly class ContainerManager
{
    public function __construct(private DockerClient $client) {}

    public function from(string $image): ContainerBuilder
    {
        return new ContainerBuilder($this->client, $image);
    }

    public function find(string $idOrName): ?Container
    {
        try {
            $response = $this->client->get("containers/{$idOrName}/json");
            $data = json_decode($response->getBody()->getContents(), true);

            return new Container(
                $this->client,
                $data['Id'],
                $data['Name'] ? ltrim($data['Name'], '/') : null
            );
        } catch (DockerException $e) {
            return null;
        }
    }

    public function list(bool $all = false): array
    {
        $response = $this->client->get('containers/json', [
            'query' => ['all' => $all],
        ]);

        $containers = json_decode($response->getBody()->getContents(), true);

        return array_map(
            fn ($data) => new Container(
                $this->client,
                $data['Id'],
                isset($data['Names'][0]) ? ltrim($data['Names'][0], '/') : null
            ),
            $containers
        );
    }

    public function run(string $image, string $code, int $timeout = 30): string
    {
        // Inject the code through the Docker API rather than a host bind mount,
        // so this works the same on local sockets, VM-backed daemons
        // (Colima/Lima) and remote TCP/TLS daemons.
        $container = $this->from($image)
            ->withCommand(['php', '/code.php'])
            ->withNetworkMode('none')
            ->withMemoryLimit('128m')
            ->create();

        $container->putArchive('/', Tar::single('code.php', $code));
        $container->start();

        try {
            $container->wait($timeout);
        } catch (ProcessTimeoutException $e) {
            $container->kill();
            $container->remove(true);

            throw $e;
        }

        $output = $container->logs();
        $container->remove();

        return $output;
    }
}
