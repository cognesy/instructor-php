<?php
namespace Cognesy\Aux\Web\Scrapers;

use Cognesy\Aux\Web\Contracts\CanGetUrlContent;

class BasicReader implements CanGetUrlContent
{
    public function getContent(string $url, array $options = []): string {
        return file_get_contents($url);
    }
}
