<?php

namespace Cognesy\Instructor\Extras\Web\Contracts;

interface CanGetUrlContent
{
    public function getContent(string $url, array $options = []) : string;
}
