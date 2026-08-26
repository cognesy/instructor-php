<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

interface CanDisposeTellModule
{
    public function dispose(): void;
}
