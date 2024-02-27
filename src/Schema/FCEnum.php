<?php
namespace Cognesy\Instructor\Schema;

class FCEnum {
    public string $name = '';
    public string $type = 'string';
    public string $description = '';
    public array $values = [];

    public function toArray() : array {
        $array = [];
        $array['type'] = $this->type;
        $array['enum'] = $this->values;
        if ($this->description !== '') {
            $array['description'] = $this->description;
        }
        return $array;
    }
}
