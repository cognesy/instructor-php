<?php
namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;
use Cognesy\Schema\Data\TypeDetails;

// TODO: not implemented / supported yet

class ArrayShapeSchema extends Schema
{
    use \Cognesy\Schema\Data\Schema\Traits\Schema\ProvidesPropertyAccess;

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