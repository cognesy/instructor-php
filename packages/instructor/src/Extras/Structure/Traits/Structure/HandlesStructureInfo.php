<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Structure\Traits\Structure;

trait HandlesStructureInfo
{
    public function withName(string $name) : self {
        $this->name = $name;
        return $this;
    }

    public function name() : string {
        return $this->name;
    }

    public function withDescription(string $description) : self {
        $this->description = $description;
        return $this;
    }

    public function description() : string {
        return $this->description;
    }
}