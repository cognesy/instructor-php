<?php declare(strict_types=1);

use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\EnumSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\Tests\Examples\Schema\SimpleClass;
use Symfony\Component\TypeInfo\Type;

enum SchemaFactoryMakeSchemaBranchOrderEnum : string
{
    case A = 'a';
}

it('checks collection branch before object and enum branches in makeSchema', function () {
    $source = file_get_contents(__DIR__ . '/../../src/SchemaFactory.php');
    expect($source)->not->toBeFalse();

    $methodStart = strpos($source, 'private function makeSchema(Type $type) : Schema');
    expect($methodStart)->not->toBeFalse();

    $methodBody = substr($source, $methodStart);
    $collectionPos = strpos($methodBody, 'TypeInfo::isCollection($type) =>');
    $objectPos = strpos($methodBody, 'TypeInfo::isObject($type) && !TypeInfo::isEnum($type) =>');
    $enumPos = strpos($methodBody, 'TypeInfo::isEnum($type) =>');

    expect($collectionPos)->not->toBeFalse();
    expect($objectPos)->not->toBeFalse();
    expect($enumPos)->not->toBeFalse();

    expect($collectionPos)->toBeLessThan($objectPos);
    expect($collectionPos)->toBeLessThan($enumPos);
});

it('builds collection schema for typed collection roots', function () {
    $factory = new SchemaFactory();

    $objectListSchema = $factory->schema(Type::list(Type::object(SimpleClass::class)));
    $enumListSchema = $factory->schema(Type::list(Type::enum(SchemaFactoryMakeSchemaBranchOrderEnum::class)));

    expect($objectListSchema)->toBeInstanceOf(CollectionSchema::class);
    expect($objectListSchema->nestedItemSchema)->toBeInstanceOf(ObjectSchema::class);

    expect($enumListSchema)->toBeInstanceOf(CollectionSchema::class);
    expect($enumListSchema->nestedItemSchema)->toBeInstanceOf(EnumSchema::class);
});
