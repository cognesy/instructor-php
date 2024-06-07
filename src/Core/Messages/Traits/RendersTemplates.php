<?php

namespace Cognesy\Instructor\Core\Messages\Traits;

use Cognesy\Instructor\Core\Messages\Message;
use Cognesy\Instructor\Core\Messages\Messages;
use Cognesy\Instructor\Utils\Template;

trait RendersTemplates
{
    /**
     * @param string $template
     * @param array<string,mixed>|null $context
     * @return string
     */
    private function renderString(string $template, ?array $context) : string {
        return match(true) {
            is_null($context) => $template,
            default => (new Template($context))->renderString($template),
        };
    }

    /**
     * @param array<string,string|array>|Message $messages
     * @param array<string,mixed>|null $context
     * @return string
     */
    protected function renderMessage(array|Message $message, ?array $context) : array {
        return match(true) {
            is_null($context) => $message,
            default => (new Template($context))->renderMessage($message),
        };
    }

    /**
     * @param array<string,string|array>|Messages $messages
     * @param array<string,mixed>|null $context
     * @return string
     */
    protected function renderMessages(array|Messages $messages, ?array $context) : array {
        return match(true) {
            is_null($context) => $messages,
            default => (new Template($context))->renderMessages($messages),
        };
    }
}
