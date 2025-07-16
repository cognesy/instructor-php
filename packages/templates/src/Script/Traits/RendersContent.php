<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits;

use Cognesy\Template\Template;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

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
     * @param array<string,string|array>|\Cognesy\Messages\Message $messages
     * @param array<string,mixed>|null $parameters
     * @return string
     */
    protected function renderMessage(Message $message, ?array $parameters) : Message {
        return match(true) {
            empty($parameters) => $message,
            default => Template::arrowpipe()->with($parameters)->renderMessage($message),
        };
    }

    /**
     * @param array<string,string|array>|\Cognesy\Messages\Messages $messages
     * @param array<string,mixed>|null $parameters
     * @return string
     */
    protected function renderMessages(Messages $messages, ?array $parameters) : Messages {
        return match(true) {
            //empty($context) => $messages,
            default => Template::arrowpipe()->with($parameters)->renderMessages($messages),
        };
    }
}
