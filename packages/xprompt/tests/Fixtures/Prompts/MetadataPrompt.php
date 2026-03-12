<?php

declare(strict_types=1);

namespace Cognesy\Xprompt\Tests\Fixtures\Prompts;

use Cognesy\Xprompt\Prompt;

class MetadataPrompt extends Prompt
{
    public string $model = 'sonnet';
    public string $templateFile = 'with_frontmatter.twig';
    public ?string $templateDir = __DIR__ . '/../templates';
}
