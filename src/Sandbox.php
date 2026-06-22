<?php

namespace Sharryy\Docker;

use InvalidArgumentException;

/**
 * A high-level, language-aware entry point for running untrusted code with
 * secure defaults. Ships with php/python/node presets and lets callers
 * register their own.
 */
final class Sandbox
{
    /** @var array<string, Preset> */
    private static array $custom = [];

    public function __construct(private readonly Docker $docker = new Docker) {}

    /**
     * Register a custom language preset (overrides a built-in of the same name).
     */
    public static function register(string $name, Preset $preset): void
    {
        self::$custom[strtolower($name)] = $preset;
    }

    /**
     * Forget all custom presets (primarily useful in tests).
     */
    public static function flushCustomPresets(): void
    {
        self::$custom = [];
    }

    #[\NoDiscard('The ExecutionResult carries the output and exit code.')]
    public function run(string $language, string $code, int $timeout = 30): ExecutionResult
    {
        return $this->docker->containers()->runPreset($this->preset($language), $code, $timeout);
    }

    #[\NoDiscard('The ExecutionResult carries the output and exit code.')]
    public function php(string $code, int $timeout = 30): ExecutionResult
    {
        return $this->run('php', $code, $timeout);
    }

    #[\NoDiscard('The ExecutionResult carries the output and exit code.')]
    public function python(string $code, int $timeout = 30): ExecutionResult
    {
        return $this->run('python', $code, $timeout);
    }

    #[\NoDiscard('The ExecutionResult carries the output and exit code.')]
    public function node(string $code, int $timeout = 30): ExecutionResult
    {
        return $this->run('node', $code, $timeout);
    }

    private function preset(string $language): Preset
    {
        $key = strtolower($language);

        return self::$custom[$key]
            ?? self::builtin()[$key]
            ?? throw new InvalidArgumentException("Unknown sandbox preset: {$language}");
    }

    /**
     * @return array<string, Preset>
     */
    private static function builtin(): array
    {
        return [
            'php' => new Preset('php:8.2-cli', 'main.php', 'php'),
            'python' => new Preset('python:3.12-slim', 'main.py', 'python'),
            'node' => new Preset('node:20-alpine', 'main.js', 'node'),
        ];
    }
}
