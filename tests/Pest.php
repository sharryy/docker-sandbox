<?php

use Sharryy\Docker\Docker;

/**
 * Resolve a usable Docker socket for the integration tests.
 *
 * Mirrors ConnectionOptions' discovery so the suite runs on the standard
 * daemon path as well as Colima / Docker Desktop setups.
 */
function dockerSocket(): string
{
    $candidates = [];

    $dockerHost = getenv('DOCKER_HOST');
    if (is_string($dockerHost) && str_starts_with($dockerHost, 'unix://')) {
        $candidates[] = substr($dockerHost, strlen('unix://'));
    }

    $candidates[] = '/var/run/docker.sock';

    $home = getenv('HOME');
    if (is_string($home) && $home !== '') {
        $candidates[] = "{$home}/.colima/default/docker.sock";
        $candidates[] = "{$home}/.docker/run/docker.sock";
    }

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && file_exists($candidate)) {
            return $candidate;
        }
    }

    return '/var/run/docker.sock';
}

// Ensure the base image the integration suite builds on is present, so the
// builder-based tests work on a fresh daemon (e.g. CI) and not just where the
// image happens to be cached already.
(static function (): void {
    try {
        $docker = new Docker;

        if (! $docker->images()->exists('php:8.2-cli')) {
            $docker->images()->pull('php:8.2-cli');
        }
    } catch (Throwable) {
        // No daemon reachable at bootstrap; the tests themselves will surface it.
    }
})();
