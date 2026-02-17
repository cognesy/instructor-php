<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Core;

use Cognesy\Experimental\ModPredict\Contracts\CanInitiateModuleCall;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Generator;
use InvalidArgumentException;
use ReflectionObject;

/**
 * Why Module is designed this way
 * -------------------------------
 * We need a way to call some operation with arbitrary signature and one or more outputs.
 * We need to execute internal logic before and/or after the operation.
 * We want to block direct access to main execution method forward(), so it is not called directly.
 * The reason we don't want forward() to be called directly is because we need some pre- and post-processing.
 * We also want unified interface - arguments are always an array, outputs are always an array.
 * The reason we want unified interface is it will help us to use modules in a pipeline or as graph nodes.
 * But we also want to give developers type safe module calling. This the goal of for() method, which
 * is defined by Module author and can be called by the user. For has to call forward() via __invoke(),
 * as it provides additional processing needed for modules, while forward() does only the core logic.
 *
 * Why we don't use functions - like e.g. Hamilton
 * ------------------------------------------------------
 * Sometimes module logic may be complex and class provides a better way to structure the code. It
 * also allows for Modules with additional dependencies and state. And it allows structuring complex code
 * with traits.
 *
 */
abstract class Module implements CanInitiateModuleCall
{
    public static function factory(string $class, mixed ...$constructorArgs) : static {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Class `$class` not found");
        }
        if (!is_subclass_of($class, Module::class)) {
            throw new InvalidArgumentException("Class `$class` is not a subclass of " . Module::class);
        }
        return $class::with(...$constructorArgs);
    }

    public static function with(mixed ...$constructorArgs) : static {
        return new static(...$constructorArgs);
    }

    public function using(
        ?CanCreateStructuredOutput $structuredOutput = null,
    ) : static {
        foreach($this->predictors() as $predictor) {
            $predictor->with(structuredOutput: $structuredOutput);
        }
        return $this;
    }

    #[\Override]
    public function __invoke(mixed ...$callArgs): ModuleCall {
        return new ModuleCall(
            inputs: $callArgs,
            moduleCall: fn(...$args) => $this->forward(...$callArgs)
        );
    }

    public function call(mixed ...$callArgs): array {
        $args = (count($callArgs) === 1 && is_array($callArgs[0]))
            ? $callArgs[0]
            : $callArgs;
        return $this->__invoke($args)->outputs();
    }

    abstract protected function forward(mixed ...$callArgs): array;

    /**
     * @param string $path
     * @return Generator<string, Module>
     */
    public function submodules(string $path = '') : Generator {
        foreach ($this->getProperties() as $name => $value) {
            if ($value instanceof Module) {
                yield from $value->submodules($this->varPath($path, $name));
                yield $this->varPath($path, $name) => $value;
            }
        }
    }

    /**
     * @param string $path
     * @return Generator<string, Predictor>
     */
    public function predictors(string $path = '') : Generator {
        foreach ($this->submodules() as $modulePath => $module) {
            yield from $module->predictors($modulePath);
        }
        foreach (get_object_vars($this) as $name => $value) {
            if ($value instanceof Predictor) {
                yield $path . '.' . $name => $value;
            }
        }
    }

    // INTERNAL /////////////////////////////////////////////////////////////////

    private function varPath(string $path, string $name) : string {
        return $path . '.' . $name;
    }

    private function getProperties() : array {
        $objectReflection = new ReflectionObject($this);
        $propertyReflection = $objectReflection->getProperties();
        $properties = array_map(fn($property) => $property->getName(), $propertyReflection);
        $values = array_map(fn($property) => $this->{$property->getName()}, $propertyReflection);
        return array_combine($properties, $values);
    }
}
