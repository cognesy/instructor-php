<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Web\Scrapers;

use Cognesy\Auxiliary\Web\Contracts\CanGetUrlContent;

class BasicReader implements CanGetUrlContent
{
    #[\Override]
    public function getContent(string $url, array $options = []): string {
        $content = file_get_contents($url);
        return is_string($content) ? $content : '';
    }
}
