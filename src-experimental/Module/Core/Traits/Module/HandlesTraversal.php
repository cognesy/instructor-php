<?php

namespace Cognesy\Experimental\Module\Core\Traits\Module;

use Cognesy\Experimental\Module\Core\Module;
use Cognesy\Experimental\Module\Core\Predictor;
use Generator;
use ReflectionObject;

trait HandlesTraversal
{
    /**
     * @param string $path
     * @return Generator<string, \Cognesy\Experimental\Experimental\Module\Core\Module>
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
     * @return Generator<string, \Cognesy\Experimental\Module\Core\Predictor>
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
