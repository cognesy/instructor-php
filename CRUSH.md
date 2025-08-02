# CRUSH.md - Project Guidelines

This document outlines essential commands and code style guidelines for this PHP project.

## Build & Dependencies

- **Install/Update Dependencies:** `composer install` / `composer update`

## Lint & Type Checking

- **PHPStan (Static Analysis):** `composer phpstan`
- **Psalm (Static Analysis):** `composer psalm`

## Testing

- **Run All Tests:** `composer test-all` or `./vendor/bin/pest`
- **Run Unit & Feature Tests:** `composer test` or `./vendor/bin/pest --testsuite=Unit,Feature`
- **Run a Single Test File:** `./vendor/bin/pest <path/to/your/test/file>`

## Code Style Guidelines

- **Language:** PHP.
- **Autoloading:** PSR-4 standard.
- **Formatting:** Adhere to PSR-12 coding style. Follow existing file indentation and bracing.
- **Naming Conventions:**
    - Classes, Interfaces, Traits: `PascalCase`
    - Methods, Properties, Functions: `camelCase`
    - Constants: `SCREAMING_SNAKE_CASE`
- **Type Hinting:** Mandatory for arguments, return types, and properties where possible.
- **Error Handling:** Use exceptions for error conditions, follow existing `try-catch` patterns.
- **Imports:** Use `use` statements for fully qualified class names. Avoid aliasing unless necessary.
- **Comments:** Add comments sparingly, focusing on _why_ complex logic exists rather than _what_ it does.
