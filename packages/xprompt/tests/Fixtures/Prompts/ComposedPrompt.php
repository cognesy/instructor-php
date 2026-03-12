<?php

declare(strict_types=1);

namespace Cognesy\Xprompt\Tests\Fixtures\Prompts;

use Cognesy\Xprompt\Prompt;

class ComposedPrompt extends Prompt
{
    public function body(mixed ...$ctx): string|array|null
    {
        return [
            HeaderBlock::make(),
            GreetingPrompt::make(),
            $ctx['include_footer'] ?? false ? FooterBlock::make() : null,
        ];
    }
}
