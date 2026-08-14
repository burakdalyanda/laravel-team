# Upgrading TeamGuard

## From v1 to v2

Version 2 is a reimplementation of the package's team membership purpose. Review the following changes before upgrading.

### Runtime requirements

TeamGuard v2 requires PHP 8.3+ and supports Laravel 12 and 13. Laravel 9–11 are no longer supported. Laravel 11 is outside its security-support window and current Composer security policy blocks its available framework releases.

### Composer package name

The package name is `burakdalyanda/laravel-team`. Earlier README examples incorrectly used `burakdalyanda/laravel-team-guard`.

### Publish tags

The supported tags are now stable and match the documentation:

```bash
php artisan vendor:publish --tag=team-guard-config
php artisan vendor:publish --tag=team-guard-migrations
```

### Database schema

The v1 migration stub was empty, so v2 defines the first complete supported schema. Compare the published migration with any application-specific tables before running it.

The `teams` table uses:

- `name`, which is unique;
- nullable `description` and `parent_id`;
- `status` with `active` and `inactive` values;
- `sort_order` instead of the ambiguous `order` attribute;
- timestamps.

The polymorphic pivot defaults to `model_has_teams` with a composite uniqueness constraint. Existing duplicate pivot rows must be removed before applying an equivalent constraint.

### Configuration

The unused `models.user`, cache options, and Spatie feature flag were removed. They did not provide working behavior in v1.

`model_key_type` now explicitly supports integer, UUID, and ULID keys for teamable models. Set it before publishing the migration.

### Membership API

The following v1 names remain available:

- `assignTeam()`
- `assignToTeam()`
- `removeTeam()`
- `syncTeam()`
- `syncTeamWithoutDetach()`
- `hasTeam()`
- `getTeamIds()`
- `getModelTeam()`

Prefer `syncTeams()`, `syncTeamsWithoutDetaching()`, and `firstTeam()` in new code. Assignments now accept persisted team models, IDs, and existing names, and duplicate assignment is idempotent.

### Cache removal

The v1 initializer and `team:cache-reset` command were removed. They referenced incomplete classes and could return stale membership data. Version 2 queries the relationship directly and relies on the database uniqueness constraint for integrity. Applications that need caching should cache complete authorization decisions with application-specific invalidation.

### Hierarchy behavior

Hierarchy methods are now implemented and cycles are rejected. Deleting a parent sets its children's `parent_id` to `null`.

### Events

Use `TeamAssigned`, `TeamRemoved`, and `TeamsSynced` for membership side effects. Listeners should be idempotent because application queues may retry them.
