<?php

namespace Cognesy\Experimental\Tests\Feature\Extras;

use Cognesy\Dynamic\Structure;
use Cognesy\Experimental\Signature\Attributes\InputField;
use Cognesy\Experimental\Signature\Attributes\OutputField;
use Cognesy\Experimental\Signature\SignatureFactory;
use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\SchemaBuilder;
use Symfony\Component\Serializer\Attribute\Ignore;

it('creates signature from string', function () {
    $signature = SignatureFactory::fromString('name:string (description) -> age:int (description)');
    expect($signature->toSignatureString())->toBe('name:string (description) -> age:int (description)');
});

it('creates signature from separate schemas', function () {
    $inputSchema = SchemaBuilder::define('inputs')
        ->string('name', 'name description')
        ->schema();

    $outputSchema = SchemaBuilder::define('outputs')
        ->int('age', 'age description')
        ->schema();

    $signature = SignatureFactory::fromSchemas($inputSchema, $outputSchema);
    expect($signature->toSignatureString())->toBe('name:string (name description) -> age:int (age description)');
    expect($signature->toRequestedSchema('prediction')->name())->toBe('prediction');
    expect($signature->toRequestedSchema('prediction')->description())->toBe($signature->getDescription());
});

it('creates signature from classes', function () {
    class Input {
        public string $name;
    }
    class Output {
        public int $age;
    }
    $signature = SignatureFactory::fromClasses(input: Input::class, output: Output::class);
    expect($signature->toSignatureString())->toBe('name:string -> age:int');
});

it('creates auto signature from class metadata - autowiring', function () {
    #[Description('Test description')]
    class TestSignature2Task {
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
    $signature = SignatureFactory::fromClassMetadata(TestSignature2Task::class);
    expect($signature->toSignatureString())->toBe('stringProperty:string, boolProperty:bool (bool description) -> intProperty:int, mixedProperty:mixed');
});

it('keeps transitional structure adapters in signature factory', function () {
    $input = Structure::fromSchema(SchemaBuilder::define('inputs')->string('name')->schema());
    $output = Structure::fromSchema(SchemaBuilder::define('outputs')->int('age')->schema());

    $signature = SignatureFactory::fromStructures($input, $output);
    expect($signature->toSignatureString())->toBe('name:string -> age:int');

    $single = SignatureFactory::fromStructure(
        Structure::fromSchema(
            SchemaBuilder::define('prediction')
                ->shape('inputs', fn(SchemaBuilder $builder) => $builder->string('query'))
                ->shape('outputs', fn(SchemaBuilder $builder) => $builder->string('answer'))
                ->schema(),
        ),
    );
    expect($single->toSignatureString())->toBe('query:string -> answer:string');
});
