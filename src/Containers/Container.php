<?php

namespace Sharryy\Docker\Containers;

use Psr\Http\Message\ResponseInterface;
use Sharryy\Docker\DockerClient;
use Sharryy\Docker\Exceptions\ConnectionException;
use Sharryy\Docker\Exceptions\ProcessTimeoutException;
use Sharryy\Docker\ExecutionResult;
use Sharryy\Docker\Support\StreamParser;
use Sharryy\Docker\Support\Tar;

final class Container
{
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

    public function start(): self
    {
        $this->client->post("containers/{$this->id}/start");

        return $this;
    }

    public function stop(int $timeout = 10): self
    {
        $this->client->post("containers/{$this->id}/stop", [
            'query' => ['t' => $timeout],
        ]);

        return $this;
    }

    public function restart(): self
    {
        $this->client->post("containers/{$this->id}/restart");

        return $this;
    }

    public function kill(string $signal = 'SIGKILL'): self
    {
        $this->client->post("containers/{$this->id}/kill", [
            'query' => ['signal' => $signal],
        ]);

        return $this;
    }

    public function pause(): self
    {
        $this->client->post("containers/{$this->id}/pause");

        return $this;
    }

    public function unpause(): self
    {
        $this->client->post("containers/{$this->id}/unpause");

        return $this;
    }

    public function rename(string $name): self
    {
        $this->client->post("containers/{$this->id}/rename", [
            'query' => ['name' => $name],
        ]);

        return $this;
    }

    /**
     * Fetch a one-shot resource usage snapshot (CPU, memory, network, …).
     *
     * @return array<array-key, mixed>
     */
    public function stats(): array
    {
        return $this->decode($this->client->get("containers/{$this->id}/stats", [
            'query' => ['stream' => false],
        ]));
    }

    public function wait(?int $timeout = null): array
    {
        $options = [];

        if ($timeout !== null) {
            $options['timeout'] = $timeout;
        }

        try {
            $response = $this->client->post("containers/{$this->id}/wait", $options);
        } catch (ConnectionException $e) {
            if ($timeout !== null && $this->isTimeout($e)) {
                throw new ProcessTimeoutException(
                    "Container {$this->id} did not finish within {$timeout} seconds.",
                    0,
                    $e
                );
            }

            throw $e;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Upload a tar archive into the container's filesystem.
     *
     * @param  string  $path  Directory inside the container to extract into.
     * @param  string  $tar  Raw tar archive contents.
     */
    public function putArchive(string $path, string $tar): self
    {
        $this->client->put("containers/{$this->id}/archive", [
            'query' => ['path' => $path],
            'headers' => ['Content-Type' => 'application/x-tar'],
            'body' => $tar,
        ]);

        return $this;
    }

    /**
     * Upload a set of files into the container.
     *
     * @param  array<string, string>  $files  map of path => contents
     */
    public function putFiles(array $files, string $path = '/'): self
    {
        return $this->putArchive($path, Tar::archive($files));
    }

    /**
     * Download a path from the container as a raw tar archive.
     */
    public function getArchive(string $path): string
    {
        return $this->client->get("containers/{$this->id}/archive", [
            'query' => ['path' => $path],
        ])->getBody()->getContents();
    }

    /**
     * Follow the container's output in real time, invoking the callback for
     * each frame until the container exits.
     *
     * @param  callable(string $text, string $stream): void  $onChunk  $stream is "stdout" or "stderr"
     */
    public function streamLogs(callable $onChunk): void
    {
        $body = $this->client->get("containers/{$this->id}/logs", [
            'query' => ['stdout' => true, 'stderr' => true, 'follow' => true],
            'stream' => true,
        ])->getBody();

        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (strlen($buffer) >= 8) {
                $type = ord($buffer[0]);
                $unpacked = unpack('N', substr($buffer, 4, 4));
                $size = is_array($unpacked) && is_int($unpacked[1]) ? $unpacked[1] : 0;

                if (strlen($buffer) < 8 + $size) {
                    break;
                }

                $text = substr($buffer, 8, $size);
                $buffer = substr($buffer, 8 + $size);

                if ($size > 0) {
                    $onChunk($text, $type === 2 ? 'stderr' : 'stdout');
                }
            }
        }
    }

    public function logs(bool $stdout = true, bool $stderr = true, bool $timestamps = false): string
    {
        $response = $this->client->get("containers/{$this->id}/logs", [
            'query' => [
                'stdout' => $stdout,
                'stderr' => $stderr,
                'timestamps' => $timestamps,
            ],
        ]);

        return $this->parseDockerLogs($response->getBody()->getContents());
    }

    /**
     * Capture the container's stdout/stderr, exit code and OOM state as a result.
     */
    public function result(float $duration = 0.0): ExecutionResult
    {
        $raw = $this->client->get("containers/{$this->id}/logs", [
            'query' => ['stdout' => true, 'stderr' => true],
        ])->getBody()->getContents();

        $streams = StreamParser::demux($raw);

        $details = $this->inspect(true);
        $state = is_array($details['State'] ?? null) ? $details['State'] : [];
        $exitCode = is_int($state['ExitCode'] ?? null) ? $state['ExitCode'] : null;
        $oomKilled = (bool) ($state['OOMKilled'] ?? false);

        return new ExecutionResult(
            trim($streams['stdout']),
            trim($streams['stderr']),
            $exitCode,
            false,
            $oomKilled,
            $duration,
        );
    }

    public function remove(bool $force = false, bool $removeVolumes = false): void
    {
        $this->client->delete("containers/{$this->id}", [
            'query' => [
                'force' => $force,
                'v' => $removeVolumes,
            ],
        ]);
    }

    public function inspect(bool $refresh = false): array
    {
        if (empty($this->details) || $refresh) {
            $response = $this->client->get("containers/{$this->id}/json");
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

    /**
     * Run a command inside the running container and capture its result.
     *
     * @param  list<string>  $command
     */
    public function exec(array $command, ?string $workingDir = null, ?string $user = null): ExecutionResult
    {
        $createResponse = $this->client->post("containers/{$this->id}/exec", [
            'json' => array_filter([
                'AttachStdout' => true,
                'AttachStderr' => true,
                'Cmd' => $command,
                'WorkingDir' => $workingDir,
                'User' => $user,
            ], fn ($value) => $value !== null),
        ]);

        $created = $this->decode($createResponse);
        $execId = is_string($created['Id'] ?? null) ? $created['Id'] : '';

        $startResponse = $this->client->post("exec/{$execId}/start", [
            'json' => ['Detach' => false],
        ]);

        $streams = StreamParser::demux($startResponse->getBody()->getContents());

        $inspect = $this->decode($this->client->get("exec/{$execId}/json"));
        $exitCode = is_int($inspect['ExitCode'] ?? null) ? $inspect['ExitCode'] : null;

        return new ExecutionResult(trim($streams['stdout']), trim($streams['stderr']), $exitCode);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $data = json_decode($response->getBody()->getContents(), true);

        return is_array($data) ? $data : [];
    }

    private function isTimeout(ConnectionException $e): bool
    {
        $message = $e->getPrevious()?->getMessage() ?? $e->getMessage();

        return str_contains($message, 'timed out')
            || str_contains($message, 'cURL error 28');
    }

    private function parseDockerLogs(string $logs): string
    {
        $output = '';
        $pos = 0;
        $length = strlen($logs);

        while ($pos < $length) {
            if ($length - $pos >= 8) {
                $header = substr($logs, $pos, 8);
                $unpacked = unpack('N', substr($header, 4, 4));
                $size = is_array($unpacked) ? (int) $unpacked[1] : 0;
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
