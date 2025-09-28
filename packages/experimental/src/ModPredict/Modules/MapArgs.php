<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Modules;

use Closure;
use Cognesy\Experimental\ModPredict\Core\Module;
use Cognesy\Experimental\Signature\Attributes\ModuleDescription;
use Cognesy\Experimental\Signature\Attributes\ModuleSignature;
use InvalidArgumentException;

#[ModuleSignature('arguments:array -> mapped_arguments:array')]
#[ModuleDescription('Map arguments to the expected format')]
class MapArgs extends Module
{
    protected Closure $mapping;

    public function __construct(array|Closure $mapping = []) {
        $this->mapping = match(true) {
            is_array($mapping) => function($args) use ($mapping) { return $this->defaultMapping($mapping, $args); },
            is_callable($mapping) => $mapping,
            default => throw new InvalidArgumentException('Invalid mapping provided'),
        };
    }

    protected function forward(mixed ...$callArgs) : array {
        if (empty($this->mapping)) {
            return $callArgs;
        }
        return ($this->mapping)($callArgs);
    }

    protected function defaultMapping(array $mapping, array $callArgs) : array {
        $mapped = [];
        foreach ($mapping as $key => $value) {
            $mapped[$key] = match(true) {
                is_callable($value) => $value($callArgs[$key]),
                default => $callArgs[$value],
            };
        }
        return $mapped;
    }
}
