<?php
namespace Cognesy\Instructor\Extras\Module\Core;

use Cognesy\Instructor\ApiClient\Contracts\CanCallLLM;
use Cognesy\Instructor\Extras\Module\Contracts\CanInitiateModuleCall;
use Cognesy\Instructor\Instructor;

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
    use Traits\Module\HandlesCreation;
    use Traits\Module\HandlesTraversal;

    public static function with(mixed ...$constructorArgs) : static {
        return new static(...$constructorArgs);
    }

    public function using(
        Instructor $instructor = null,
        CanCallLLM $client = null,
    ) : static {
        foreach($this->predictors() as $predictor) {
            $predictor->using(instructor: $instructor, client: $client);
        }
        return $this;
    }

    public function __invoke(mixed ...$callArgs): ModuleCall {
        return new ModuleCall(
            inputs: $callArgs,
            moduleCall: fn(...$args) => $this->forward(...$callArgs)
        );
    }

    public function call(mixed ...$callArgs): array {
        return $this($callArgs)->get();
    }

    abstract protected function forward(mixed ...$callArgs): array;
}
