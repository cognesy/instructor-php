<?php

declare(strict_types=1);

namespace Cognesy\Xprompt\Tests\Fixtures\Prompts;

use Cognesy\Xprompt\Prompt;

class HeaderBlock extends Prompt
{
    public bool $isBlock = true;

    public function body(mixed ...$ctx): string|array|null
    {
        $title = $ctx['title'] ?? 'Document';
        return "# {$title}";
    }
}
