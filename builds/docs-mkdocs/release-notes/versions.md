## Versioning

### Versioning Rules

Starting from version 1.0.0 Instructor follows semantic versioning (SemVer) with version numbers in the format x.y.z (e.g., 1.2.3, where x is the major version, y is the minor version, and z is the patch version).

Use these rules to plan updates for the Instructor library:

 - **Major version (x):** Incremented for significant changes, such as extensive refactoring of the core library or breaking changes to public APIs that are incompatible with previous versions. Major version updates may not be fully incompatible, but compatibility depends on the specific changes. Always consult the upgrade guide for the corresponding major version to understand the impact.
 - **Minor version (y):** Incremented for backward-compatible additions, such as new features or components, or for breaking changes to a limited subset of public APIs (e.g., modifying or removing specific APIs). While minor versions aim to maintain compatibility, breaking changes in these releases may affect some use cases. Refer to the upgrade guide for details.
 - **Patch version (z):** Incremented for backward-compatible bug fixes, security patches, or minor enhancements that do not affect existing functionality. In rare cases, a patch release may include breaking changes to fix a completely unusable feature, but these changes are not treated as minor version updates since the affected functionality was already broken. New features or components introduced in patch releases are designed to be backward-compatible and should not impact existing code.

### Upgrading Instructor

When upgrading the Instructor library, follow these guidelines:

 - **Major (x) or Minor (y) Upgrades:** Review the upgrade guide for the specific version in the documentation. These upgrades may include breaking changes or new features that require code adjustments.
 - **Patch (z) Upgrades:** These are backward-compatible and can typically be applied by running composer update instructor-php in your project’s root directory to update the dependency. No additional changes are usually required.

We recommend upgrading all Instructor components together to ensure a consistent development experience, rather than updating individual components separately.
