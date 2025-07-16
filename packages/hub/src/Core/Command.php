<?php declare(strict_types=1);
namespace Cognesy\InstructorHub\Core;

abstract class Command
{
    public string $name;
    public string $description;

    abstract public function execute(array $params = []);
}
