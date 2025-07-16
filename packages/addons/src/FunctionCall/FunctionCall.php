<?php

namespace Cognesy\Addons\FunctionCall;

use Cognesy\Addons\FunctionCall\Traits\HandlesAccess;
use Cognesy\Addons\FunctionCall\Traits\HandlesConstruction;
use Cognesy\Addons\FunctionCall\Traits\HandlesDeserialization;
use Cognesy\Addons\FunctionCall\Traits\HandlesMutation;
use Cognesy\Addons\FunctionCall\Traits\HandlesSchema;
use Cognesy\Addons\FunctionCall\Traits\HandlesTransformation;
use Cognesy\Addons\FunctionCall\Traits\HandlesValidation;
use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Schema\Contracts\CanProvideSchema;

/**
 * Represents a function call that can be inferred from provided context
 * using any of the Instructor's modes (not just tool calling).
 */
class FunctionCall implements CanDeserializeSelf, CanTransformSelf, CanProvideSchema, CanValidateSelf
{
    use HandlesAccess;
    use HandlesConstruction;
    use HandlesDeserialization;
    use HandlesMutation;
    use HandlesSchema;
    use HandlesTransformation;
    use HandlesValidation;

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
