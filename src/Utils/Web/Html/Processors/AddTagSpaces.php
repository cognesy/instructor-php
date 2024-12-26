<?php

namespace Cognesy\Instructor\Utils\Web\Html\Processors;

use Cognesy\Instructor\Utils\Web\Contracts\CanCleanHtml;

class AddTagSpaces implements CanCleanHtml
{
    public function process(string $html): string {
        return preg_replace_callback('/<[^>]+>/', function ($matches) {
            $tag = $matches[0];
            if (strpos($tag, '<span') === 0 || strpos($tag, '</span') === 0) {
                // If it's a span tag, return it unchanged
                return $tag;
            }
            // Otherwise, add spaces around the tag
            return ' ' . $tag . ' ';
        }, $html);
    }
}