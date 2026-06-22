<?php

namespace Sharryy\Docker\Support;

/**
 * Demultiplexes the Docker Engine's attached stream format.
 *
 * When a container is started without a TTY, stdout and stderr are
 * interleaved into a single stream where each frame is prefixed with an
 * 8-byte header: byte 0 is the stream type (1 = stdout, 2 = stderr) and
 * bytes 4-7 are the big-endian frame size.
 */
final class StreamParser
{
    private const HEADER_SIZE = 8;

    private const STDERR = 2;

    /**
     * @return array{stdout: string, stderr: string}
     */
    public static function demux(string $raw): array
    {
        $stdout = '';
        $stderr = '';
        $pos = 0;
        $length = strlen($raw);

        while ($pos + self::HEADER_SIZE <= $length) {
            $type = ord($raw[$pos]);
            $unpacked = unpack('N', substr($raw, $pos + 4, 4));
            $size = is_array($unpacked) ? (int) $unpacked[1] : 0;
            $pos += self::HEADER_SIZE;

            if ($size <= 0 || $pos + $size > $length) {
                break;
            }

            $chunk = substr($raw, $pos, $size);
            $pos += $size;

            if ($type === self::STDERR) {
                $stderr .= $chunk;
            } else {
                $stdout .= $chunk;
            }
        }

        return ['stdout' => $stdout, 'stderr' => $stderr];
    }
}
