<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Assembly;

use Cognesy\Messages\Content;
use Cognesy\Messages\Message;

final class ThinkTagMessageNormalizer
{
    private const START_TAG = '<think>';
    private const END_TAG = '</think>';

    public static function normalize(Message $message): Message
    {
        if ($message->reasoningContent() !== '') {
            return $message;
        }

        $content = $message->content()->toString();
        $start = strpos($content, self::START_TAG);
        $end = strpos($content, self::END_TAG);
        if ($start === false || $end === false || $end <= $start) {
            return $message;
        }

        $reasoningStart = $start + strlen(self::START_TAG);
        $reasoning = substr($content, $reasoningStart, $end - $reasoningStart);
        if ($reasoning === '') {
            return $message;
        }

        $before = substr($content, 0, $start);
        $after = substr($content, $end + strlen(self::END_TAG));

        return $message
            ->withContent(Content::text(trim($before . $after)))
            ->withReasoningContent($reasoning);
    }
}
