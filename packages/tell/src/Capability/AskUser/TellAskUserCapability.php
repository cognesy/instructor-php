<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\AskUser;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Override;

/** @implements CanProvideAgentCapability<CanConfigureAgent> */
final readonly class TellAskUserCapability implements CanProvideAgentCapability
{
    public function __construct(private TellAnswerQueue $answers) {}

    #[Override]
    public static function capabilityName(): string {
        return 'tell.ask_user';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return $agent->withTools($agent->tools()->withTool(new AskUserTool($this->answers)));
    }
}
