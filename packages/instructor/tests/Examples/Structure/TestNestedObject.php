<?php

namespace Cognesy\Instructor\Tests\Examples\Structure;

use DateTime;

class TestNestedObject {
    public string $stringProperty;
    public int $integerProperty;
    public bool $boolProperty;
    public float $floatProperty;
    public DateTime $datetimeProperty;
    public TestEnum $enumProperty;
    public array $arrayProperty;
    /** @var string[] */
    public array $collectionProperty;
}
