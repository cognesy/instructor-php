<?php

namespace Cognesy\Instructor\Features\Schema\Factories;

/**
 * Factory for creating TypeDetails from type strings or PropertyInfo Type objects
 */
class TypeDetailsFactory
{
    use Traits\TypeDetailsFactory\HandlesResolvers;
    use Traits\TypeDetailsFactory\HandlesBuilders;
}
