<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\HasSignature;
use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\DataModel;
use Cognesy\Instructor\Extras\Tasks\TaskData\ObjectDataModel;
use Cognesy\Instructor\Schema\Utils\ClassInfo;


class Signature implements HasSignature, CanProvideSchema
{
    use Traits\Signature\ProvidesClassInfo;
    use Traits\Signature\ProvidesSchema;
    use Traits\ConvertsToSignatureString;
    use Traits\InitializesSignatureInputs;

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

    public function input(): DataModel {
        return $this->input;
    }

    public function output(): DataModel {
        return $this->output;
    }

    public function toArray(): array {
        return array_merge(
            $this->input->getValues(),
            $this->output->getValues(),
        );
    }

    public function description(): string {
        return $this->description;
    }
}