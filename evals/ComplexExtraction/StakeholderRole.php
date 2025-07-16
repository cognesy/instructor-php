<?php declare(strict_types=1);

namespace Evals\ComplexExtraction;

enum StakeholderRole: string {
    case Customer = 'customer';
    case Vendor = 'vendor';
    case SystemIntegrator = 'system integrator';
    case Other = 'other';
}
