<?php
namespace Cognesy\Instructor\Utils\Web\Traits;

trait HandlesContent
{
    public function content() : string {
        return $this->content;
    }

    public function metadata(
        array $attributes = ['title', 'description', 'keywords', 'og:title', 'og:description', 'og:image', 'og:url']
    ) : array {
        return $this->htmlProcessor->getMetadata($this->content, $attributes);
    }

    public function title() : string {
        return $this->htmlProcessor->getTitle($this->content);
    }

    public function body() : string {
        return $this->htmlProcessor->getBody($this->content);
    }

    public function asMarkdown() : string {
        return $this->htmlProcessor->toMarkdown($this->content);
    }
}