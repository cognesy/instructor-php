<?php

namespace Cognesy\Instructor\Features\Schema\Visitors;

use Cognesy\Instructor\Features\Schema\Contracts\CanVisitSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\ArrayShapeSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\CollectionSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\EnumSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\ObjectRefSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class SchemaToXml implements CanVisitSchema
{
    private string $xmlLineSeparator = "\n";
    private bool $asArrayItem = false;
    private array $xml = [];

    public function toXml(Schema $schema, bool $asArrayItem = false): string {
        $this->asArrayItem = $asArrayItem;
        $schema->accept($this);
        return implode($this->xmlLineSeparator, $this->xml);
    }

    public function visitSchema(Schema $schema): void {
        $this->xml[] = '';
    }

    public function visitArraySchema(ArraySchema $schema): void {
        $xml = [];
        if (!$this->asArrayItem) {
            $xml[] = '<parameter>';
            $xml[] = '<name>'.$schema->name.'</name>';
            $xml[] = '<type>array</type>';
            if ($schema->description) {
                $xml[] = '<description>' . trim($schema->description) . '</description>';
            }
            $xml[] = '</parameter>';
        } else {
            $xml[] = '<type>array</type>';
            if ($schema->description) {
                $xml[] = '<description>' . trim($schema->description) . '</description>';
            }
        }
        $this->xml[] = implode($this->xmlLineSeparator, $xml);
    }

    public function visitCollectionSchema(CollectionSchema $schema): void {
        $xml = [];
        if (!$this->asArrayItem) {
            $xml[] = '<parameter>';
            $xml[] = '<name>'.$schema->name.'</name>';
            $xml[] = '<type>array</type>';
            if ($schema->description) {
                $xml[] = '<description>' . trim($schema->description) . '</description>';
            }
            $xml[] = '<items>';
            $xml[] = (new SchemaToXml)->toXml($schema->nestedItemSchema, true);
            $xml[] = '</items>';
            $xml[] = '</parameter>';
        } else {
            $xml[] = '<type>array</type>';
            if ($schema->description) {
                $xml[] = '<description>' . trim($schema->description) . '</description>';
            }
            $xml[] = '<items>';
            $xml[] = (new SchemaToXml)->toXml($schema->nestedItemSchema, true);
            $xml[] = '</items>';
        }
        $this->xml[] = implode($this->xmlLineSeparator, $xml);
    }

    public function visitObjectSchema(ObjectSchema $schema): void {
        $xml = [];
        if (!$this->asArrayItem) {
            $xml[] = '<parameter>';
            $xml[] = '<name>'.$schema->name.'</name>';
            $xml[] = '<type>object</type>';
            if ($schema->description) {
                $xml[] = '<description>'.trim($schema->description).'</description>';
            }
            $xml[] = '<properties>';
            $childrenXml = [];
            foreach ($schema->properties as $property) {
                $childrenXml[] = (new SchemaToXml)->toXml($property);
            }
            $xml[] = implode($this->xmlLineSeparator, $childrenXml);
            $xml[] = '</properties>';
            $xml[] = '</parameter>';
        } else {
            $xml[] = '<type>object</type>';
            if ($schema->description) {
                $xml[] = '<description>'.trim($schema->description).'</description>';
            }
            $xml[] = '<properties>';
            $childrenXml = [];
            foreach ($schema->properties as $property) {
                $childrenXml[] = (new SchemaToXml)->toXml($property);
            }
            $xml[] = implode($this->xmlLineSeparator, $childrenXml);
            $xml[] = '</properties>';
        }
        $this->xml[] = implode($this->xmlLineSeparator, $xml);
    }

    public function visitArrayShapeSchema(ArrayShapeSchema $schema): void {
        $xml = [];
        if (!$this->asArrayItem) {
            $xml[] = '<parameter>';
            $xml[] = '<name>'.$schema->name.'</name>';
            $xml[] = '<type>array-shape</type>';
            if ($schema->description) {
                $xml[] = '<description>'.trim($schema->description).'</description>';
            }
            $xml[] = '<properties>';
            $childrenXml = [];
            foreach ($schema->properties as $property) {
                $childrenXml[] = (new SchemaToXml)->toXml($property);
            }
            $xml[] = implode($this->xmlLineSeparator, $childrenXml);
            $xml[] = '</properties>';
            $xml[] = '</parameter>';
        } else {
            $xml[] = '<type>array-shape</type>';
            if ($schema->description) {
                $xml[] = '<description>'.trim($schema->description).'</description>';
            }
            $xml[] = '<properties>';
            $childrenXml = [];
            foreach ($schema->properties as $property) {
                $childrenXml[] = (new SchemaToXml)->toXml($property);
            }
            $xml[] = implode($this->xmlLineSeparator, $childrenXml);
            $xml[] = '</properties>';
        }
        $this->xml[] = implode($this->xmlLineSeparator, $xml);
    }

    public function visitEnumSchema(EnumSchema $schema): void {
        $xml = [];
        if (!$this->asArrayItem) {
            $xml[] = '<parameter>';
            $xml[] = '<name>'.$schema->name.'</name>';
            $xml[] = '<type>'.$schema->typeDetails->enumType.'</type>';
            if ($schema->description) {
                $xml[] = '<description>'.trim($schema->description).'</description>';
            }
            $xml[] = '<enum>';
            $xml[] = implode($this->xmlLineSeparator, array_map(fn($v) => '<value>'.$v.'</value>', $schema->typeDetails->enumValues));
            $xml[] = '</enum>';
            $xml[] = '</parameter>';
        } else {
            $xml[] = '<type>'.$schema->typeDetails->enumType.'</type>';
            if ($schema->description) {
                $xml[] = '<description>'.trim($schema->description).'</description>';
            }
            $xml[] = '<enum>';
            $xml[] = implode($this->xmlLineSeparator, array_map(fn($v) => '<value>'.$v.'</value>', $schema->typeDetails->enumValues));
            $xml[] = '</enum>';
        }
        $this->xml[] = implode($this->xmlLineSeparator, $xml);
    }

    public function visitScalarSchema(ScalarSchema $schema): void {
        $xml = [];
        if (!$this->asArrayItem) {
            $xml[] = '<parameter>';
            $xml[] = '<name>'.$schema->name.'</name>';
            $xml[] = '<type>'.$schema->typeDetails->jsonType().'</type>';
            if ($schema->description) {
                $xml[] = '<description>'.trim($schema->description).'</description>';
            }
            $xml[] = '</parameter>';
        } else {
            $xml[] = '<type>'.$schema->typeDetails->jsonType().'</type>';
            if ($schema->description) {
                $xml[] = '<description>'.trim($schema->description).'</description>';
            }
        }
        $this->xml[] = implode($this->xmlLineSeparator, $xml);
    }

    public function visitObjectRefSchema(ObjectRefSchema $schema): void {
        $this->xml[] = '';
    }
}