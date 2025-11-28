# Local Test Matrix Runner

Simple bash script to run GitHub Actions test matrix locally without Docker.

## Requirements

- Multiple PHP versions installed (8.2, 8.3, 8.4)
- Composer installed globally
- Bash shell

## Installing Multiple PHP Versions

### Ubuntu/Debian
```bash
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.2 php8.3 php8.4
```

### macOS (Homebrew)
```bash
brew tap shivammathur/php
brew install shivammathur/php/php@8.2
brew install shivammathur/php/php@8.3
brew install shivammathur/php/php@8.4
```

### Using phpenv/php-build
```bash
phpenv install 8.2.x
phpenv install 8.3.x
phpenv install 8.4.x
```

## Usage

```bash
# Run full matrix (all PHP versions × all composer flags)
./test-matrix

# Run all tests for specific PHP version
./test-matrix 8.3

# Run specific combination
./test-matrix 8.3 --prefer-lowest

# Run all PHP versions with specific composer flags
./test-matrix "" --prefer-stable
```

## What It Does

The script mirrors the GitHub Actions workflow (`.github/workflows/php.yml`):

1. **Cleans vendor directory** - ensures fresh install
2. **Installs dependencies** - runs `composer update` with specified flags
3. **Regenerates autoloader** - runs `composer dump-autoload`
4. **Runs tests** - executes `composer test` (Pest test suite)

## Test Matrix

| PHP Version | Composer Flags |
|-------------|----------------|
| 8.2 | --prefer-stable |
| 8.2 | --prefer-lowest |
| 8.3 | --prefer-stable |
| 8.3 | --prefer-lowest |
| 8.4 | --prefer-stable |
| 8.4 | --prefer-lowest |

## Output

The script provides:
- Color-coded output (green=pass, red=fail, yellow=skip)
- Progress indicators for each step
- Summary table at the end
- Non-zero exit code if any tests fail

## Example Output

```
╔════════════════════════════════════════════════════════╗
║         Local GitHub Test Matrix Runner                ║
╚════════════════════════════════════════════════════════╝

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
▶ Testing PHP 8.3 with --prefer-lowest
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🧹 Cleaning vendor directory...
📦 Installing dependencies with --prefer-lowest...
🔄 Regenerating autoloader...
🧪 Running test suite...

✓ Tests PASSED for PHP 8.3 (--prefer-lowest)

╔════════════════════════════════════════════════════════╗
║                    Test Summary                        ║
╚════════════════════════════════════════════════════════╝

✓ PHP 8.3 (--prefer-lowest)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Total: 1 | Passed: 1 | Failed: 0
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

## Notes

- If a PHP version is not found, it will be skipped (not counted as failure)
- The script automatically detects if `php8.3` or `php` commands should be used
- Vendor directory is cleaned between runs to ensure accurate testing
- Exit code reflects test results (0=all passed, 1=some failed)
