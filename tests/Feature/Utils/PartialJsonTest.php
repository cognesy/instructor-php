<?php

use Cognesy\Instructor\Utils\JsonParser;

enum TestStringEnum : string {
    case A = 'initial';
    case B = 'changed';
}

enum TestIntEnum : int {
    case A = 0;
    case B = 1;
}

class TestNestedClass {
    public string $nestedStringVar = 'intial-b';
    public TestStringEnum $nestedStringEnumVar = TestStringEnum::A;
    public array $nestedArrayVar = ['intial-b1', 'intial-b2'];

    public function __construct($val = 0) {
        $this->nestedStringVar = !$val ? 'intial-b' : 'changed-b';
        $this->nestedStringEnumVar = !$val ? TestStringEnum::A : TestStringEnum::B;
        $this->nestedArrayVar = !$val ? ['intial-b1', 'intial-b2'] : ['changed-b1', 'changed-b2'];
    }
}

class TestClass {
    public TestIntEnum $intEnumVar = TestIntEnum::A;
    public string $stringVar = 'initial-a';
    public int $intVar = 0;
    public ?TestNestedClass $nestedClassVar = null;
    public array $arrayVar = ['intial-a1', 'initial-a2'];

    public function __construct($val = 0) {
        $this->nestedClassVar = new TestNestedClass($val);
        $this->intVar = $val;
        $this->stringVar = !$val ? 'initial-a' : 'changed-a';
        $this->intEnumVar = !$val ? TestIntEnum::A : TestIntEnum::B;
        $this->arrayVar = !$val ? ['initial-a1', 'initial-a2'] : ['changed-a1', 'changed-a2', 'changed-a3'];
        $this->nestedClassVar->nestedStringVar = !$val ? 'initial-a' : 'changed-a';
        $this->nestedClassVar->nestedStringEnumVar = !$val ? TestStringEnum::A : TestStringEnum::B;
        $this->nestedClassVar->nestedArrayVar = !$val ? ['initial-a1', 'initial-a2'] : ['changed-a1', 'changed-a2'];
    }
}

class PartialModelFactory {
    public function fromPartialJson(string $json) : object {
    }

    function merge(array &$blueprint, array &$partial)
    {
        $result = $blueprint;
        foreach ($partial as $key => &$value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                $result[$key] = $this->merge($result[$key], $value);
            } else {
                if (array_key_exists($key, $result)) {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }
}

it('can parse partial JSON', function () {
    //$json = '{"nestedStringEnumVar":"a","nestedStringVar":"x", "nestedArrayVar":["ar1","ar2"]}';
    $parser = new JsonParser();
    $blueprint = json_decode(json_encode(new TestClass()), true);
    $json = json_encode(new TestClass(1));
    $segments = str_split($json, 3);

    $steps = 0;
    $merger = new MergedJson();
    $jsonPartial = '';
    foreach ($segments as $part) {
        $steps++;
        $jsonPartial .= $part;
        $data = $parser->parse($jsonPartial);
        $merged = $merger->merge($blueprint, $data);
        if ($steps < 3) {
            break;
        }
    }
})->skip("Tests to be implemented");