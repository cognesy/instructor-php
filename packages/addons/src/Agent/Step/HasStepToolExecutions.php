<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Step;

use Cognesy\Addons\Agent\Collections\ToolExecutions;
use Cognesy\Messages\Messages;
use Throwable;

interface HasStepToolExecutions
{
    public function toolExecutions(): ToolExecutions;
    public function errorExecutions(): ToolExecutions;
    public static function failure(Messages $inputMessages, Throwable $error): self;
}