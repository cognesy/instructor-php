<?php declare(strict_types=1);

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;
use Cognesy\Schema\Data\Schema\Traits\Schema\ProvidesPropertyAccess;
use Cognesy\Schema\Data\TypeDetails;

class ObjectSchema extends Schema
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
        $visitor->visitObjectSchema($this);
    }
}
