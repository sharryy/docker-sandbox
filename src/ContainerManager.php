<?php

namespace Sharryy\Docker;

use Sharryy\Docker\Exceptions\DockerException;
use Sharryy\Docker\Exceptions\ProcessTimeoutException;

final readonly class ContainerManager
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

    public function run(string $image, string $code, int $timeout = 30): ExecutionResult
    {
        return $this->runPreset(new Preset($image, 'main.php', 'php'), $code, $timeout);
    }

    public function runPreset(Preset $preset, string $code, int $timeout = 30): ExecutionResult
    {
        $images = new ImageManager($this->client);

        if (! $images->exists($preset->image)) {
            $images->pull($preset->image);
        }

        $container = $this->hardened($preset, $code)->create();

        $start = microtime(true);
        $container->start();

        try {
            $container->wait($timeout);
        } catch (ProcessTimeoutException $e) {
            $container->kill();
            $container->remove(true);

            throw $e;
        }

        $result = $container->result(microtime(true) - $start);
        $container->remove(true);

        return $result;
    }

    /**
     * Build a locked-down container for running untrusted code: no network,
     * non-root, read-only rootfs with a small writable tmpfs, no capabilities,
     * no privilege escalation, no swap, and a process-count limit.
     *
     * The code is delivered through an env var and written to the tmpfs at
     * runtime — a read-only rootfs refuses archive uploads, and this works the
     * same on local, VM-backed (Colima/Lima) and remote daemons.
     */
    private function hardened(Preset $preset, string $code): ContainerBuilder
    {
        $path = '/tmp/'.$preset->filename;
        $command = sprintf('printf "%%s" "$SANDBOX_CODE" | base64 -d > %s && exec %s %s', $path, $preset->interpreter, $path);

        return $this->from($preset->image)
            ->withCommand(['sh', '-c', $command])
            ->withEnvironment(['SANDBOX_CODE' => base64_encode($code)])
            ->withNetworkMode('none')
            ->withMemoryLimit('128m')
            ->withoutSwap()
            ->withPidsLimit(128)
            ->withUser('65534:65534')
            ->asReadOnly()
            ->withTmpfs('/tmp', 'rw,size=16m')
            ->dropCapabilities()
            ->withoutNewPrivileges();
    }
}
