<?php

namespace Cognesy\Auxiliary\Web\Scrapers;

use Cognesy\Auxiliary\Web\Contracts\CanGetUrlContent;
use Spatie\Browsershot\Browsershot;

class BrowsershotDriver implements CanGetUrlContent
{
    public function getContent(string $url, array $options = []): string
    {
        return Browsershot::url($url)->bodyHtml();
    }
}
