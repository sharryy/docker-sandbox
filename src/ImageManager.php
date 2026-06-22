<?php

namespace Sharryy\Docker;

use Sharryy\Docker\Exceptions\BadRequestException;
use Sharryy\Docker\Exceptions\DockerException;

readonly class ImageManager
{
    public function __construct(private DockerClient $client) {}

    /**
     * Pull an image from a registry, blocking until the pull completes.
     *
     * @param  string|null  $auth  Base64-encoded X-Registry-Auth payload for private registries.
     */
    public function pull(string $image, ?string $auth = null): void
    {
        [$name, $tag] = $this->splitTag($image);

        $options = ['query' => ['fromImage' => $name, 'tag' => $tag]];

        if ($auth !== null) {
            $options['headers'] = ['X-Registry-Auth' => $auth];
        }

        // images/create returns 200 even on failure and reports problems as
        // {"error": ...} lines in the streamed body, so inspect it.
        $body = $this->client->post('images/create', $options)->getBody()->getContents();

        if (str_contains($body, '"error"')) {
            throw new DockerException("Failed to pull image '{$image}': {$body}");
        }
    }

    public function exists(string $image): bool
    {
        try {
            $this->client->get("images/{$image}/json");

            return true;
        } catch (BadRequestException) {
            return false;
        }
    }

    /**
     * List the repository tags of all local images.
     *
     * @return list<string>
     */
    public function list(): array
    {
        $data = json_decode($this->client->get('images/json')->getBody()->getContents(), true);

        $tags = [];

        if (is_array($data)) {
            foreach ($data as $image) {
                if (is_array($image) && is_array($image['RepoTags'] ?? null)) {
                    foreach ($image['RepoTags'] as $tag) {
                        if (is_string($tag)) {
                            $tags[] = $tag;
                        }
                    }
                }
            }
        }

        return $tags;
    }

    public function remove(string $image, bool $force = false): void
    {
        $this->client->delete("images/{$image}", [
            'query' => ['force' => $force],
        ]);
    }

    /**
     * Remove unused (dangling) images.
     */
    public function prune(): void
    {
        $this->client->post('images/prune');
    }

    /**
     * Split an image reference into its name and tag, accounting for a
     * registry host that itself contains a port (e.g. "host:5000/img:tag").
     *
     * @return array{0: string, 1: string}
     */
    private function splitTag(string $image): array
    {
        $slash = strrpos($image, '/');
        $colon = strrpos($image, ':');

        if ($colon !== false && ($slash === false || $colon > $slash)) {
            return [substr($image, 0, $colon), substr($image, $colon + 1)];
        }

        return [$image, 'latest'];
    }
}
