<?php declare(strict_types=1);

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Data\TypeDetails;
use Exception;

readonly class ArrayShapeSchema extends Schema
{
    /**
     * @param array<string, Schema> $properties
     * @param array<string> $required
     */
    public function __construct(
        TypeDetails $type,
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

    /** @return static */
    #[\Override]
    public function removeProperty(string $name) : static {
        if (!$this->hasProperty($name)) {
            throw new Exception('Property not found: ' . $name);
        }

        $properties = array_filter($this->properties, static fn(string $key) : bool => $key !== $name, ARRAY_FILTER_USE_KEY);
        $required = array_values(array_filter($this->required, static fn(string $value) : bool => $value !== $name));

        return new static($this->typeDetails, $this->name, $this->description, $properties, $required);
    }

    #[\Override]
    public function withName(string $name) : self {
        return new self($this->typeDetails, $name, $this->description, $this->properties, $this->required);
    }

    #[\Override]
    public function withDescription(string $description) : self {
        return new self($this->typeDetails, $this->name, $description, $this->properties, $this->required);
    }
}
