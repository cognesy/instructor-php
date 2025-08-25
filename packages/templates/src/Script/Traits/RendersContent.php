<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Template\Template;

trait RendersContent
{
    /**
     * @param string $template
     * @param array<string,mixed>|null $parameters
     * @return string
     */
    protected function renderString(string $template, ?array $parameters) : string {
        return match(true) {
            empty($parameters) => $template,
            default => Template::arrowpipe()->from($template)->with($parameters)->toText(),
        };
    }

    /**
     * @param Message $message
     * @param array<string,mixed>|null $parameters
     * @return Message
     */
    protected function renderMessage(Message $message, ?array $parameters) : Message {
        return match(true) {
            empty($parameters) => $message,
            default => Template::arrowpipe()->with($parameters)->renderMessage($message),
        };
    }

    /**
     * @param Messages $messages
     * @param array<string,mixed>|null $parameters
     * @return Messages
     */
    protected function renderMessages(Messages $messages, ?array $parameters) : Messages {
        return match(true) {
            //empty($context) => $messages,
            default => Template::arrowpipe()->with($parameters)->renderMessages($messages),
        };
    }
}
