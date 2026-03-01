<?php declare(strict_types=1);

namespace Cognesy\Utils\Json;

use InvalidArgumentException;
use JsonException;

class Json
{
    private string $json;

    public function __construct(string $json = '') {
        $this->json = $json;
    }

    // NEW API ////////////////////////////////////////////////

    public static function none() : self {
        return new Json('');
    }

    public static function fromString(string $text) : Json {
        return match(true) {
            empty(trim($text)) => new Json(''),
            default => new Json((new JsonParser)->findCompleteJson($text)),
        };
    }

    public static function fromArray(array $array) : Json {
        return new Json(json_encode($array) ?: '');
    }

    public static function fromPartial(string $text) : Json {
        return match(true) {
            empty(trim($text)) => new Json(''),
            default => new Json((new JsonParser)->findPartialJson($text)),
        };
    }

    public function isEmpty() : bool {
        return $this->json === '';
    }

    public function toString() : string {
        return $this->json;
    }

    public function toArray() : array {
        if ($this->isEmpty()) {
            return [];
        }
        try {
            $decoded = json_decode($this->json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException("Invalid JSON provided: {$e->getMessage()}", 0, $e);
        }

        if (!is_array($decoded)) {
            $type = gettype($decoded);
            throw new InvalidArgumentException("Json::toArray expects JSON object or array as root type, got {$type}.");
        }

        return $decoded;
    }

    public function format(int $options = 0, ?int $depth = null) : string {
        if (is_null($depth)) {
            return json_encode($this->toArray(), $options) ?: '';
        }
        $safeDepth = max(1, $depth);
        /** @var int<1, 2147483647> $safeDepth */
        return json_encode($this->toArray(), $options, $safeDepth) ?: '';
    }

    // STATIC /////////////////////////////////////////////////

    public static function decode(string $text, mixed $default = null) : mixed {
        if ($text === '') {
            return $default;
        }
        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            if ($default === null) {
                throw $e;
            }
            return $default;
        }
        return $decoded;
    }

    public static function encode(mixed $json, int $options = 0) : string {
        return json_encode($json, $options) ?: '';
    }
}
