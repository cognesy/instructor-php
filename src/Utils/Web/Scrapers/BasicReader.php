<?php
namespace Cognesy\Instructor\Utils\Web\Scrapers;

use Cognesy\Instructor\Utils\Web\Contracts\CanGetUrlContent;

class BasicReader implements CanGetUrlContent
{
    public function getContent(string $url, array $options = []): string {
        return file_get_contents($url);
    }
}
