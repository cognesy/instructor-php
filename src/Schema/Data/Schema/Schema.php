<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

use Cognesy\Instructor\Schema\Contracts\CanAcceptSchemaVisitor;
use Cognesy\Instructor\Schema\Contracts\CanVisitSchema;
use Cognesy\Instructor\Schema\Data\TypeDetails;

class Schema implements CanAcceptSchemaVisitor
{
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

    public function description(): string {
        return $this->description;
    }

    public function typeDetails(): TypeDetails {
        return $this->typeDetails;
    }

    public function getPropertyNames() : array {
        return [];
    }

    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitSchema($this);
    }

    static public function undefined() : self {
        return new self(TypeDetails::undefined());
    }
}
