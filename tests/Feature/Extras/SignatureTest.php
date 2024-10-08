<?php
namespace Tests\Feature\Extras;

use Cognesy\Instructor\Extras\Module\CallData\SignatureData;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\InputField;
use Cognesy\Instructor\Extras\Module\Signature\Attributes\OutputField;
use Cognesy\Instructor\Extras\Module\Signature\SignatureFactory;
use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Features\Schema\Attributes\Description;
use Symfony\Component\Serializer\Attribute\Ignore;

it('creates signature from string', function () {
    $signature = SignatureFactory::fromString('name:string (description) -> age:int (description)');
    expect($signature->toSignatureString())->toBe('name:string (description) -> age:int (description)');
});

it('creates signature from separate structures', function () {
    $structure1 = Structure::define('inputs', [
        Field::string('name', 'name description'),
    ]);
    $structure2 = Structure::define('outputs', [
        Field::int('age', 'age description'),
    ]);
    $signature = SignatureFactory::fromStructures($structure1, $structure2);
    expect($signature->toSignatureString())->toBe('name:string (name description) -> age:int (age description)');
});

it('creates signature from classes', function () {
    class Input {
        public string $name;
    }
    class Output {
        public int $age;
    }
    $signature = SignatureFactory::fromClasses(Input::class, Output::class);
    expect($signature->toSignatureString())->toBe('name:string -> age:int');
});

it('creates auto signature from class metadata - autowiring', function () {
    #[Description('Test description')]
    class TestSignature2Task extends SignatureData {
        #[InputField]
        public string $stringProperty;
        #[InputField('bool description')]
        public bool $boolProperty;
        #[OutputField]
        public int $intProperty;
        #[Ignore]
        public bool $ignoredProperty;
        protected string $protectedProperty;
        private string $privateProperty;
        static public string $staticProperty;
        #[OutputField]
        public $mixedProperty;
    }
    $signature = (new TestSignature2Task)->signature();
    expect($signature->toSignatureString())->toBe('stringProperty:string, boolProperty:bool (bool description) -> intProperty:int, mixedProperty:string');
})->skip("To be reimplemented");
