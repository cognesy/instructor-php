<?php

namespace Cognesy\Instructor\Features\Schema\Data\Schema;

use Cognesy\Instructor\Features\Schema\Contracts\CanVisitSchema;
use Cognesy\Instructor\Features\Schema\Data\TypeDetails;

class ObjectSchema extends Schema
{
    use \Cognesy\Instructor\Features\Schema\Data\Traits\Schema\ProvidesPropertyAccess;

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
}
