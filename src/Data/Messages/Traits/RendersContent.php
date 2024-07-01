<?php

namespace Cognesy\Instructor\Data\Messages\Traits;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Utils\TemplateUtil;

trait RendersContent
{
    /**
     * @param string $template
     * @param array<string,mixed>|null $context
     * @return string
     */
    private function renderString(string $template, ?array $context) : string {
        return match(true) {
            empty($context) => $template,
            default => (new TemplateUtil($context))->renderString($template),
        };
    }

    /**
     * @param array<string,string|array>|Message $messages
     * @param array<string,mixed>|null $context
     * @return string
     */
    protected function renderMessage(array|Message $message, ?array $context) : array {
        return match(true) {
            empty($context) => $message,
            default => (new TemplateUtil($context))->renderMessage($message),
        };
    }

    /**
     * @param array<string,string|array>|Messages $messages
     * @param array<string,mixed>|null $context
     * @return string
     */
    protected function renderMessages(array|Messages $messages, ?array $context) : array {
        return match(true) {
            //empty($context) => $messages,
            default => (new TemplateUtil($context))->renderMessages($messages),
        };
    }
}
