<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

use Cognesy\Instructor\Schema\Contracts\CanAcceptSchemaVisitor;
use Cognesy\Instructor\Schema\Contracts\CanVisitSchema;
use Cognesy\Instructor\Schema\Data\Traits\Schema\ProvidesNoPropertyAccess;
use Cognesy\Instructor\Schema\Data\Traits\Schema\HandlesFactoryMethods;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Visitors\SchemaToJsonSchema;

class Schema implements CanAcceptSchemaVisitor
{
    use ProvidesNoPropertyAccess;
    use HandlesFactoryMethods;

    public string $name = '';
    public string $description = '';
    public TypeDetails $typeDetails;

    public function __construct(
        TypeDetails $type,
        string $name = '',
        string $description = '',
    ) {
        $this->typeDetails = $type;
        $this->name = $name;
        $this->description = $description;
    }

    public function name(): string {
        return $this->name;
    }

    public function withName(string $name): self {
        $this->name = $name;
        return $this;
    }

    public function description(): string {
        return $this->description;
    }

    public function withDescription(string $description): self {
        $this->description = $description;
        return $this;
    }

    public function typeDetails(): TypeDetails {
        return $this->typeDetails;
    }

    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitSchema($this);
    }

    static public function undefined() : self {
        return new self(TypeDetails::undefined());
    }

    public function toJsonSchema() : array {
        return (new SchemaToJsonSchema)->toArray($this);
    }

    public function hasProperties(): bool {
        return false;
    }

    public function isScalar(): bool {
        return $this->typeDetails->isScalar();
    }
}
