<?php

declare(strict_types=1);

namespace Cognesy\Xprompt\Tests\Fixtures\Prompts;

use Cognesy\Xprompt\Prompt;

class BlocksPrompt extends Prompt
{
    public string $templateFile = 'with_blocks.twig';
    public ?string $templateDir = __DIR__ . '/../templates';
    public array $blocks = [HeaderBlock::class, FooterBlock::class];
}
