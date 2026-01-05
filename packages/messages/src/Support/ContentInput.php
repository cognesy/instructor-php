<?php declare(strict_types=1);

namespace Cognesy\Messages\Support;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;
use Cognesy\Utils\Arrays;
use InvalidArgumentException;

final class ContentInput
{
    public static function fromAny(string|array|Content|ContentPart|null $content): Content {
        return match (true) {
            is_null($content) => new Content(),
            is_string($content) => new Content(ContentPart::text($content)),
            is_array($content) && Message::isMessage($content) => new Content(ContentPart::fromAny($content['content'] ?? '')),
            is_array($content) && Arrays::hasOnlyStrings($content) => new Content(...array_map(
                fn(string $text) => ContentPart::text($text),
                $content,
            )),
            is_array($content) => new Content(...array_map(
                fn(mixed $item) => ContentPart::fromAny($item),
                $content,
            )),
            $content instanceof Content => new Content(...$content->parts()),
            $content instanceof ContentPart => new Content($content),
            default => throw new InvalidArgumentException('Content must be a string, array, or ContentPart.'),
        };
    }
}
