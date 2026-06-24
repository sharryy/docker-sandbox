<?php

namespace Sharryy\Docker\Networks;

use Psr\Http\Message\ResponseInterface;
use Sharryy\Docker\DockerClient;
use Sharryy\Docker\Exceptions\DockerException;

final readonly class NetworkManager
{
    public function __construct(private DockerClient $client) {}

    /**
     * Create a user-defined network.
     *
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $options  Driver-specific options.
     */
    public function create(
        string $name,
        string $driver = 'bridge',
        bool $internal = false,
        bool $attachable = false,
        array $labels = [],
        array $options = [],
    ): Network {
        $response = $this->client->post('networks/create', [
            'json' => array_filter([
                'Name' => $name,
                'Driver' => $driver,
                'Internal' => $internal,
                'Attachable' => $attachable,
                'Labels' => $labels ?: null,
                'Options' => $options ?: null,
            ], fn ($value) => $value !== null),
        ]);

        // networks/create only returns the Id, so carry the known name through.
        return $this->hydrate($this->decode($response), $name);
    }

    public function find(string $idOrName): ?Network
    {
        try {
            return $this->hydrate($this->decode($this->client->get("networks/{$idOrName}")));
        } catch (DockerException) {
            return null;
        }
    }

    /**
     * List all networks known to the daemon.
     *
     * @return list<Network>
     */
    public function list(): array
    {
        $data = json_decode($this->client->get('networks')->getBody()->getContents(), true);

        $networks = [];

        if (is_array($data)) {
            foreach ($data as $network) {
                if (is_array($network)) {
                    $networks[] = $this->hydrate($network);
                }
            }
        }

        return $networks;
    }

    public function remove(string $idOrName): void
    {
        $this->client->delete("networks/{$idOrName}");
    }

    /**
     * Remove unused networks.
     */
    public function prune(): void
    {
        $this->client->post('networks/prune');
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function hydrate(array $data, ?string $name = null): Network
    {
        return new Network(
            $this->client,
            is_string($data['Id'] ?? null) ? $data['Id'] : '',
            is_string($data['Name'] ?? null) ? $data['Name'] : $name
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $data = json_decode($response->getBody()->getContents(), true);

        return is_array($data) ? $data : [];
    }
}
