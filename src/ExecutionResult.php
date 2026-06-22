<?php

namespace Sharryy\Docker;

use Stringable;

/**
 * The outcome of running code or a command in a container.
 */
final class ExecutionResult implements Stringable
{
    public function __construct(
        private readonly string $output,
        private readonly string $errorOutput = '',
        private readonly ?int $exitCode = null,
        private readonly bool $timedOut = false,
        private readonly bool $oomKilled = false,
        private readonly float $duration = 0.0,
    ) {}

    public function output(): string
    {
        return $this->output;
    }

    public function errorOutput(): string
    {
        return $this->errorOutput;
    }

    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    public function timedOut(): bool
    {
        return $this->timedOut;
    }

    public function oomKilled(): bool
    {
        return $this->oomKilled;
    }

    /**
     * Wall-clock execution time in seconds.
     */
    public function duration(): float
    {
        return $this->duration;
    }

    public function __toString(): string
    {
        return $this->output;
    }
}
