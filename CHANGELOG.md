# Changelog

All notable changes to TeamGuard are documented here.

## 2.0.0 - Unreleased

### Added

- Laravel 11, 12, and 13 support on PHP 8.3+.
- Complete team and polymorphic membership migrations.
- Validated hierarchical teams with ancestor and descendant APIs.
- Idempotent assignment, synchronization, and membership query scopes.
- Membership events and integer, UUID, or ULID teamable keys.
- Behavioral tests, Larastan, Pint, Composer validation, and a CI matrix.

### Changed

- Rebuilt package configuration and service-provider publishing.
- Replaced `order` with `sort_order` and formalized active/inactive status values.
- Made documentation examples executable against the public API.

### Removed

- Incomplete global team cache, initializer, helper file, pivot model, and broken cache-reset command.
- Unsupported Laravel 9 and 10 runtimes.
