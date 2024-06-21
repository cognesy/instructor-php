<?php
namespace Cognesy\Instructor\Extras\Structure;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
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

    public function __construct() {
        $this->schemaFactory = new SchemaFactory(false);
        $this->typeDetailsFactory = new TypeDetailsFactory();
        $this->deserializer = new SymfonyDeserializer();
    }
}
