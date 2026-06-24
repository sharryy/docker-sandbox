<?php

namespace Sharryy\Docker\Volumes;

use Sharryy\Docker\DockerClient;

final class Volume
{
    /** @var array<array-key, mixed> */
    private array $details = [];

    public function __construct(
        private readonly DockerClient $client,
        private readonly string $name
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Absolute path where the volume is mounted on the host.
     */
    public function mountpoint(): ?string
    {
        $mountpoint = $this->inspect()['Mountpoint'] ?? null;

        return is_string($mountpoint) ? $mountpoint : null;
    }

    public function driver(): ?string
    {
        $driver = $this->inspect()['Driver'] ?? null;

        return is_string($driver) ? $driver : null;
    }

    public function remove(bool $force = false): void
    {
        $this->client->delete("volumes/{$this->name}", [
            'query' => ['force' => $force],
        ]);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function inspect(bool $refresh = false): array
    {
        if (empty($this->details) || $refresh) {
            $data = json_decode($this->client->get("volumes/{$this->name}")->getBody()->getContents(), true);
            $this->details = is_array($data) ? $data : [];
        }

        return $this->details;
    }
}
