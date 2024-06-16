<?php
namespace Cognesy\Instructor\Schema\Data;

use Cognesy\Instructor\Schema\Data\Traits\TypeDetails\HandlesJsonTypes;
use Cognesy\Instructor\Schema\Data\Traits\TypeDetails\HandlesPhpTypes;
use Cognesy\Instructor\Schema\Data\Traits\TypeDetails\HandlesTypeDetailsConstruction;

class TypeDetails
{
    use HandlesPhpTypes;
    use HandlesJsonTypes;
    use HandlesTypeDetailsConstruction;

    /**
     * @param string $type object, enum, array, int, string, bool, float
     * @param class-string|null $class for objects and enums OR null
     * @param TypeDetails|null $nestedType for arrays OR null
     * @param string|null $enumType for enums OR null
     * @param array|null $enumValues for enums OR null
     */
    public function __construct(
        public string $type,
        public ?string $class = null,
        public ?TypeDetails $nestedType = null,
        public ?string $enumType = null,
        public ?array $enumValues = null,
        public ?string $docString = null,
    ) {
        $this->validate($type, $class, $nestedType, $enumType, $enumValues);
    }

    public function type() : string {
        return $this->type;
    }

    public function class() : ?string {
        return $this->class;
    }

    public function nestedType() : ?TypeDetails {
        return $this->nestedType;
    }

    public function enumType() : ?string {
        return $this->enumType;
    }

    public function enumValues() : ?array {
        return $this->enumValues;
    }

    public function docString() : string {
        return $this->docString;
    }

    public function __toString() : string {
        return $this->toString();
    }
}
