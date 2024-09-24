<?php

namespace Cognesy\Instructor\Extras\Web\Scrapers;

use Cognesy\Instructor\Extras\Web\Contracts\CanGetUrlContent;
use Spatie\Browsershot\Browsershot;

class BrowsershotDriver implements CanGetUrlContent
{
    public function getContent(string $url, array $options = []): string
    {
        return Browsershot::url($url)->bodyHtml();
    }
}
