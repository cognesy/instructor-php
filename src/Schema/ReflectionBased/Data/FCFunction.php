<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Data;

class FCFunction {
    public string $name = '';
    public string $type = 'object';
    public string $description = '';
    public array $parameters = [];
    public array $required = [];

    public function toArray() : array {
        $array = [];
        $array['name'] = $this->name;
        if ($this->description !== '') {
            $array['description'] = $this->description;
        }
        if ($this->parameters !== []) {
            $array['parameters']['type'] = 'object';
        }
        foreach($this->parameters as $parameter) {
            $array['parameters']['properties'][$parameter->name] = $parameter->toArray();
        }
        if (count($this->required) > 0) {
            $array['parameters']['required'] = $this->required;
        }
        return $array;
    }
}
