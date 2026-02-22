<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers;

use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;

interface CanAcceptToolRuntime
{
    public function withToolRuntime(Tools $tools, CanExecuteToolCalls $executor): static;
}
