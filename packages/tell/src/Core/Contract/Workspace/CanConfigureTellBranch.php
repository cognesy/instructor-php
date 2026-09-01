<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

use Cognesy\Tell\Data\TellBranchConfig;
use Cognesy\Tell\Data\TellRequest;

interface CanConfigureTellBranch
{
    /** @return list<string> */
    public function allowedKeys(): array;

    public function show(): TellBranchConfig;

    public function effective(?TellRequest $request = null): TellBranchConfig;

    public function set(string $key, mixed $value, int $expectedVersion): TellBranchConfig;

    public function delete(string $key, int $expectedVersion): TellBranchConfig;
}
