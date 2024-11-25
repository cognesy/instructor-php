<?php

namespace Cognesy\Instructor\Utils\Messages\Traits;

use Cognesy\Instructor\Extras\Prompt\Template;
use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;

trait RendersContent
{
    /**
     * @param string $template
     * @param array<string,mixed>|null $parameters
     * @return string
     */
    private function renderString(string $template, ?array $parameters) : string {
        return match(true) {
            empty($parameters) => $template,
            default => (new Template($parameters))->renderString($template),
        };
    }

    /**
     * @param array<string,string|array>|Message $messages
     * @param array<string,mixed>|null $parameters
     * @return string
     */
    protected function renderMessage(array|Message $message, ?array $parameters) : array {
        return match(true) {
            empty($parameters) => $message,
            default => (new Template($parameters))->renderMessage($message),
        };
    }

    /**
     * @param array<string,string|array>|Messages $messages
     * @param array<string,mixed>|null $parameters
     * @return string
     */
    protected function renderMessages(array|Messages $messages, ?array $parameters) : array {
        return match(true) {
            //empty($context) => $messages,
            default => (new Template($parameters))->renderMessages($messages),
        };
    }
}
