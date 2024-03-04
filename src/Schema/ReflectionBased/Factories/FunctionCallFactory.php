<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Factories;

use Closure;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\ClassData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\FunctionData;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\MethodData;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

class FunctionCallFactory {
    public function fromClass(
        object|string $class,
        string $customName = 'extract_object',
        string $customDescription = 'Extract parameters from chat content'
    ) : array {
        $refClass = new ReflectionClass($class);
        $classData = new ClassData($refClass);
        return [
            'type' => 'function',
            'function' => [
                'name' => $customName,
                'description' => $customDescription,
                'parameters' => $classData->toStruct()->toArray(),
            ]
        ];
    }

    public function fromFunction(
        callable $function,
        string $customName = '',
        string $customDescription = ''
    ) : array {
        $refFunction = new ReflectionFunction($function);
        $functionData = new FunctionData($refFunction);
        $customName = $customName ?: $functionData->name;
        $customDescription = $customDescription ?: $functionData->description;
        return [
            'type' => 'function',
            'function' => $functionData->toStruct($customName, $customDescription)->toArray(),
        ];
    }

    public function fromMethod(
        Closure $method,
        string $customName = '',
        string $customDescription = ''
    ) : array {
        $refClosure = new ReflectionFunction($method);
        $object = $refClosure->getClosureThis();
        $methodName = $refClosure->getName();
        $refMethod = new ReflectionMethod($object, $methodName);
        $methodData = new MethodData($refMethod);
        $functionName = $customName ?: $methodData->name;
        $functionDescription = $customDescription ?: $methodData->description;
        return [
            'type' => 'function',
            'function' => $methodData->toStruct($functionName, $functionDescription)->toArray(),
        ];
    }
}
