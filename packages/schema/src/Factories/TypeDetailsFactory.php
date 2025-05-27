<?php

namespace Cognesy\Schema\Factories;

/**
 * Factory for creating TypeDetails from type strings or PropertyInfo Type objects
 */
class TypeDetailsFactory
{
    use \Cognesy\Schema\Factories\Traits\TypeDetailsFactory\HandlesResolvers;
    use \Cognesy\Schema\Factories\Traits\TypeDetailsFactory\HandlesBuilders;
}
