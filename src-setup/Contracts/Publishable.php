<?php

namespace Cognesy\Setup\Contracts;

interface Publishable
{
    public function publish(): bool;
}