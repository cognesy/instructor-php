<?php

namespace Cognesy\Instructor\Extras\Module\Call\Contracts;

interface HasErrorData
{
    public function hasErrors() : bool;

    public function errors() : array;

    public function addError(string $message, array $context) : void;
}