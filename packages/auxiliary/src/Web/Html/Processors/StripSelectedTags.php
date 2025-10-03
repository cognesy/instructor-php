<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Html\Processors;

use Cognesy\Auxiliary\Web\Contracts\CanCleanHtml;

class StripSelectedTags implements CanCleanHtml
{
    private array $tags;

    public function __construct(?array $tags = null)
    {
        $this->tags = $tags;
    }

    #[\Override]
    public function process(string $html): string
    {
        if (empty($this->tags)) {
            return $html;
        }

        $tags = implode('|', $this->tags);
        $pattern = "/<($tags)[^>]*>.*?<\/\\1>/si";
        $result = preg_replace($pattern, '', $html);
        return is_string($result) ? $result : $html;
    }
}