<?php
namespace Cognesy\Instructor\Schema\Data;

use Cognesy\Instructor\Schema\Data\Traits\TypeDetails\DefinesJsonTypeConstants;
use Cognesy\Instructor\Schema\Data\Traits\TypeDetails\DefinesPhpTypeConstants;
use Cognesy\Instructor\Schema\Data\Traits\TypeDetails\HandlesJsonTypes;
use Cognesy\Instructor\Schema\Data\Traits\TypeDetails\HandlesPhpTypes;
use Cognesy\Instructor\Schema\Data\Traits\TypeDetails\HandlesTypeDetailsValidation;

class TypeDetails
{
    use Traits\TypeDetails\HandlesAccess;
    use Traits\TypeDetails\HandlesFactoryMethods;
    use HandlesPhpTypes;
    use HandlesJsonTypes;
    use DefinesJsonTypeConstants;
    use DefinesPhpTypeConstants;
    use HandlesTypeDetailsValidation;

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
}
