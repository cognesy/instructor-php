<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
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