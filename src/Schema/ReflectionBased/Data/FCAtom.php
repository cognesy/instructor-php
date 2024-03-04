<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Data;

class FCAtom {
    public string $name = '';
    public string $type = '';
    public string $description = '';

    public function toArray() : array {
        $array = [];
        $array['type'] = $this->type;
        if ($this->description !== '') {
            $array['description'] = $this->description;
        }
        return $array;
    }
}
