<?php

namespace Cognesy\Instructor\Extras\Sequences\Traits;

use Cognesy\Instructor\Utils\Json;

trait HandlesDeserialization
{
    private $deserializer;

    public function fromJson(string $jsonData): static {
        $deserializer = $this->deserializer;
        $data = Json::parse($jsonData);
        $returnedList = $data['list'] ?? [];
        $list = [];
        foreach ($returnedList as $item) {
            $list[] = $deserializer->fromArray($item, $this->class);
        }
        $this->list = $list;
        return $this;
    }
}