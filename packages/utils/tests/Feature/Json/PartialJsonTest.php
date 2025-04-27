<?php

use Cognesy\Utils\Json\PartialJsonParser;

it('can parse partial JSON', function () {
    $examples = [
        '{"' => [],
        '{"field-a":"str-1"' => ['field-a' => 'str-1'],
        '{"field-a":"str-1", "field' => ['field-a'=>'str-1', 'field'=>null],
        '{"field-a":"str-1", "field-b":1, ' => ['field-a'=>'str-1', 'field-b'=>1],
        '{"field-a":"str-1", "field-b":1, "field-c":["str-2", ' => ['field-a'=>'str-1', 'field-b'=>1, 'field-c'=>['str-2']],
        '{"field-a":"str-1", "field-b":1, "field-c":["str-2", 2, true]}' => ['field-a'=>'str-1', 'field-b'=>1, 'field-c'=>['str-2', 2, true]],
    ];
    $parser = new PartialJsonParser();

    foreach ($examples as $src => $dest) {
        $partial = $parser->parse($src);
        expect($partial)->toBeArray();
        expect($partial)->toMatchArray($dest);
    }
});

//enum TestStringEnum : string {
//    case A = 'initial';
//    case B = 'changed';
//}
//
//enum TestIntEnum : int {
//    case A = 0;
//    case B = 1;
//}
//
//class TestNestedClass {
//    public string $nestedStringVar = 'intial-b';
//    public TestStringEnum $nestedStringEnumVar = TestStringEnum::A;
//    public array $nestedArrayVar = ['intial-b1', 'intial-b2'];
//
//    public function __construct($val = 0) {
//        $this->nestedStringVar = !$val ? 'intial-b' : 'changed-b';
//        $this->nestedStringEnumVar = !$val ? TestStringEnum::A : TestStringEnum::B;
//        $this->nestedArrayVar = !$val ? ['intial-b1', 'intial-b2'] : ['changed-b1', 'changed-b2'];
//    }
//}
//
//class TestClass {
//    public TestIntEnum $intEnumVar = TestIntEnum::A;
//    public string $stringVar = 'initial-a';
//    public int $intVar = 0;
//    public ?TestNestedClass $nestedClassVar = null;
//    public array $arrayVar = ['intial-a1', 'initial-a2'];
//
//    public function __construct($val = 0) {
//        $this->nestedClassVar = new TestNestedClass($val);
//        $this->intVar = $val;
//        $this->stringVar = !$val ? 'initial-a' : 'changed-a';
//        $this->intEnumVar = !$val ? TestIntEnum::A : TestIntEnum::B;
//        $this->arrayVar = !$val ? ['initial-a1', 'initial-a2'] : ['changed-a1', 'changed-a2', 'changed-a3'];
//        $this->nestedClassVar->nestedStringVar = !$val ? 'initial-a' : 'changed-a';
//        $this->nestedClassVar->nestedStringEnumVar = !$val ? TestStringEnum::A : TestStringEnum::B;
//        $this->nestedClassVar->nestedArrayVar = !$val ? ['initial-a1', 'initial-a2'] : ['changed-a1', 'changed-a2'];
//    }
//}
//
//class PartialModelFactory {
//    function merge(array &$blueprint, array &$partial) {
//        $result = $blueprint;
//        foreach ($partial as $key => &$value) {
//            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
//                $result[$key] = $this->merge($result[$key], $value);
//            } else {
//                if (array_key_exists($key, $result)) {
//                    $result[$key] = $value;
//                }
//            }
//        }
//        return $result;
//    }
//}

