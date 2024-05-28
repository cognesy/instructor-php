<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature;

use Cognesy\Instructor\Contracts\DataModel\CanHandleDataStructure;
use Cognesy\Instructor\Extras\Signature\Schema;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;

class AutoSignature implements Signature
{
    use \Cognesy\Instructor\Extras\Tasks\Signature\Traits\HandlesAutoConfig;
    use \Cognesy\Instructor\Extras\Tasks\Signature\Traits\ConvertsToString;

    public const ARROW = '->';

    protected CanHandleDataStructure $inputs;
    protected CanHandleDataStructure $outputs;
    protected string $description = '';
    protected string $prompt = 'Your task is to find output arguments in input data based on specification: {signature} {description}';

    public function __construct(
        string $description = null,
    ) {
        if (!is_null($description)) {
            $this->description = $description;
        }
        $this->autoConfigure();
    }

    public function getInputValues(): array {
        return $this->inputs->fieldValues();
    }

    /** @return \Cognesy\Instructor\Contracts\DataModel\CanHandleDataField[] */
    public function getInputFields(): array {
        return $this->inputs->fields();
    }

    /** @return \Cognesy\Instructor\Contracts\DataModel\CanHandleDataField[] */
    public function getOutputFields(): array {
        return $this->outputs->fields();
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getInputSchema(): Schema {
    }

    public function getOutputSchema(): Schema {
    }

//    public function getInputSchema(): Schema {
//        $schema = (new SchemaFactory)->schema(static::class);
//        $properties = array_map(function($property) {
//            return $property->getName();
//        }, array_filter(
//            (new ClassInfo(static::class))->getProperties(), function($property) {
//                return $property->isPublic() && !$property->isStatic() && $property->hasAttribute(InputField::class);
//            })
//        );
//        foreach ($properties as $name) {
//            if (!in_array($name, $properties)) {
//                $schema->removeProperty($name);
//            }
//        }
//        return $schema;
//    }

//    public function getOutputSchema(): Schema {
//        $schema = (new SchemaFactory)->schema(static::class);
//        $properties = array_map(function($property) {
//            return $property->getName();
//        }, array_filter(
//                (new ClassInfo(static::class))->getProperties(), function($property) {
//                return $property->isPublic() && !$property->isStatic() && $property->hasAttribute(OutputField::class);
//            })
//        );
//        foreach ($properties as $name) {
//            if (!in_array($name, $properties)) {
//                $schema->removeProperty($name);
//            }
//        }
//        return $schema;
//    }

//    public function toArray(): array {
//        $properties = array_map(function($property) {
//            return $property->getName();
//        }, array_filter(
//                (new ClassInfo(static::class))->getProperties(), function($property) {
//                return $property->isPublic() && !$property->isStatic() && $property->hasAttribute(OutputField::class);
//            })
//        );
//        $result = [];
//        foreach ($properties as $name) {
//            $result[$name] = $this->$name;
//        }
//        return $result;
//    }
}