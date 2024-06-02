<?php

namespace Cognesy\Instructor\Extras\FunctionCall;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;

class FunctionCall implements CanDeserializeSelf, CanTransformSelf, CanProvideSchema, CanValidateSelf
{
    use Traits\HandlesConstruction;
    use Traits\HandlesCallInfo;
    use Traits\HandlesTransformation;
    use Traits\HandlesDeserialization;
    use Traits\HandlesSchema;
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
