<?php
namespace Cognesy\Instructor\Extras\Structure;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;

class Structure implements CanProvideSchema, CanDeserializeSelf, CanValidateSelf, CanTransformSelf
{
    use Traits\Structure\HandlesDefinition;
    use Traits\Structure\HandlesDeserialization;
    use Traits\Structure\HandlesFieldAccess;
    use Traits\Structure\HandlesSerialization;
    use Traits\Structure\HandlesStructureInfo;
    use Traits\Structure\HandlesTransformation;
    use Traits\Structure\HandlesValidation;
    use Traits\Structure\ProvidesSchema;

    protected string $name = '';
    protected string $description = '';
    /** @var Field[] */
    protected array $fields = [];

    public function __construct() {
        $this->deserializer = new SymfonyDeserializer();
    }
}
