<?php declare(strict_types=1);

namespace Cognesy\Template\Rendering;

use Cognesy\Messages\Contracts\CanRenderMessages;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Template\Template;

class MessageToRoleStringRenderer implements CanRenderMessages
{
    public function renderMessages(Messages $messages, array $parameters = []): Messages {
        $text = $this->fromMessages($messages);
        return Messages::fromString($text);
    }

    private function fromMessages(Messages $messages) : string {
        $text = '';
        foreach ($messages->each() as $message) {
            if ($message->isEmpty()) {
                continue;
            }
            $text .= $this->fromMessage($message) . "\n";
        }
        return $text;
    }

    private function fromMessage(Message $message) : string {
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
}