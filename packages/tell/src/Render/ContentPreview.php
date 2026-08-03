<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

final readonly class ContentPreview
{
    public function __construct(
        public string $content,
        public int $characters,
        public bool $truncated,
    ) {}

    public static function from(string $content, bool $full, int $limit = 1200): self
    {
        $characters = mb_strlen($content);
        $truncated = ! $full && $characters > $limit;
        $preview = match ($truncated) {
            true => rtrim(mb_substr($content, 0, $limit - 3)).'...',
            false => $content,
        };

        return new self($preview, $characters, $truncated);
    }
}
