<?php

namespace Cognesy\Instructor\Extras\Toolset;

use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Extras\Call\Call;
use Cognesy\Instructor\Extras\Tool\Tool;

class Toolset implements CanDeserializeSelf, CanTransformSelf, CanProvideJsonSchema, CanValidateSelf, CanHandleToolSelection
{
    use Traits\HandlesAccess;
    use Traits\HandlesSchemas;
    use Traits\HandlesDeserialization;
    use Traits\HandlesTransformation;
    use Traits\HandlesValidation;

    /** @var Tool[] */
    private array $tools = [];
    private Call $call;

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
