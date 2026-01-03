<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Sequence\Traits;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;

trait HandlesDeserialization
{
    private CanDeserializeClass $deserializer;

    #[\Override]
    public function fromArray(array $data, ?string $toolName = null): static {
        $deserializer = $this->deserializer;

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
