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

    /**
     * Renders array schema
     */
    public function toArray(callable $refCallback = null) : array
    {
        return array_filter([
            'type' => 'array',
            'items' => $this->nestedItemSchema->toArray($refCallback),
            'description' => $this->description,
        ]);
    }

    public function toXml(bool $asArrayItem = false) : string {
        $xml = [];
        if (!$asArrayItem) {
            $xml[] = '<parameter>';
            $xml[] = '<name>'.$this->name.'</name>';
            $xml[] = '<type>array</type>';
            if ($this->description) {
                $xml[] = '<description>' . trim($this->description) . '</description>';
            }
            $xml[] = '<items>';
            $xml[] = $this->nestedItemSchema->toXml(true);
            $xml[] = '</items>';
            $xml[] = '</parameter>';
        } else {
            $xml[] = '<type>array</type>';
            if ($this->description) {
                $xml[] = '<description>' . trim($this->description) . '</description>';
            }
            $xml[] = '<items>';
            $xml[] = $this->nestedItemSchema->toXml(true);
            $xml[] = '</items>';
        }
        return implode($this->xmlLineSeparator, $xml);
    }
}
