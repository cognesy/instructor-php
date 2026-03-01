<?php declare(strict_types=1);
namespace Cognesy\Dynamic\Traits\Field;

use Cognesy\Dynamic\Structure;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;

trait HandlesFieldSchema
{
    private Schema $schema;

    public function name() : string {
        return $this->schema->name;
    }

    public function withName(string $name) : self {
        $this->schema = $this->schema->withName($name);
        $structure = $this->structureValue();
        if ($structure !== null) {
            $structure->withName($name);
        }
        return $this;
    }

    public function description() : string {
        return $this->schema->description;
    }

    public function withDescription(string $description) : self {
        $this->schema = $this->schema->withDescription($description);
        $structure = $this->structureValue();
        if ($structure !== null) {
            $structure->withDescription($description);
        }
        return $this;
    }

    public function schema() : Schema {
        $structure = $this->structureValue();
        if ($this->isStructure() && $structure !== null) {
            return $structure->schema();
        }
        return $this->schema;
    }

    public function isStructure() : bool {
        return $this->schema->typeDetails->class === Structure::class;
    }

    public function typeDetails() : TypeDetails {
        return $this->schema->typeDetails;
    }

    public function nestedType() : TypeDetails {
        $nestedType = $this->schema->typeDetails->nestedType;
        if ($nestedType === null) {
            throw new \Exception('Field does not have a nested type');
        }
        return $nestedType;
    }

    private function structureValue() : ?Structure {
        $value = $this->get();
        return match(true) {
            $value instanceof Structure => $value,
            default => null,
        };
    }
}
