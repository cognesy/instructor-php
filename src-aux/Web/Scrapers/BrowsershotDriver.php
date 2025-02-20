<?php

namespace Cognesy\Aux\Web\Scrapers;

use Cognesy\Aux\Web\Contracts\CanGetUrlContent;
use Spatie\Browsershot\Browsershot;

class BrowsershotDriver implements CanGetUrlContent
{
    public function getContent(string $url, array $options = []): string
    {
        return Browsershot::url($url)->bodyHtml();
    }
}
