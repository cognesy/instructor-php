<?php declare(strict_types=1);

namespace Cognesy\Utils\JsonSchema;

use JsonSerializable;
use Stringable;

class JsonSchemaType implements JsonSerializable, Stringable
{
    use Traits\DefinesJsonTypes;
    use Traits\JsonSchemaType\HandlesAccess;
    use Traits\JsonSchemaType\HandlesFactoryMethods;

    /** @var string[] */
    private array $types;
    private ?bool $isNullable;

    private function __construct(array $types, ?bool $isNullable = null) {
        $this->types = $types;
        $this->isNullable = $isNullable;
    }

    public static function fromJsonData(array $data, ?bool $isNullable = null): self {
        $types = match(true) {
            empty($data) => [],
            isset($data['type']) && is_string($data['type']) => [$data['type']],
            isset($data['type']) && is_array($data['type']) => $data['type'],
            isset($data['anyOf']) && is_array($data['anyOf']) => array_map(
                fn($item) => $item['type'] ?? '',
                $data['anyOf'],
            ),
        };

        // deduplicate types
        $types = array_unique($types);

        foreach ($types as $type) {
            if ($type === 'null') {
                continue; // 'null' is a valid type, but we handle it separately
            }
            if (!in_array($type, self::JSON_TYPES)) {
                throw new \InvalidArgumentException("Invalid JSON type: $type in: " . json_encode($types));
            }
        }

        return new self(
            types: $types,
            isNullable: $isNullable,
        );
    }

    public function jsonSerialize(): mixed {
        if (count($this->types) === 1) {
            return $this->types[0];
        }
        return $this->types;
    }


    public function toString() : string {
        if (count($this->types) === 0) {
            return '';
        }
        if (count($this->types) === 1) {
            return $this->types[0];
        }
        return json_encode($this->types);
    }

    public function __toString() {
        return $this->toString();
    }
}