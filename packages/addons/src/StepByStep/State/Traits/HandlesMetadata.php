<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Traits;

use Cognesy\Utils\Metadata;

trait HandlesMetadata
{
    protected readonly Metadata $metadata;

    public function metadata(): Metadata {
        return $this->metadata;
    }

    public function withMetadata(string $name, mixed $value): static {
        return $this->with(variables: $this->metadata->withKeyValue($name, $value));
    }
}