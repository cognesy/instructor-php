<?php

namespace Cognesy\Instructor\Extras\Call;

use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Extras\Structure\Structure;

class Call implements CanDeserializeSelf, CanTransformSelf, CanProvideSchema, CanValidateSelf
{
    use Traits\HandlesSignatureConstruction;
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
