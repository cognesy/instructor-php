<?php

namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs;

use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Attribute\AttributeCollection;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Tag\TagCollection;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Tag\TypeDefTag;
use ReflectionAttribute;
use ReflectionParameter;
use ReflectionProperty;

class TypeDefContext
{
    public string $varName = '';
    public string $location = '';
    /** @var ReflectionAttribute[] */
    private AttributeCollection $attributes;
    /** @var \Cognesy\Instructor\Schema\ReflectionBased\Reflection\Tag\TypeDefTag[] */
    private TagCollection $tags;

    static public function fromReflectionProperty(ReflectionProperty $reflectionProperty): TypeDefContext
    {
        $context = new TypeDefContext();
        $context->varName = $reflectionProperty->getName();
        $context->location = $reflectionProperty
            ->getDeclaringClass()->getName()
            . "::"
            . $reflectionProperty->getName();
        $context->attributes = new AttributeCollection($reflectionProperty->getAttributes());
        $context->tags = TagCollection::extract(
            docComment: $reflectionProperty->getDocComment(),
            tagTypes: ['var'],
            varName: $context->varName
        );
        return $context;
    }

    static public function fromReflectionParameter(ReflectionParameter $reflectionParameter): TypeDefContext
    {
        $context = new TypeDefContext();
        $context->varName = $reflectionParameter->getName();
        $context->location = $reflectionParameter->getDeclaringFunction()->getName()
            . "(". $reflectionParameter->getName() . ")";
        $context->attributes = new AttributeCollection($reflectionParameter->getAttributes());
        $context->tags = TagCollection::extract(
            docComment: $reflectionParameter->getDeclaringFunction()->getDocComment(),
            tagTypes: ['param'],
            varName: $context->varName
        );
        return $context;
    }

    public function attributes() : AttributeCollection
    {
        return $this->attributes;
    }

    public function tags() : TagCollection
    {
        return $this->tags;
    }
}
