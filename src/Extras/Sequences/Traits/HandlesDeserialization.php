<?php

namespace Cognesy\Instructor\Extras\Sequences\Traits;

use Cognesy\Instructor\Deserialization\Symfony\Deserializer;
use Cognesy\Instructor\Utils\Json;

trait HandlesDeserialization
{
    private Deserializer $deserializer;

    public function fromJson(string $jsonData): static {
        $deserializer = $this->deserializer;
        $data = Json::parse($jsonData);

        // $data['properties']['list'] is workaround for models
        // which do not support JSON Schema tool calling natively
        // but still can generate JSON following the schema
        $returnedList = $data['list'] ?? $data['properties']['list'] ?? [];

        $list = [];
        foreach ($returnedList as $item) {
            $list[] = $deserializer->fromArray($item, $this->class);
        }
        $this->list = $list;
        return $this;
    }
}