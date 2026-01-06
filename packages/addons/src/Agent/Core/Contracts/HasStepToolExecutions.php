<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Core\Contracts;

use Cognesy\Addons\Agent\Core\Collections\ToolExecutions;
use Cognesy\Messages\Messages;
use Throwable;

interface HasStepToolExecutions
{
    public function toolExecutions(): ToolExecutions;
    public function errorExecutions(): ToolExecutions;
    public static function failure(Messages $inputMessages, Throwable $error): self;
}