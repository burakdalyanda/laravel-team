# Contributing

Create a branch from `dev` and keep each commit focused on one coherent behavior or maintenance concern.

Install dependencies and run the complete local gate before opening a pull request:

```bash
composer install
composer verify
```

Tests should verify observable behavior and important failure paths. Do not weaken an assertion to accommodate a regression. Add a focused regression test first when fixing a bug.

Pull requests should explain the intended behavior, compatibility impact, and commands actually run. Do not include credentials, local environment files, generated caches, or editor configuration.
