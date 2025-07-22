<?php declare(strict_types=1);

namespace Cognesy\Utils;

/**
 * A class for generating Universally Unique Identifiers (UUID).
 *
 * Goal is to decouple Instructor main code from depending on specific UUID provider libraries
 * and make it easier to switch providers.
 *
 * TODO: implement drivers for different UUID providers (e.g. ramsey/uuid, webpatser/uuid, etc.)
 */
class Uuid {
    /**
     * Generates a random UUID (version 4) string.
     *
     * @return string A randomly generated UUID (version 4) string.
     */
    public static function uuid4() : string {
        return self::fromRandomBytes();
    }

    public static function hex(int $length = 4) : string {
        if ($length <= 0) {
            throw new \InvalidArgumentException('Length must be a positive integer.');
        }
        return bin2hex(random_bytes($length));
    }

    /**
     * Generates a UUID using random bytes.
     *
     * @return string Generated UUID in the format xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
     */
    private static function fromRandomBytes() : string {
        // generate uuid using random bytes
        $data = random_bytes(16);
        // format as hex string in uuid format
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
