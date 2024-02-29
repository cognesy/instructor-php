<?php
namespace Cognesy\Experimental;

use Cognesy\Instructor\Reflection\Enums\JsonType;
use Cognesy\Instructor\Reflection\Enums\PhpType;
use ReflectionEnum;

class SimpleFunctionCallSchema
{
    public function make(
        string $name = '',
        string $description = '',
        string $argName = '',
        string $argDescription = '',
        PhpType|BackedEnum $argType = PhpType::STRING,
    ) : array {
        $functionData = new \Cognesy\Instructor\Schema\FCFunction();
        $functionData->name = $name;
        $functionData->description = $description;
        $functionData->parameters = [
            'type' => 'object',
            'properties' => [
                $argName => [
                    'description' => $argDescription,
                    'type' => JsonType::fromPhpType($argType)->value,
                ],
            ],
        ];
        if ($argType === 'enum') {
            $values = array_values((new ReflectionEnum($argType))->getConstants());
            $functionData->parameters['properties'][$argName]['enum'] = $values;
        }
        $functionData->required[] = $argName;
        return [
            'type' => 'function',
            'function' => $functionData,
        ];
    }
}