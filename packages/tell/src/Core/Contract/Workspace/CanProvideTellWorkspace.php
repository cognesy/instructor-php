<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

/** Complete workspace provider required by the standard Tell profiles. */
interface CanProvideTellWorkspace extends CanOpenTellWorkspace, CanReadTellBranchConfiguration
{}
