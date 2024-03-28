<?php
namespace Cognesy\Instructor\Schema\Data\Schema;

use Cognesy\Instructor\Schema\Data\TypeDetails;

class ArraySchema extends Schema
{
    public Schema $nestedItemSchema;

    public function __construct(
        TypeDetails $type,
        string $name,
        string $description,
        Schema $nestedItemSchema,
    ) {
        parent::__construct($type, $name, $description);
        $this->nestedItemSchema = $nestedItemSchema;
    }

    public function toArray(callable $refCallback = null) : array
    {
        return array_filter([
            'type' => 'array',
            'items' => $this->nestedItemSchema->toArray($refCallback),
            'description' => $this->description,
        ]);
    }

    public function toXml() : string {
        $lines = [
            '<parameter>',
            '<name>'.$this->name.'</name>',
            '<type>array</type>',
            '<description>'.$this->description.'</description>',
            '<items>',
            $this->nestedItemSchema->toXml(),
            '</items>',
            '</parameter>',
        ];
        return implode("\n", $lines);
    }
}
