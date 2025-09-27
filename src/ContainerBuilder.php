<?php

namespace Sharryy\Docker;

use stdClass;
use GuzzleHttp\Client;

class ContainerBuilder
{
    private array $command = [];
    private array $environment = [];
    private array $volumes = [];
    private array $ports = [];
    private ?string $name = null;
    private ?string $networkMode = 'none';
    private ?int $memory = null;
    private ?float $cpuLimit = null;
    private bool $autoRemove = false;
    private array $labels = [];

    public function __construct(private readonly Client $client, private readonly string $image)
    {
    }

    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function withCommand(array $command): self
    {
        $this->command = $command;

        return $this;
    }

    public function withEnvironment(array $environment): self
    {
        $this->environment = array_merge($this->environment, $environment);

        return $this;
    }

    public function withVolume(string $hostPath, string $containerPath, string $mode = 'rw'): self
    {
        $this->volumes[] = "{$hostPath}:{$containerPath}:{$mode}";

        return $this;
    }

    public function withPort(int $hostPort, int $containerPort): self
    {
        $this->ports["{$containerPort}/tcp"] = [
            ['HostPort' => (string) $hostPort]
        ];

        return $this;
    }

    public function withMemoryLimit(string $memory): self
    {
        // Convert memory string to bytes (e.g., '256m' to bytes)
        $value = (int) $memory;
        $unit = strtolower(substr($memory, -1));

        $this->memory = match ($unit) {
            'k' => $value * 1024,
            'm' => $value * 1024 * 1024,
            'g' => $value * 1024 * 1024 * 1024,
            default => $value
        };

        return $this;
    }

    public function withCpuLimit(float $cpus): self
    {
        $this->cpuLimit = $cpus;

        return $this;
    }

    public function withNetworkMode(string $mode): self
    {
        $this->networkMode = $mode;

        return $this;
    }

    public function withAutoRemove(bool $autoRemove = true): self
    {
        $this->autoRemove = $autoRemove;

        return $this;
    }

    public function withLabel(string $key, string $value): self
    {
        $this->labels[$key] = $value;

        return $this;
    }

    private function buildConfig(): array
    {
        $config = [
            'Image' => $this->image,
            'HostConfig' => [
                'NetworkMode' => $this->networkMode,
                'AutoRemove' => $this->autoRemove,
            ],
        ];

        if (! empty($this->name)) {
            $config['name'] = $this->name;
        }

        if (! empty($this->command)) {
            $config['Cmd'] = $this->command;
        }

        if (! empty($this->environment)) {
            $config['Env'] = array_map(
                fn($key, $value) => "{$key}={$value}",
                array_keys($this->environment),
                array_values($this->environment)
            );
        }

        if (! empty($this->volumes)) {
            $config['HostConfig']['Binds'] = $this->volumes;
        }

        if (! empty($this->ports)) {
            $config['ExposedPorts'] = array_combine(
                array_keys($this->ports),
                array_fill(0, count($this->ports), new stdClass())
            );
            $config['HostConfig']['PortBindings'] = $this->ports;
        }

        if ($this->memory !== null) {
            $config['HostConfig']['Memory'] = $this->memory;
        }

        if ($this->cpuLimit !== null) {
            // Docker expects CPU limit in nanocores (1 CPU = 1e9 nanocores)
            $config['HostConfig']['NanoCpus'] = (int) ($this->cpuLimit * 1e9);
        }

        if (! empty($this->labels)) {
            $config['Labels'] = $this->labels;
        }

        return $config;
    }

    public function create(): Container
    {
        $response = $this->client->post('/v1.41/containers/create', [
            'json' => $this->buildConfig(),
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return new Container($this->client, $data['Id'], $this->name);
    }

    public function run(): Container
    {
        $container = $this->create();
        $container->start();
        $container->wait();

        return $container;
    }
}
