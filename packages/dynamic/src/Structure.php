<?php declare(strict_types=1);

namespace Cognesy\Dynamic;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Schema\Contracts\CanProvideSchema;

/**
 * @property mixed $stringProperty
 * @property mixed $integerProperty
 * @property mixed $boolProperty
 * @property mixed $floatProperty
 * @property mixed $datetimeProperty
 * @property mixed $enumProperty
 * @property mixed $optionProperty
 * @property mixed $objectProperty
 * @property mixed $arrayProperty
 * @property mixed $collectionProperty
 * @property mixed $collectionObjectProperty
 * @property mixed $collectionEnumProperty
 * @property mixed $collectionDateProperty
 * @property mixed $structureProperty
 */
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
