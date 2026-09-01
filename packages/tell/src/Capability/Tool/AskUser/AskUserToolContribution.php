<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Tool\AskUser;

use Cognesy\Tell\Core\Agent\TellAgentAssembly;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;

final readonly class AskUserToolContribution implements CanContributeTellAgent
{
    #[\Override]
    public function contribute(TellAgentAssembly $assembly): void {
        $answers = new TellAnswerQueue($assembly->request->answers);
        $assembly->diagnostics?->trackWarnings(static function () use ($answers): array {
            $remaining = $answers->remaining();

            return match ($remaining) {
                0 => [],
                default => ["Unused non-interactive answers: {$remaining}."],
            };
        });
        $assembly->capabilities->register(
            TellAskUserCapability::capabilityName(),
            new TellAskUserCapability($answers),
        );
    }
}
