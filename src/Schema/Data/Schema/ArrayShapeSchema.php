<?php
namespace Cognesy\Instructor\Schema\Data\Schema;

use Cognesy\Instructor\Schema\Contracts\CanVisitSchema;
use Cognesy\Instructor\Schema\Data\Traits\Schema\ProvidesPropertyAccess;
use Cognesy\Instructor\Schema\Data\TypeDetails;

// TODO: not implemented / supported yet

class ArrayShapeSchema extends Schema
{
    use ProvidesPropertyAccess;

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
        $visitor->visitArrayShapeSchema($this);
    }
}