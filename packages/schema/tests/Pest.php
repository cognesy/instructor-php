<?php

declare(strict_types=1);

bootstrapSchemaPackageAutoload();

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeCloseTo', function (float $expected, int $precision = 8) {
    $epsilon = 1 / (10 ** $precision);
    $actual = $this->value;
    $diff = abs($expected - $actual);
    $message = "Failed asserting that %.{$precision}f matches expected %.{$precision}f within epsilon %.{$precision}f.";
    PHPUnit\Framework\Assert::assertLessThanOrEqual(
        expected: $epsilon,
        actual: $diff,
        message: sprintf($message, $actual, $expected, $epsilon)
    );
    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

if (!function_exists('something')) {
    function something()
    {
        // ..
    }
}

function bootstrapSchemaPackageAutoload() : void
{
    $root = dirname(__DIR__);
    spl_autoload_register(
        static function (string $class) use ($root) : void {
            $prefixes = [
                'Cognesy\\Schema\\Tests\\' => $root . '/tests/',
                'Cognesy\\Schema\\' => $root . '/src/',
            ];

            foreach ($prefixes as $prefix => $baseDir) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }

                $relative = substr($class, strlen($prefix));
                $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }

                return;
            }
        },
        true,
        true,
    );
}
