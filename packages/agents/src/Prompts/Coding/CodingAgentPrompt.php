<?php

declare(strict_types=1);

namespace Cognesy\Agents\Prompts\Coding;

use Cognesy\Xprompt\Prompt;
use Override;

/** @deprecated Use Cognesy\Agents\Capability\Prompt\UseSystemPrompt instead. */
final class CodingAgentPrompt extends Prompt
{
    public string $templateFile = 'coding-agent.md.twig';
    public ?string $templateDir = __DIR__ . '/../../../resources/prompts/coding';

    #[Override]
    public function body(mixed ...$ctx): string|array|null {
        $ctx['documentation_path'] = $ctx['documentation_path'] ?? '';

        return $this->renderTemplate(...$ctx);
    }
}
