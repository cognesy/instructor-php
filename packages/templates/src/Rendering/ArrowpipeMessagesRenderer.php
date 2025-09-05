<?php declare(strict_types=1);

namespace Cognesy\Template\Rendering;

use Cognesy\Messages\Contracts\CanRenderMessages;
use Cognesy\Messages\Messages;
use Cognesy\Template\Template;

final class ArrowpipeMessagesRenderer implements CanRenderMessages
{
    /**
     * @param array<string,mixed> $parameters
     */
    public function renderMessages(Messages $messages, array $parameters = []) : Messages
    {
        // Use Arrowpipe template engine to render text parts within messages
        // by delegating to the existing Template adapter.
        $engine = Template::arrowpipe()->withValues($parameters);
        return $engine->renderMessages($messages);
    }
}

