<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\TaskData\ObjectDataModel;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\DataModel;


class AutoSignature implements Signature, CanProvideSchema
{
    use Traits\GetsPropertyNamesFromClass;
    use Traits\GetsFieldsFromClass;
    use Traits\ConvertsToSignatureString;
    use Traits\InitializesSignatureInputs;

    private DataModel $input;
    private DataModel $output;
    private string $description;

    public function __construct() {
        $classInfo = new ClassInfo(static::class);
        $this->description = $classInfo->getClassDescription();
        $fields = self::getPropertyNames($classInfo);
        $this->input = new ObjectDataModel($this, $fields['inputs']);
        $this->output = new ObjectDataModel($this, $fields['outputs']);
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

    public function toSchema(): Schema {
        $classInfo = new ClassInfo(static::class);
        $fields = self::getFields($classInfo);
        $required = [];
        $properties = [];
        foreach($fields['outputs'] as $field) {
            $properties[$field->name()] = $field->schema();
            if ($field->isRequired()) {
                $required[] = $field->name();
            }
        }
        $typeDetails = (new TypeDetailsFactory)->objectType(static::class);
        $objectSchema = new ObjectSchema(
            $typeDetails,
            static::class,
            $classInfo->getClassDescription(),
            $properties,
            $required,
        );
        return $objectSchema;
    }

    public function toArray(): array {
        $toArray = function($x) use(&$toArray) {
            return (is_scalar($x) || is_null($x))
                ? $x
                : array_map($toArray, (array) $x);
        };
        return $toArray($this->output()->getValues());
    }
}