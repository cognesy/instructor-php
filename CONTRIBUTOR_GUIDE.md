# Contributor Guide

Welcome to the Instructor PHP monorepo! This guide will help you understand how to work with this project structure and perform common development tasks.

## Project Structure

This is a monorepo containing multiple independent packages under the `packages/` directory:

- **Core packages**: `instructor`, `config`, `events`, `messages`, `utils`, `schema`, `templates`
- **Extended functionality**: `addons`, `auxiliary`, `polyglot`, `setup`, `hub`, `tell`
- **Development tools**: `evals`, `experimental`
- **HTTP client**: `http-client`

Each package is independently publishable to Packagist with its own `composer.json`, tests, and documentation.

## Getting Started

### Prerequisites
- PHP 8.2+
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
- `composer hub` - Run Instructor Hub CLI
- `composer tell` - Run Tell CLI
- `composer setup` - Run setup wizard

#### Package-level commands:
Each package supports:
- `composer test` or `composer tests` - Run package tests
- `composer phpstan` - Static analysis
- `composer psalm` - Additional static analysis

### Testing

#### Test Individual Package
```bash
cd packages/instructor
composer test
```

#### Test All Packages
```bash
./scripts/run-all-tests.sh
```

This script:
- Clears composer cache for each package
- Dumps autoload files
- Installs dependencies
- Runs tests for each package

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

### 3. Complete Package Setup

```bash
cd packages/my-package
composer install
composer test
```

## Version Management and Releases

### Version Synchronization

All packages follow semantic versioning and are released together:

```bash
./scripts/sync-ver.sh 1.2.0
```

This script:
- Updates version constraints across all packages
- Ensures internal dependencies use compatible version ranges (^MAJOR.MINOR)
- Updates the following packages:
  - `cognesy/instructor-addons`
  - `cognesy/instructor-auxiliary`
  - `cognesy/instructor-config`
  - `cognesy/instructor-evals`
  - `cognesy/instructor-events`
  - `cognesy/instructor-http-client`
  - `cognesy/instructor-hub`
  - `cognesy/instructor-struct`
  - `cognesy/instructor-messages`
  - `cognesy/instructor-polyglot`
  - `cognesy/instructor-schema`
  - `cognesy/instructor-setup`
  - `cognesy/instructor-tell`
  - `cognesy/instructor-templates`
  - `cognesy/instructor-utils`

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
- Splits each package into separate repositories
- Creates individual releases for each package on Packagist

## Utilities and Helper Scripts

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