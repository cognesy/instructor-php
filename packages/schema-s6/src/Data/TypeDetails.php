<?php
namespace Cognesy\Schema\Data;

class TypeDetails
{
    use Traits\TypeDetails\HandlesAccess;
    use Traits\TypeDetails\HandlesConversion;
    use Traits\TypeDetails\HandlesFactoryMethods;
    use Traits\TypeDetails\HandlesJsonTypes;
    use Traits\TypeDetails\DefinesJsonTypeConstants;
    use Traits\TypeDetails\DefinesPhpTypeConstants;
    use Traits\TypeDetails\HandlesPhpTypes;
    use Traits\TypeDetails\HandlesValidation;

    /**
     * @param string $type object, enum, array, int, string, bool, float
     * @param class-string|null $class for objects and enums OR null
     * @param TypeDetails|null $nestedType for arrays OR null
     * @param string|null $enumType for enums OR null
     * @param array|null $enumValues for enums OR null
     * @param string|null $docString
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

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'class' => $this->class,
            'nestedType' => $this->nestedType?->toArray(),
            'enumType' => $this->enumType,
            'enumValues' => $this->enumValues,
            'docString' => $this->docString,
        ];
    }

    public function clone() : self {
        return new self(
            type: $this->type,
            class: $this->class,
            nestedType: $this->nestedType?->clone(),
            enumType: $this->enumType,
            enumValues: $this->enumValues,
            docString: $this->docString,
        );
    }
}
