<?php
namespace Cognesy\Instructor\Extras\Field\Traits;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;

trait HandlesFieldSchema
{
    private Schema $schema;

    public function name() : string {
        return $this->schema->name ?? '';
    }

    public function withName(string $name) : self {
        $this->schema->name = $name;
        // TODO: revise this
        if ($this->schema->typeDetails->class === Structure::class) {
            $this->value->withName($name);
        }
        return $this;
    }

    public function description() : string {
        return $this->schema->description ?? '';
    }

    public function withDescription(string $description) : self {
        $this->schema->description = $description;
        // TODO: revise this
        if ($this->schema->typeDetails->class === Structure::class) {
            $this->value->withDescription($description);
        }
        return $this;
    }

    public function schema() : Schema {
        return $this->schema;
    }

    public function typeDetails() : TypeDetails {
        return $this->schema->typeDetails;
    }

    public function nestedType() : TypeDetails {
        return $this->schema->typeDetails->nestedType;
    }
}