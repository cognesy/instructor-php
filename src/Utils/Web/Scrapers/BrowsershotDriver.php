<?php

namespace Cognesy\Instructor\Utils\Web\Scrapers;

use Cognesy\Instructor\Utils\Web\Contracts\CanGetUrlContent;
use Spatie\Browsershot\Browsershot;

class BrowsershotDriver implements CanGetUrlContent
{
    public function getContent(string $url, array $options = []): string
    {
        return Browsershot::url($url)->bodyHtml();
    }
}
