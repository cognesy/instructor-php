<?php

namespace Cognesy\Auxiliary\Web\Contracts;

interface CanGetUrlContent
{
    public function getContent(string $url, array $options = []) : string;
}
