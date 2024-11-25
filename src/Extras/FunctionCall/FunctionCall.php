<?php

namespace Cognesy\Instructor\Extras\FunctionCall;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Features\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Features\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Features\Validation\Contracts\CanValidateSelf;

/**
 * Represents a function call that can be inferred from provided context
 * using any of the Instructor's modes (not just tool calling).
 */
class FunctionCall implements CanDeserializeSelf, CanTransformSelf, CanProvideSchema, CanValidateSelf
{
    use Traits\HandlesAccess;
    use Traits\HandlesConstruction;
    use Traits\HandlesDeserialization;
    use Traits\HandlesMutation;
    use Traits\HandlesSchema;
    use Traits\HandlesTransformation;
    use Traits\HandlesValidation;

    private string $name;
    private string $description;
    private Structure $arguments;

    public function __construct(
        string $name,
        string $description,
        Structure $arguments
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->arguments = $arguments;
    }
}
