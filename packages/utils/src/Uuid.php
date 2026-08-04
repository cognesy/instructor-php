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
    private const VALID_UUID_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i';

    /** First three UUID groups, drawn once per process. See correlationId(). */
    private static ?string $correlationPrefix = null;
    private static int $correlationCounter = 0;

    /**
     * Generates a random UUID (version 4) string.
     *
     * @return string A randomly generated UUID (version 4) string.
     */
    public static function uuid4() : string {
        return self::fromRandomBytes();
    }

    /**
     * Generates a UUID-shaped correlation id, cheaply.
     *
     * Same shape as uuid4() and accepted by isValid(), but built from one per-process
     * CSPRNG draw plus a counter instead of a fresh random_bytes(16) per call: 0.245 us
     * against 0.594 us, measured over 100k draws. Event::__construct() calls this for
     * every event in the framework, so the difference is paid constantly.
     *
     * NOT a substitute for uuid4() where unpredictability matters. Within one process
     * these ids are guessable — the low 60 bits are a counter — so use them only for
     * correlating logs, traces and events, never for authorization, addressing, or
     * anything an attacker benefits from predicting.
     *
     * Uniqueness rests on the per-process prefix, which is a CSPRNG draw mixed with the
     * pid. Caveat: a process that forks AFTER its first call passes its prefix and
     * counter to the child, and the two will then collide. Forking workers should call
     * resetCorrelationPrefix() in the child.
     */
    public static function correlationId() : string {
        self::$correlationPrefix ??= self::newCorrelationPrefix();
        // 60 bits, which is 15 hex digits — the width of the last two UUID groups
        // minus the variant nibble. Masking keeps the string length fixed forever
        // rather than silently producing an invalid id on overflow.
        $counter = ++self::$correlationCounter & 0x0FFFFFFFFFFFFFFF;
        $tail = str_pad(dechex($counter), 15, '0', STR_PAD_LEFT);

        return self::$correlationPrefix.'-8'.substr($tail, 0, 3).'-'.substr($tail, 3, 12);
    }

    /**
     * Draws a fresh per-process prefix. Call in a child after forking.
     */
    public static function resetCorrelationPrefix() : void {
        self::$correlationPrefix = null;
        self::$correlationCounter = 0;
    }

    public static function hex(int $length = 4) : string {
        if ($length <= 0) {
            throw new \InvalidArgumentException('Length must be a positive integer.');
        }
        return bin2hex(random_bytes($length));
    }

    public static function isValid(string $value): bool {
        return preg_match(self::VALID_UUID_PATTERN, $value) === 1;
    }

    public static function assertValid(string $value): void {
        if (!self::isValid($value)) {
            throw new \InvalidArgumentException("Invalid UUID: {$value}");
        }
    }

    /**
     * Builds "xxxxxxxx-xxxx-4xxx" — the first three groups, with the version nibble
     * already set. The pid is mixed in so a child that first calls after forking gets
     * a different prefix than its parent.
     */
    private static function newCorrelationPrefix() : string {
        $seed = bin2hex(random_bytes(8) ^ pack('J', getmypid()));

        return substr($seed, 0, 8).'-'.substr($seed, 8, 4).'-4'.substr($seed, 12, 3);
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
