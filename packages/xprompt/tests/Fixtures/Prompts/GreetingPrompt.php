<?php

declare(strict_types=1);

namespace Cognesy\Xprompt\Tests\Fixtures\Prompts;

use Cognesy\Xprompt\Prompt;

class GreetingPrompt extends Prompt
{
    public string $templateFile = 'greeting.twig';
    public ?string $templateDir = __DIR__ . '/../templates';
}
