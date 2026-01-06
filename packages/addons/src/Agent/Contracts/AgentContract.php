<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Contracts;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\Core\Data\AgentDescriptor;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\StepByStep\Contracts\CanExecuteIteratively;
use Cognesy\Utils\Result\Result;

/**
 * @extends CanExecuteIteratively<AgentState>
 */
interface AgentContract extends CanExecuteIteratively
{
    public function descriptor(): AgentDescriptor;

    public function build(): Agent;

    public function run(AgentState $state): AgentState;

    public function serializeConfig(): array;

    public static function fromConfig(array $config): Result;
}
