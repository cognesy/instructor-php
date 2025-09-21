<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step\Contracts;

use Cognesy\Utils\Metadata;

interface HasStepMetadata
{
    public function metadata(): Metadata;
}