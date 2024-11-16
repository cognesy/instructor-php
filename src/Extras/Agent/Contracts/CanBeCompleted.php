<?php

namespace Cognesy\Instructor\Extras\Agent\Contracts;

interface CanBeCompleted
{
    public function isCompleted(): bool;
}