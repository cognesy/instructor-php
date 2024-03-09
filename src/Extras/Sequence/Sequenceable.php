<?php

namespace Cognesy\Instructor\Extras\Sequence;

interface Sequenceable
{
    public static function of(string $class) : Sequenceable;
}