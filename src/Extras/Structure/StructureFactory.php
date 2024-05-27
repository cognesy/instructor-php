<?php

namespace Cognesy\Instructor\Extras\Structure;

class StructureFactory
{
    use Traits\CreatesStructureFromArray;
    use Traits\CreatesStructureFromString;
    use Traits\CreatesStructureFromCallables;
    use Traits\CreatesStructureFromClasses;
    use Traits\CreatesStructureFromJsonSchema;
}