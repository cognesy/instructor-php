<?php

namespace Cognesy\Instructor\Utils;

class Uuid {
    public static function uuid4() : string {
        return self::fromRandomBytes();
    }

    private static function fromRandomBytes() : string {
        // generate uuid using random bytes
        $data = random_bytes(16);
        // format as hex string in uuid format
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private static function fromRamseyUuid4() : string {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }
}