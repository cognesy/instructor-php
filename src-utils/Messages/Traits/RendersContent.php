<?php

namespace Cognesy\Utils\Messages\Traits;

use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Cognesy\Utils\Template\Template;

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
     * @param array<string,string|array>|\Cognesy\Utils\Messages\Message $messages
     * @param array<string,mixed>|null $parameters
     * @return string
     */
    protected function renderMessage(array|Message $message, ?array $parameters) : array {
        return match(true) {
            empty($parameters) => $message,
            default => Template::arrowpipe()->with($parameters)->renderMessage($message),
        };
    }

    /**
     * @param array<string,string|array>|\Cognesy\Utils\Messages\Messages $messages
     * @param array<string,mixed>|null $parameters
     * @return string
     */
    protected function renderMessages(array|Messages $messages, ?array $parameters) : array {
        return match(true) {
            //empty($context) => $messages,
            default => Template::arrowpipe()->with($parameters)->renderMessages($messages),
        };
    }
}
