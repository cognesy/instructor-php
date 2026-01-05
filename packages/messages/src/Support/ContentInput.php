<?php declare(strict_types=1);

namespace Cognesy\Messages\Support;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Message;
use InvalidArgumentException;

final class ContentInput
{
    public static function fromAny(string|array|Content|ContentPart|ContentParts|null $content): Content {
        return match (true) {
            is_null($content) => new Content(),
            is_string($content) => new Content(ContentPart::text($content)),
            is_array($content) && Message::isMessage($content) => self::fromAny($content['content'] ?? ''),
            is_array($content) => Content::fromParts(
                ContentParts::fromArray($content),
            ),
            $content instanceof Content => Content::fromParts($content->partsList()),
            $content instanceof ContentPart => new Content($content),
            $content instanceof ContentParts => Content::fromParts($content),
            default => throw new InvalidArgumentException('Content must be a string, array, ContentPart, or ContentParts.'),
        };
    }
}
