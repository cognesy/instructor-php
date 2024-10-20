<?php

namespace Cognesy\Instructor\Utils\Web\Contracts;

interface CanGetUrlContent
{
    public function getContent(string $url, array $options = []) : string;
}
