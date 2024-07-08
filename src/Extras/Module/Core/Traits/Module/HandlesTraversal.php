<?php

namespace Cognesy\Instructor\Extras\Module\Core\Traits\Module;

use Cognesy\Instructor\Extras\Module\Core\Module;
use Cognesy\Instructor\Extras\Module\Core\Predictor;
use Generator;

trait HandlesTraversal
{
    protected function submodules(string $path = '') : Generator {
        foreach (get_object_vars($this) as $name => $value) {
            if ($value instanceof Module) {
                yield from $value->submodules($this->varPath($path, $name));
                yield $this->varPath($path, $name) => $value;
            }
        }
    }

    protected function predictors(string $path = '') : Generator {
        foreach ($this->submodules() as $modulePath => $module) {
            yield from $module->predictors($modulePath);
        }
        foreach (get_object_vars($this) as $name => $value) {
            if ($value instanceof Predictor) {
                yield $path . '.' . $name => $value;
            }
        }
    }

    private function varPath(string $path, string $name) : string {
        return $path . '.' . $name;
    }
}