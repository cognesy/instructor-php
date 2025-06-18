<?php

namespace Cognesy\Template\Utils;

use Cognesy\Template\Template;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;

class MessageToRoleString
{
    public function fromMessage(Message $message) : string {
        $template = match(true) {
            !empty($message->name()) => "<|name|> (<|role|>): <|content|>",
            default => "<|role|>: <|content|>"
        };
        return Template::arrowpipe()
            ->with([
                'role' => $message->role(),
                'name' => $message->name(),
                'content' => $message->toString(),
            ])
            ->withTemplateContent($template)
            ->toText();
    }

    public function fromMessages(Messages $messages) : string {
        $text = '';
        foreach ($messages->each() as $message) {
            if ($message->isEmpty()) {
                continue;
            }
            $text .= $this->fromMessage($message) . "\n";
        }
        return $text;
    }
}