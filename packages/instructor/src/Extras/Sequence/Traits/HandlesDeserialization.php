<?php

namespace Cognesy\Instructor\Extras\Sequence\Traits;

use Cognesy\Instructor\Features\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Utils\Json\Json;

trait HandlesDeserialization
{
    private SymfonyDeserializer $deserializer;

    public function fromJson(string $jsonData, ?string $toolName = null): static {
        $deserializer = $this->deserializer;
        $data = Json::decode($jsonData);

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