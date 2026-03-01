<?php declare(strict_types=1);

namespace Cognesy\Schema\Data;

use Cognesy\Schema\TypeInfo;
use Exception;
use Symfony\Component\TypeInfo\Type;

readonly class Schema
{
    public function __construct(
        public Type $type,
        public string $name = '',
        public string $description = '',
        /** @var array<string|int>|null */
        public ?array $enumValues = null,
    ) {}

    public function name() : string {
        return $this->name;
    }

    public function description() : string {
        return $this->description;
    }

    public function type() : Type {
        return $this->type;
    }

    public function hasProperties() : bool {
        return false;
    }

    public function isScalar() : bool {
        return TypeInfo::isScalar($this->type) && $this->enumValues === null;
    }

    public function isObject() : bool {
        return TypeInfo::isObject($this->type);
    }

    public function isEnum() : bool {
        return TypeInfo::isEnum($this->type)
            || ($this->enumValues !== null && $this->enumValues !== []);
    }

    public function isArray() : bool {
        return TypeInfo::isArray($this->type);
    }

    /** @return string[] */
    public function getPropertyNames() : array {
        return [];
    }

    /** @return array<string, Schema> */
    public function getPropertySchemas() : array {
        return [];
    }

    public function getPropertySchema(string $name) : Schema {
        throw new Exception('Property not found: ' . $name);
    }

    public function hasProperty(string $name) : bool {
        return false;
    }

    /** @return array<string, mixed> */
    public function toArray() : array {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'type' => (string) $this->type,
            'class' => TypeInfo::className($this->type),
            'enumValues' => $this->enumValues ?? TypeInfo::enumValues($this->type),
        ];
    }
}
