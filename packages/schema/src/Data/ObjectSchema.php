<?php declare(strict_types=1);

namespace Cognesy\Schema\Data;

use Exception;
use Symfony\Component\TypeInfo\Type;

readonly class ObjectSchema extends Schema
{
    /**
     * @param array<string, Schema> $properties
     * @param array<string> $required
     */
    public function __construct(
        Type $type,
        string $name = '',
        string $description = '',
        public array $properties = [],
        public array $required = [],
    ) {
        parent::__construct($type, $name, $description);
    }

    #[\Override]
    public function hasProperties() : bool {
        return $this->properties !== [];
    }

    /** @return array<string, Schema> */
    #[\Override]
    public function getPropertySchemas() : array {
        return $this->properties;
    }

    /** @return string[] */
    #[\Override]
    public function getPropertyNames() : array {
        return array_keys($this->properties);
    }

    #[\Override]
    public function getPropertySchema(string $name) : Schema {
        if (!isset($this->properties[$name])) {
            throw new Exception('Property not found: ' . $name);
        }

        return $this->properties[$name];
    }

    #[\Override]
    public function hasProperty(string $name) : bool {
        return isset($this->properties[$name]);
    }
}
