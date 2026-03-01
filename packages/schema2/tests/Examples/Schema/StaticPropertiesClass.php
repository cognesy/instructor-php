<?php

namespace Cognesy\Schema\Tests\Examples\Schema;

class StaticPropertiesClass
{
    public static string $globalName = 'global';
    public static int $globalCount = 0;

    public string $name = '';
    public ?int $age = null;
}

