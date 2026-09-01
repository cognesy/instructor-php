<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Execution;

interface CanDisposeTellResources
{
    public function dispose(): void;
}
