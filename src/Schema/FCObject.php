<?php
namespace Cognesy\Instructor\Schema;

class FCObject {
    public string $name = '';
    public string $type = 'object';
    public string $description = '';
    public array $properties = [];
    public array $required = [];

    public function toArray() : array {
        $array = [];
        $array['type'] = $this->type;
        if ($this->description !== '') {
            $array['description'] = $this->description;
        }
        foreach($this->properties as $property) {
            $array['properties'][$property->name] = $property->toArray();
        }
        if (count($this->required) > 0) {
            $array['required'] = $this->required;
        }
        return $array;
    }
}
