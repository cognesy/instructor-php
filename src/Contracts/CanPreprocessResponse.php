<?php

namespace Cognesy\Instructor\Contracts;

use Psr\Http\Message\ResponseInterface;

interface CanPreprocessResponse
{
    public function process(ResponseInterface $response) : ResponseInterface;
}