<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Utils\Env;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
trait HandlesEnv
{
    /**
     * Sets the environment variables configuration file paths and names
     *
     * @param string|array $paths
     * @param string|array $names
     * @return $this
     */
    public function withEnv(string|array $paths, string|array $names = '') : self {
        Env::set($paths, $names);
        return $this;
    }
}