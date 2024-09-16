<?php

namespace Cognesy\Instructor\Data\Messages\Traits;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Utils\TemplateUtil;

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
            default => (new TemplateUtil($parameters))->renderString($template),
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
            default => (new TemplateUtil($parameters))->renderMessage($message),
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
            default => (new TemplateUtil($parameters))->renderMessages($messages),
        };
    }
}
