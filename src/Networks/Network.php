<?php

namespace Sharryy\Docker\Networks;

use Sharryy\Docker\DockerClient;

final class Network
{
    /** @var array<array-key, mixed> */
    private array $details = [];

    public function __construct(
        private readonly DockerClient $client,
        private readonly string $id,
        private readonly ?string $name = null
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * Attach a container to this network.
     *
     * @param  list<string>  $aliases  Optional network-scoped aliases for the container.
     */
    public function connect(string $container, array $aliases = []): self
    {
        $body = ['Container' => $container];

        if ($aliases !== []) {
            $body['EndpointConfig'] = ['Aliases' => $aliases];
        }

        $this->client->post("networks/{$this->id}/connect", [
            'json' => $body,
        ]);

        return $this;
    }

    /**
     * Detach a container from this network.
     */
    public function disconnect(string $container, bool $force = false): self
    {
        $this->client->post("networks/{$this->id}/disconnect", [
            'json' => ['Container' => $container, 'Force' => $force],
        ]);

        return $this;
    }

    public function remove(): void
    {
        $this->client->delete("networks/{$this->id}");
    }

    /**
     * @return array<array-key, mixed>
     */
    public function inspect(bool $refresh = false): array
    {
        if (empty($this->details) || $refresh) {
            $data = json_decode($this->client->get("networks/{$this->id}")->getBody()->getContents(), true);
            $this->details = is_array($data) ? $data : [];
        }

        return $this->details;
    }
}
