<?php
namespace Cognesy\Instructor\Schema;

use Closure;
use Cognesy\Instructor\Reflection\ClassData;
use Cognesy\Instructor\Reflection\FunctionData;
use Cognesy\Instructor\Reflection\MethodData;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

class FunctionCallSchema {
    public function withClass(
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

    public function withFunction(
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

    public function withMethod(
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
