<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

use Cognesy\Instructor\Schema\Contracts\CanAcceptSchemaVisitor;
use Cognesy\Instructor\Schema\Contracts\CanVisitSchema;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use JetBrains\PhpStorm\Deprecated;

class Schema implements CanAcceptSchemaVisitor
{
    public TypeDetails $type;
    public string $name = '';
    public string $description = '';

    protected string $xmlLineSeparator = "";

    public function __construct(
        TypeDetails $type,
        string $name = '',
        string $description = '',
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->description = $description;
    }

    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitSchema($this);
    }

    static public function undefined() : self {
        return new self(TypeDetails::undefined());
    }

    public function getPropertyNames() : array {
        return [];
    }
}
