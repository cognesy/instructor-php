<?php

namespace Cognesy\Instructor\Tests\Examples\Complex;

enum StakeholderRole: string {
    case Customer = 'customer';
    case Vendor = 'vendor';
    case SystemIntegrator = 'system integrator';
    case Other = 'other';
}
