<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Tasks\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\DataModel;
use Cognesy\Instructor\Extras\Tasks\TaskData\ObjectDataModel;
use Cognesy\Instructor\Schema\Utils\ClassInfo;


class AutoSignature implements Signature, CanProvideSchema
{
    use Traits\ProvidesClassData;
    use Traits\ProvidesSchema;
    use Traits\ConvertsToSignatureString;
    use Traits\InitializesSignatureInputs;

    private DataModel $input;
    private DataModel $output;
    private string $description;

    public function __construct() {
        $classInfo = new ClassInfo(static::class);
        $this->description = $classInfo->getClassDescription();
        $inputProperties = self::getPropertyNames($classInfo, [fn($property) => $property->hasAttribute(InputField::class)]);
        $outputProperties = self::getPropertyNames($classInfo, [fn($property) => $property->hasAttribute(OutputField::class)]);
        $this->input = new ObjectDataModel($this, $inputProperties);
        $this->output = new ObjectDataModel($this, $outputProperties);
    }

    static public function make(mixed ...$inputs) : static {
        if (empty($inputs)) {
            return new static;
        }
        return (new static)->withArgs(...$inputs);
    }

    public function input(): DataModel {
        return $this->input;
    }

    public function output(): DataModel {
        return $this->output;
    }

    public function description(): string {
        return $this->description;
    }

    public function toArray(): array {
        return array_merge(
            $this->input->getValues(),
            $this->output->getValues(),
        );
    }
}