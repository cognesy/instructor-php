<?php
namespace Cognesy\InstructorHub\Core;

abstract class Command
{
    public string $name;
    public string $description;

    abstract public function execute(array $params = []);
}
