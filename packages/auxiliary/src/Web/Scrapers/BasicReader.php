<?php
namespace Cognesy\Auxiliary\Web\Scrapers;

use Cognesy\Auxiliary\Web\Contracts\CanGetUrlContent;

class BasicReader implements CanGetUrlContent
{
    public function getContent(string $url, array $options = []): string {
        return file_get_contents($url);
    }
}
