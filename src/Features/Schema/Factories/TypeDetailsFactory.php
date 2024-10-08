<?php

namespace Cognesy\Instructor\Features\Schema\Factories;

/**
 * Factory for creating TypeDetails from type strings or PropertyInfo Type objects
 */
class TypeDetailsFactory
{
    use \Cognesy\Instructor\Features\Schema\Factories\Traits\TypeDetailsFactory\HandlesResolvers;
    use \Cognesy\Instructor\Features\Schema\Factories\Traits\TypeDetailsFactory\HandlesBuilders;
}
