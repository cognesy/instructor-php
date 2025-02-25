<?php

namespace Cognesy\Instructor\Extras\Scalar;

use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Features\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Features\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Features\Validation\Contracts\CanValidateSelf;

/**
 * Scalar value adapter.
 * Improved DX via simplified retrieval of scalar value from LLM response.
 */
class Scalar implements CanProvideJsonSchema, CanDeserializeSelf, CanTransformSelf, CanValidateSelf
{
    use Traits\HandlesDeserialization;
    use Traits\ProvidesJsonSchema;
    use Traits\HandlesTransformation;
    use Traits\HandlesTypeDefinitions;
    use Traits\HandlesValidation;

    public mixed $value;

    public string $name = 'value';
    public string $description = 'Response value';
    public ValueType $type = ValueType::STRING;
    public bool $required = true;
    public mixed $defaultValue = null;

    public function __construct(
        string $name = 'value',
        string $description = 'Response value',
        ValueType $type = ValueType::STRING,
        bool $required = true,
        mixed $defaultValue = null,
        string $enumType = null
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->required = $required;
        $this->defaultValue = $defaultValue;
        $this->type = $type;
        $this->initEnum($enumType, $type);
    }
}
