<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step\Traits;

use Cognesy\Utils\Metadata;

trait HandlesStepMetadata
{
    protected readonly Metadata $metadata;

    public function metadata(): Metadata {
        return $this->metadata;
    }
}