<?php
namespace Cognesy\Instructor\Extras\Structure;

use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Deserialization\Symfony\Deserializer;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

class Structure implements CanProvideSchema, CanDeserializeSelf, CanValidateSelf, CanTransformSelf
{
    use Traits\CreatesFromArray;
    use Traits\CreatesFromCallables;
    use Traits\CreatesFromClasses;
    use Traits\CreatesFromJsonSchema;
    use Traits\CreatesFromString;
    use Traits\HandlesDefinition;
    use Traits\HandlesDeserialization;
    use Traits\HandlesFieldAccess;
    use Traits\HandlesSerialization;
    use Traits\HandlesStructureInfo;
    use Traits\HandlesTransformation;
    use Traits\HandlesValidation;
    use Traits\ProvidesSchema;

    public function __construct() {
        $this->schemaFactory = new SchemaFactory(false);
        $this->typeDetailsFactory = new TypeDetailsFactory();
        $this->deserializer = new Deserializer();
    }
}
