<?php

namespace Sharryy\Docker\Support;

/**
 * Minimal in-memory tar (ustar) writer.
 *
 * The Docker Engine API uploads files into a container via a tar stream
 * (PUT /containers/{id}/archive). Building the archive in memory lets us
 * inject code into a container without sharing a filesystem with the daemon,
 * so it works identically for local sockets, VM-backed daemons (Colima/Lima)
 * and remote TCP/TLS daemons.
 */
class Tar
{
    private const BLOCK_SIZE = 512;

    /**
     * Build a tar archive containing a single file.
     */
    public static function single(string $name, string $contents, int $mode = 0644): string
    {
        return self::header($name, strlen($contents), $mode)
            .self::pad($contents)
            .str_repeat("\0", self::BLOCK_SIZE * 2);
    }

    private static function header(string $name, int $size, int $mode): string
    {
        $header = pack('a100', ltrim($name, '/'))
            .pack('a8', sprintf('%07o', $mode & 0777))
            .pack('a8', sprintf('%07o', 0))            // uid
            .pack('a8', sprintf('%07o', 0))            // gid
            .pack('a12', sprintf('%011o', $size))
            .pack('a12', sprintf('%011o', 0))          // mtime (deterministic)
            .str_repeat(' ', 8)                         // checksum placeholder
            .'0'                                        // typeflag: regular file
            .pack('a100', '')                           // linkname
            .pack('a6', 'ustar')
            .pack('a2', '00')
            .pack('a32', '')                            // uname
            .pack('a32', '')                            // gname
            .pack('a8', '')                             // devmajor
            .pack('a8', '')                             // devminor
            .pack('a155', '');                          // prefix

        $header = str_pad($header, self::BLOCK_SIZE, "\0");

        $checksum = 0;
        for ($i = 0; $i < self::BLOCK_SIZE; $i++) {
            $checksum += ord($header[$i]);
        }

        // Overwrite the checksum field: 6 octal digits, null, space.
        $checksumField = sprintf('%06o', $checksum)."\0 ";

        return substr_replace($header, $checksumField, 148, 8);
    }

    private static function pad(string $contents): string
    {
        $remainder = strlen($contents) % self::BLOCK_SIZE;

        if ($remainder === 0) {
            return $contents;
        }

        return $contents.str_repeat("\0", self::BLOCK_SIZE - $remainder);
    }
}
