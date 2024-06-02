<?php

namespace Cognesy\Instructor\Extras\Agent;

use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Extras\FunctionCall\FunctionCall;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;

class Toolset implements CanDeserializeSelf, CanTransformSelf, CanProvideJsonSchema, CanValidateSelf, CanHandleToolSelection
{
    use Traits\Toolset\HandlesAccess;
    use Traits\Toolset\HandlesSchemas;
    use Traits\Toolset\HandlesDeserialization;
    use Traits\Toolset\HandlesTransformation;
    use Traits\Toolset\HandlesValidation;

    /** @var Tool[] */
    private array $tools = [];
    private FunctionCall $call;

    public function __construct() {
    }

    static public function define(array $tools) : Toolset {
        $toolset = new Toolset();
        foreach ($tools as $tool) {
            $toolset->addTool($tool);
        }
        return $toolset;
    }
}
