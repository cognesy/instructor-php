<?php declare(strict_types=1);

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;
use Cognesy\Schema\Data\Schema\Traits\ProvidesPropertyAccess;
use Cognesy\Schema\Data\TypeDetails;
use Exception;

// TODO: experimental

readonly class ArrayShapeSchema extends Schema
{
    use ProvidesPropertyAccess;

    /** @var array<string, Schema> */
    public array $properties; // for objects OR empty
    /** @var string[] */
    public array $required; // for objects OR empty

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

    #[\Override]
    public function removeProperty(string $name): static {
        if (!$this->hasProperty($name)) {
            throw new Exception('Property not found: ' . $name);
        }

        $newProperties = array_filter($this->properties, fn($k) => $k !== $name, ARRAY_FILTER_USE_KEY);
        $newRequired = array_filter($this->required, fn($v) => $v !== $name);

        return new static(
            type: $this->typeDetails,
            name: $this->name,
            description: $this->description,
            properties: $newProperties,
            required: $newRequired,
        );
    }

    #[\Override]
    public function withName(string $name): self {
        return new self(
            type: $this->typeDetails,
            name: $name,
            description: $this->description,
            properties: $this->properties,
            required: $this->required,
        );
    }

    #[\Override]
    public function withDescription(string $description): self {
        return new self(
            type: $this->typeDetails,
            name: $this->name,
            description: $description,
            properties: $this->properties,
            required: $this->required,
        );
    }

    #[\Override]
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitArrayShapeSchema($this);
    }
}