<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Data\TellWorkspaceInfo;

interface CanManageTellWorkspace
{
    public function initialize(string $directory): TellWorkspaceInfo;

    public function discover(string $directory): ?TellWorkspaceInfo;

    public function validate(string $directory): TellWorkspaceInfo;
}
