<?php

namespace Cognesy\Aux\Web\Contracts;

interface CanGetUrlContent
{
    public function getContent(string $url, array $options = []) : string;
}
