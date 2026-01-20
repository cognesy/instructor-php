<?php

namespace Cognesy\Addons\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $tempDir = '';

    /** @var \Closure(string, string, string): void */
    protected \Closure $createSkillFile;

    /** @var \Closure(string, string, string, string): string */
    protected \Closure $writeDefinition;
}
