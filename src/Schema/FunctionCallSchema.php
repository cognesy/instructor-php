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
    public string $functionName = '';
    public string $description = '';

    public function withClass(
        object|string $class,
        string $functionName = 'extract_object',
        string $description = 'Extract parameters from chat content'
    ) : array {
        $refClass = new ReflectionClass($class);
        $classData = new ClassData($refClass);
        return [
            'type' => 'function',
            'function' => [
                'name' => $functionName,
                'description' => $description,
                'parameters' => $classData->toStruct()->toArray(),
            ]
        ];
    }

    public function withFunction(
        callable $function,
        string $functionName = '',
        string $description = ''
    ) : array {
        $refFunction = new ReflectionFunction($function);
        $functionData = new FunctionData($refFunction);
        return [
            'type' => 'function',
            'function' => $functionData->toStruct($functionName, $description)->toArray(),
        ];
    }

    public function withMethod(
        Closure $method,
        string $functionName = '',
        string $description = ''
    ) : array {
        $refClosure = new ReflectionFunction($method);
        $object = $refClosure->getClosureThis();
        $methodName = $refClosure->getName();
        $refMethod = new ReflectionMethod($object, $methodName);
        $methodData = new MethodData($refMethod);
        return [
            'type' => 'function',
            'function' => $methodData->toStruct($functionName, $description)->toArray(),
        ];
    }
}
