<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Contracts;

interface CanContributeToPrompt
{
    public function promptSnippet(): ?string;

    /** @return list<string> */
    public function promptGuidelines(): array;
}
