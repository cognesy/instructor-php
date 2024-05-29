<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

use Cognesy\Instructor\Schema\Contracts\CanVisitSchema;
use Cognesy\Instructor\Schema\Data\TypeDetails;

class ObjectSchema extends Schema
{
    /** @var array<string, Schema> */
    public array $properties = []; // for objects OR empty
    /** @var string[] */
    public array $required = []; // for objects OR empty

    public function __construct(
        TypeDetails $type,
        string $name = '',
        string $description = '',
        array $properties = [],
        array $required = [],
    ) {
        parent::__construct($type, $name, $description);
        $this->properties = $properties;
        $this->required = $required;
    }

    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitObjectSchema($this);
    }

    /** @return Schema[] */
    public function getProperties() : array {
        return $this->properties;
    }

    /** @return string[] */
    public function getPropertyNames() : array {
        return array_keys($this->properties);
    }

    public function removeProperty(string $name): void {
        unset($this->properties[$name]);
        unset($this->required[$name]);
    }
}
