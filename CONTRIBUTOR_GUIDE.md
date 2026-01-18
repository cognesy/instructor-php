# Contributor Guide

Welcome to the Instructor PHP monorepo! This guide will help you understand how to work with this project structure and perform common development tasks.

## Project Structure

This is a monorepo containing multiple independent packages under the `packages/` directory:

- **Core packages**: `instructor`, `config`, `events`, `messages`, `utils`, `schema`, `templates`
- **Extended functionality**: `addons`, `auxiliary`, `polyglot`, `setup`, `hub`, `tell`, `dynamic`
- **Development tools**: `evals`, `experimental`, `doctor`
- **Agent control**: `agent-ctrl` - Unified CLI bridge for code agents
- **Observability**: `metrics` - metrics collection and export
- **HTTP client**: `http-client`
- **Pipeline processing**: `pipeline`

Each package is independently publishable to Packagist with its own `composer.json`, tests, and documentation.

## Getting Started

### Prerequisites
- PHP 8.3+
- Composer
- Git
- `jq` (for version synchronization scripts)
- GitHub CLI (`gh`) for releases

### Initial Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/cognesy/instructor-php.git
   cd instructor-php
   ```

2. Install main dependencies:
   ```bash
   composer install
   ```

3. Install dependencies for all packages:
   ```bash
   ./scripts/composer-install-all.sh
   ```

## Development Workflows

### Working with Individual Packages

Each package can be developed independently:

```bash
cd packages/instructor
composer install
composer test
composer phpstan
```

### Available Commands

#### Root-level commands (via `composer.json`):
- `composer test` - Run tests for main project
- `composer tests` - Alias for test
- `composer phpstan` - Run PHPStan analysis
- `composer psalm` - Run Psalm analysis
- `composer psalm-unused` - Find unused code with Psalm
- `composer unused` - Find unused classes and methods with ShipMonk Dead Code Detector
- `composer unused-debug` - Find unused code with verbose debugging output
- `composer hub` - Run Instructor Hub CLI
- `composer tell` - Run Tell CLI
- `composer setup` - Run setup wizard

Run tests from the monorepo root to check if they work with the latest changes to the code.


#### Package-level commands:
Each package supports:
- `composer test` or `composer tests` - Run package tests
- `composer phpstan` - Static analysis
- `composer psalm` - Additional static analysis

### Testing

#### Test All Packages
```bash
composer test
```

#### Test Individual Package
```bash
cd packages/instructor
composer test
```
ATTENTION: Tests executed this way rely on Packagist dependencies, so they may miss the latest changes made to this monorepo packages unless they have been published already.

### Code Quality

Run static analysis tools:
```bash
composer phpstan  # Root level
composer psalm    # Root level

# Or for individual packages
cd packages/instructor
composer phpstan
composer psalm
```

### Unused Code Detection

Detect unused classes, methods, and dead code:

```bash
# Find unused classes and methods using ShipMonk Dead Code Detector
composer unused

# Get detailed debugging information about specific class/method usage
composer unused-debug

# Alternative: Find unused code with Psalm
composer psalm-unused
```

#### Understanding Unused Code Output

The `composer unused` command will show:
- **Unused classes**: Classes that are never instantiated or referenced
- **Unused methods**: Methods that are never called, including transitively unused methods
- **Dead cycles**: Methods that only call each other but are never called externally

Example output:
```
------ ------------------------------------------------------------------------
Line   src/App/Entity/UnusedClass.php
------ ------------------------------------------------------------------------
8      Unused App\Entity\UnusedClass 🪪 shipmonk.deadClass

26     Unused App\Facade\UserFacade::updateUserAddress 🪪 shipmonk.deadMethod
       💡 Thus App\Entity\User::updateAddress is transitively also unused
```

#### Debugging Specific Usage

To debug why a specific class or method is considered unused, add it to `phpstan-unused.neon`:

```neon
parameters:
    shipmonkDeadCode:
        debug:
            usagesOf:
                - App\YourClass::yourMethod
```

Then run `composer unused-debug` to see detailed usage analysis.

## Creating New Packages

### 1. Create Package Configuration

Create a JSON configuration file (see `package-config.example.json`):

```json
{
  "package_name": "my-package",
  "namespace": "MyPackage",
  "package_title": "My Package",
  "package_description": "Description of my package functionality",
  "target_directory": "packages/my-package"
}
```

### 2. Generate Package Structure

```bash
php scripts/create-package.php my-package-config.json
```

This script:
- Creates directory structure based on `packages/empty-new` template
- Replaces template placeholders with your configuration
- Sets up basic `composer.json`, tests, and directory structure

### 3. Update Centralized Configuration

Add your new package to `packages.json`:

```json
{
  "local": "packages/my-package",
  "repo": "cognesy/instructor-my-package",
  "github_name": "instructor-my-package", 
  "composer_name": "cognesy/instructor-my-package"
}
```

Update the GitHub Actions workflow:

```bash
./scripts/update-split-yml.sh
```

### 4. Complete Package Setup

```bash
cd packages/my-package
composer install
composer test
```

## Version Management and Releases

### Centralized Package Configuration

This monorepo uses a centralized configuration system to manage packages and avoid inconsistencies:

- **`packages.json`** - Central configuration file defining all packages with their paths, repository names, and Composer names
- **Automatic synchronization** - Scripts load package configuration from this single source
- **No manual lists** - Package lists in scripts and GitHub Actions are generated automatically

### Version Synchronization

All packages follow semantic versioning and are released together:

```bash
./scripts/sync-ver.sh 1.2.0
```

This script:
- Loads package configuration from `packages.json`
- Updates version constraints across all packages
- Ensures internal dependencies use compatible version ranges (^MAJOR.MINOR)
- Processes all packages defined in the centralized configuration

### Creating a Release

1. **Prepare release notes**: Create `docs/release-notes/vX.Y.Z.mdx`

2. **Run the release script**:
   ```bash
   ./scripts/publish-ver.sh 1.2.0
   ```

The release process:
- **Step 0**: Rebuilds documentation (`./bin/instructor-hub gendocs`)
- **Step 0.1**: Copies resource files (`./scripts/copy-resources.sh`)
- **Step 1**: Updates all package versions (`./scripts/sync-ver.sh`)
- **Step 2**: Distributes release notes to all packages
- **Step 3-4**: Commits changes if any exist
- **Step 5**: Creates Git tag (`v1.2.0`)
- **Step 6**: Pushes changes and tag to origin
- **Step 7**: Creates GitHub release

### Package Splitting

After the main release is created, GitHub Actions automatically:
- Splits each package into separate repositories (configured via `packages.json`)
- Creates individual releases for each package on Packagist
- The split workflow supports both tagged releases and continuous main branch synchronization

## Utilities and Helper Scripts

### Package Configuration Management
- `./scripts/load-packages.sh` - Load centralized package configuration (used by other scripts)
- `./scripts/generate-split-matrix.sh` - Generate GitHub Actions matrix from `packages.json`
- `./scripts/update-split-yml.sh` - Update `.github/workflows/split.yml` with current package configuration

### Resource Management
- `./scripts/copy-resources.sh` - Copy shared resources to packages
- `./scripts/remove-resources.sh` - Remove shared resources from packages

### Composer Operations
- `./scripts/composer-install-all.sh` - Install dependencies for all packages
- `./scripts/composer-update-all.sh` - Update dependencies for all packages
- `./scripts/clean-composer.sh` - Clean composer cache and autoload files

### Development Tools
- `./scripts/code2md.sh` - Convert code to markdown (documentation helper)

## Working with Documentation

### Hub CLI Tool
The project includes an Instructor Hub CLI for documentation management:

```bash
./bin/instructor-hub gendocs    # Generate documentation
composer hub gendocs            # Alternative syntax
```

### Tell CLI Tool
For interactive assistance:

```bash
./bin/tell                      # Interactive CLI
composer tell                   # Alternative syntax
```

## Package Dependencies

### Core Dependency Chain
- Most packages depend on: `config`, `events`, `messages`, `utils`
- `instructor` package is the main entry point
- `addons` extends `instructor` with additional functionality
- `polyglot` provides multilingual capabilities

### Development Dependencies
Common dev dependencies across packages:
- `pestphp/pest` - Testing framework
- `phpstan/phpstan` - Static analysis
- `vimeo/psalm` - Additional static analysis
- `icanhazstring/composer-unused` - Unused dependency detection
- `maglnet/composer-require-checker` - Dependency validation

## Best Practices

### Development
1. **Work in feature branches** - Never commit directly to main
2. **Test thoroughly** - Run tests for affected packages
3. **Follow PSR standards** - Use consistent coding style
4. **Update documentation** - Keep docs current with changes

### Commits and PRs
1. **Descriptive commit messages** - Explain what and why
2. **Atomic commits** - One logical change per commit
3. **Test before committing** - Ensure all tests pass
4. **Review dependencies** - Check for circular dependencies

### Releases
1. **Create comprehensive release notes** - Document all changes
2. **Test release process** - Verify in staging environment
3. **Coordinate releases** - All packages released together
4. **Monitor post-release** - Watch for issues after deployment

## Troubleshooting

### Common Issues

1. **Dependency conflicts**: Run `./scripts/composer-update-all.sh`
2. **Test failures**: Check individual package with `cd packages/[name] && composer test`
3. **Version mismatches**: Run `./scripts/sync-ver.sh [version]`
4. **Missing dependencies**: Run `./scripts/composer-install-all.sh`

### Getting Help

- Check existing issues: https://github.com/cognesy/instructor-php/issues
- Review documentation: Each package has its own README.md
- Examine examples: Look in `examples/` directory
- Ask questions: Create an issue with the "question" label

## Contributing

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/my-feature`
3. **Make changes and test**: Follow the workflows above
4. **Submit a pull request**: Include description of changes
5. **Respond to feedback**: Address review comments promptly

Thank you for contributing to Instructor PHP!