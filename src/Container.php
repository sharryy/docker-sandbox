<?php

namespace Sharryy\Docker;

use GuzzleHttp\Client;

class Container
{
    private array $details = [];

    public function __construct(
        private readonly Client $client,
        private readonly string $id,
        private readonly ?string $name = null
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function start(): self
    {
        $this->client->post("/v1.41/containers/{$this->id}/start");

        return $this;
    }

    public function stop(int $timeout = 10): self
    {
        $this->client->post("/v1.41/containers/{$this->id}/stop", [
            'query' => ['t' => $timeout],
        ]);

        return $this;
    }

    public function restart(): self
    {
        $this->client->post("/v1.41/containers/{$this->id}/restart");

        return $this;
    }

    public function kill(string $signal = 'SIGKILL'): self
    {
        $this->client->post("/v1.41/containers/{$this->id}/kill", [
            'query' => ['signal' => $signal],
        ]);

        return $this;
    }

    public function wait(): array
    {
        $response = $this->client->post("/v1.41/containers/{$this->id}/wait");
        ;
        return json_decode($response->getBody()->getContents(), true);
    }

    public function logs(bool $stdout = true, bool $stderr = true, bool $timestamps = false): string
    {
        $response = $this->client->get("/v1.41/containers/{$this->id}/logs", [
            'query' => [
                'stdout' => $stdout,
                'stderr' => $stderr,
                'timestamps' => $timestamps,
            ],
        ]);

        return $this->parseDockerLogs($response->getBody()->getContents());
    }

    public function remove(bool $force = false, bool $removeVolumes = false): void
    {
        $this->client->delete("/v1.41/containers/{$this->id}", [
            'query' => [
                'force' => $force,
                'v' => $removeVolumes,
            ],
        ]);
    }

    public function inspect(bool $refresh = false): array
    {
        if (empty($this->details) || $refresh) {
            $response = $this->client->get("/v1.41/containers/{$this->id}/json");
            $this->details = json_decode($response->getBody()->getContents(), true);
        }

        return $this->details;
    }

    public function status(): string
    {
        // Always refresh status to get current state
        $details = $this->inspect(true);

        return $details['State']['Status'] ?? 'unknown';
    }

    public function isRunning(): bool
    {
        return $this->status() === 'running';
    }

    public function exec(array $command, bool $attachStdout = true, bool $attachStderr = true): string
    {
        $execResponse = $this->client->post("/v1.41/containers/{$this->id}/exec", [
            'json' => [
                'AttachStdout' => $attachStdout,
                'AttachStderr' => $attachStderr,
                'Cmd' => $command,
            ],
        ]);

        $execData = json_decode($execResponse->getBody()->getContents(), true);
        $execId = $execData['Id'];

        $startResponse = $this->client->post("/v1.41/exec/{$execId}/start", [
            'json' => [
                'Detach' => false,
            ],
        ]);

        return $this->parseDockerLogs($startResponse->getBody()->getContents());
    }

    private function parseDockerLogs(string $logs): string
    {
        $output = '';
        $pos = 0;
        $length = strlen($logs);

        while ($pos < $length) {
            if ($length - $pos >= 8) {
                $header = substr($logs, $pos, 8);
                $size = unpack('N', substr($header, 4, 4))[1] ?? 0;
                $pos += 8;

                if ($size > 0 && $pos + $size <= $length) {
                    $output .= substr($logs, $pos, $size);
                    $pos += $size;
                } else {
                    break;
                }
            } else {
                $output .= substr($logs, $pos);
                break;
            }
        }

        return trim($output);
    }
}
