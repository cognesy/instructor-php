<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

use Cognesy\Tell\Data\TellSessionRemoval;
use Cognesy\Tell\Data\TellSessionView;

interface CanManageTellSessions
{
    /** @return list<TellSessionView> */
    public function list(): array;

    public function show(string $id, bool $full = false): ?TellSessionView;

    public function remove(string $id): TellSessionRemoval;
}
