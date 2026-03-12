<?php

declare(strict_types=1);

namespace Cognesy\Xprompt\Tests\Fixtures\Prompts;

use Cognesy\Xprompt\Prompt;

class FooterBlock extends Prompt
{
    public bool $isBlock = true;

    public function body(mixed ...$ctx): string|array|null
    {
        return '---\nEnd of document.';
    }
}
