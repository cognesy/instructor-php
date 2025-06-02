<?php
namespace Cognesy\Utils\Messages\Traits\Message;

use Cognesy\Template\Template;

trait HandlesTransformation
{
    public function toArray() : array {
        return array_filter([
            'role' => $this->role,
            'name' => $this->name,
            'content' => match(true) {
                $this->content->isEmpty() => '',
                $this->content->isComposite() => $this->content->toArray(),
                default => $this->content->toString(),
            },
            '_metadata' => $this->metadata,
        ]);
    }

    public function toString() : string {
        return $this->content->toString();
    }

    public function toRoleString() : string {
        $template = match(true) {
            !empty($this->name) => "<|name|> (<|role|>): <|content|>",
            default => "<|role|>: <|content|>"
        };
        return Template::arrowpipe()
            ->with([
                'role' => $this->role,
                'name' => $this->name,
                'content' => $this->toString(),
            ])
            ->withTemplateContent($template)
            ->toText();
    }
}
