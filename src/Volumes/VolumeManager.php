<?php

namespace Sharryy\Docker\Volumes;

use Sharryy\Docker\DockerClient;
use Sharryy\Docker\Exceptions\DockerException;

final readonly class VolumeManager
{
    public function __construct(private DockerClient $client) {}

    /**
     * Create a named volume.
     *
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $driverOpts  Driver-specific options.
     */
    public function create(
        string $name,
        string $driver = 'local',
        array $labels = [],
        array $driverOpts = [],
    ): Volume {
        $this->client->post('volumes/create', [
            'json' => array_filter([
                'Name' => $name,
                'Driver' => $driver,
                'Labels' => $labels ?: null,
                'DriverOpts' => $driverOpts ?: null,
            ], fn ($value) => $value !== null),
        ]);

        return new Volume($this->client, $name);
    }

    public function find(string $name): ?Volume
    {
        try {
            $this->client->get("volumes/{$name}");

            return new Volume($this->client, $name);
        } catch (DockerException) {
            return null;
        }
    }

    public function exists(string $name): bool
    {
        return $this->find($name) instanceof Volume;
    }

    /**
     * List all volumes known to the daemon.
     *
     * @return list<Volume>
     */
    public function list(): array
    {
        $data = json_decode($this->client->get('volumes')->getBody()->getContents(), true);

        // The volumes endpoint wraps the collection in a "Volumes" key.
        $volumes = is_array($data) && is_array($data['Volumes'] ?? null) ? $data['Volumes'] : [];

        $result = [];

        foreach ($volumes as $volume) {
            if (is_array($volume) && isset($volume['Name']) && is_string($volume['Name'])) {
                $result[] = new Volume($this->client, $volume['Name']);
            }
        }

        return $result;
    }

    public function remove(string $name, bool $force = false): void
    {
        $this->client->delete("volumes/{$name}", [
            'query' => ['force' => $force],
        ]);
    }

    /**
     * Remove unused volumes.
     */
    public function prune(): void
    {
        $this->client->post('volumes/prune');
    }
}
