<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Contracts;

interface CanManageStore
{
    public function setup(): void;

    public function drop(): void;
}
