<?php
namespace Cognesy\Instructor\Schema;

class FCArray {
    public string $name = '';
    public string $type = 'array';
    public string $description = '';
    public FCObject|FCEnum|FCAtom $itemType;

    public function toArray() : array {
        $array = [];
        $array['type'] = $this->type;
        if ($this->description !== '') {
            $array['description'] = $this->description;
        }
        $array['items'] = $this->itemType->toArray();
        return $array;
    }
}
