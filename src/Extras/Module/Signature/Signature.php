<?php

namespace Cognesy\Instructor\Extras\Module\Signature;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Module\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Module\DataModel\Contracts\DataModel;
use Cognesy\Instructor\Extras\Module\DataModel\ObjectDataModel;
use Cognesy\Instructor\Schema\Utils\ClassInfo;


class Signature implements HasSignature, CanProvideSchema
{
    use Traits\Signature\ProvidesClassInfo;
    use Traits\Signature\ProvidesSchema;
    use Traits\ConvertsToSignatureString;
    use Traits\InitializesSignatureInputs;
    use Traits\HandlesErrors;
    use Traits\HandlesTaskData;

    private DataModel $input;
    private DataModel $output;
    private string $description;

    public function __construct() {
        $instance = $this;
        $classInfo = new ClassInfo(static::class);
        $instance->description = $classInfo->getClassDescription();
        $inputProperties = $this->getPropertyNames($classInfo, [fn($property) => $property->hasAttribute(InputField::class)]);
        $outputProperties = $this->getPropertyNames($classInfo, [fn($property) => $property->hasAttribute(OutputField::class)]);
        $instance->input = new ObjectDataModel($instance, $inputProperties);
        $instance->output = new ObjectDataModel($instance, $outputProperties);
    }

    static public function make(mixed ...$inputs) : static {
        $instance = new static;
        if (empty($inputs)) {
            return $instance;
        }
        return $instance->withArgs(...$inputs);
    }

    public function description(): string {
        return $this->description;
    }
}