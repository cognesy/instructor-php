<?php

namespace Cognesy\Instructor\Extras\Web\Contracts;

interface CanGetUrlContent
{
    public static function fromUrl(string $url, array $options = []) : string;
    public function getContent(string $url, array $options = []) : string;
}
