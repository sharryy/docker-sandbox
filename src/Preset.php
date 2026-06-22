<?php

namespace Sharryy\Docker;

/**
 * Describes how to run a snippet of code for a given language: which image to
 * use, the filename the code is written to, and the interpreter that runs it.
 */
final class Preset
{
    public function __construct(
        public readonly string $image,
        public readonly string $filename,
        public readonly string $interpreter,
    ) {}
}
