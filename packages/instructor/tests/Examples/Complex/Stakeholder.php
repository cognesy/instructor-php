<?php

namespace Cognesy\Instructor\Tests\Examples\Complex;

/** Represents a project stakeholder */
class Stakeholder {
    /** Name of the stakeholder */
    public string $name = '';
    /** Role of the stakeholder, if specified */
    public StakeholderRole $role = StakeholderRole::Other;
    /** Any details on the stakeholder, if specified - any mentions of company, organization, structure, group, team, function */
    public ?string $details = '';
}