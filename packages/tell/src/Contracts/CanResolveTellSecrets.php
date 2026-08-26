<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Config\Contracts\CanResolveSecrets;

/** Tell's approved, redaction-aware secret resolution chain. */
interface CanResolveTellSecrets extends CanResolveSecrets {}
