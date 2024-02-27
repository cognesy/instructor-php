<?php
namespace Cognesy\Instructor\Reflection\Factories;

use Cognesy\Instructor\Reflection\Enums\PhpType;
use Cognesy\Instructor\Reflection\ParameterData\ArrayParameterData;
use Cognesy\Instructor\Reflection\ParameterData\BooleanParameterData;
use Cognesy\Instructor\Reflection\ParameterData\EnumParameterData;
use Cognesy\Instructor\Reflection\ParameterData\FloatParameterData;
use Cognesy\Instructor\Reflection\ParameterData\IntegerParameterData;
use Cognesy\Instructor\Reflection\ParameterData\ObjectParameterData;
use Cognesy\Instructor\Reflection\ParameterData\ParameterData;
use Cognesy\Instructor\Reflection\ParameterData\StringParameterData;
use Cognesy\Instructor\Reflection\Utils\ReflectionUtils;
use Exception;
use ReflectionParameter;

class ParameterDataFactory {
    public static function make(ReflectionParameter $parameter) : ParameterData {
        return (new ParameterDataFactory())->makeAny($parameter);
    }

    public function makeAny(ReflectionParameter $parameter) : ParameterData {
        return match ($this->getType($parameter)) {
            PhpType::STRING => new StringParameterData($parameter),
            PhpType::INTEGER => new IntegerParameterData($parameter),
            PhpType::FLOAT => new FloatParameterData($parameter),
            PhpType::BOOLEAN => new BooleanParameterData($parameter),
            PhpType::OBJECT => new ObjectParameterData($parameter),
            PhpType::ENUM => new EnumParameterData($parameter),
            PhpType::ARRAY => new ArrayParameterData($parameter),
            default => throw new Exception('Unsupported type: ' . $parameter->getType()?->getName())
        };
    }

    protected function getType(ReflectionParameter $parameter) : PhpType
    {
        $type = $parameter->getType();
        if ($type === null) {
            throw new Exception('Property type is not set: ' . $parameter->getName());
        }
        return ReflectionUtils::getType($type);
    }
}