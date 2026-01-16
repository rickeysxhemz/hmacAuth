# Contributing to Laravel HMAC Auth

Thank you for your interest in contributing to Laravel HMAC Auth! This document provides guidelines and instructions for contributing.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for everyone.

## Development Setup

### Prerequisites

- PHP 8.2 or higher
- Composer 2.x
- Redis (for nonce store and rate limiting tests)

### Installation

1. Fork and clone the repository:

```bash
git clone https://github.com/rickeysxhemz/hmacAuth.git
cd laravel-hmac-auth
```

2. Install dependencies:

```bash
composer install
```

3. Run tests to ensure everything is working:

```bash
composer test
```

## Development Workflow

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test:coverage

# Run specific test file
./vendor/bin/pest tests/Unit/Services/SignatureServiceTest.php

# Run tests matching a pattern
./vendor/bin/pest --filter="signature"
```

### Static Analysis

This project uses PHPStan at level 8:

```bash
composer analyse
```

### Code Style

This project follows Laravel coding standards enforced by Laravel Pint:

```bash
# Check code style
composer lint

# Fix code style issues
composer lint:fix
```

### Full Quality Check

Run all checks before submitting:

```bash
composer quality
```

This runs: code style, static analysis, and tests.

## Commit Guidelines

This project uses [Conventional Commits](https://www.conventionalcommits.org/) for consistent commit messages.

### Format

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

### Types

| Type | Description | Version Bump |
|------|-------------|--------------|
| `feat` | New feature | MINOR |
| `fix` | Bug fix | PATCH |
| `docs` | Documentation only | PATCH |
| `style` | Code style (formatting, semicolons) | PATCH |
| `refactor` | Code change that neither fixes a bug nor adds a feature | PATCH |
| `perf` | Performance improvement | PATCH |
| `test` | Adding or updating tests | PATCH |
| `chore` | Maintenance tasks | PATCH |

### Scopes

Common scopes for this project:

- `middleware` - HMAC middleware changes
- `services` - Service class changes
- `models` - Model changes
- `config` - Configuration changes
- `commands` - Artisan command changes
- `deps` - Dependency updates

### Examples

```bash
feat(middleware): add support for custom header names
fix(nonce): resolve race condition in concurrent requests
docs(readme): add Python client implementation example
test(services): add coverage for secret rotation edge cases
chore(deps): update phpstan to 2.1
```

### Breaking Changes

For breaking changes, add `!` after the type/scope and include `BREAKING CHANGE:` in the footer:

```bash
feat!(config): require explicit algorithm configuration

BREAKING CHANGE: The default algorithm is no longer applied automatically.
Users must explicitly set the algorithm in config/hmac.php.
```

## Pull Request Process

### Before Submitting

1. **Create an issue first** for significant changes to discuss the approach
2. **Branch from `main`** using a descriptive branch name:
   - `feat/add-custom-headers`
   - `fix/nonce-race-condition`
   - `docs/python-client-example`
3. **Keep changes focused** - one feature/fix per PR
4. **Update documentation** if adding new features
5. **Add tests** for new functionality (maintain 90%+ coverage)
6. **Update CHANGELOG.md** under `[Unreleased]` section

### PR Checklist

Before submitting your PR, ensure:

- [ ] Code follows project style guidelines (`composer lint`)
- [ ] All tests pass (`composer test`)
- [ ] Static analysis passes (`composer analyse`)
- [ ] New code has appropriate test coverage
- [ ] Documentation is updated (if applicable)
- [ ] CHANGELOG.md is updated
- [ ] Commit messages follow conventional commits format

### Review Process

1. Submit your PR against the `main` branch
2. Automated checks will run (tests, static analysis, code style)
3. A maintainer will review your changes
4. Address any feedback and push updates
5. Once approved, your PR will be merged

## Testing Guidelines

### Test Organization

```
tests/
├── Feature/           # Integration tests
│   ├── Middleware/    # Middleware tests
│   └── Commands/      # Artisan command tests
└── Unit/              # Unit tests
    ├── Services/      # Service class tests
    ├── DTOs/          # DTO tests
    └── Models/        # Model tests
```

### Writing Tests

Use Pest PHP for all tests:

```php
<?php

declare(strict_types=1);

use HmacAuth\Services\SignatureService;
use HmacAuth\DTOs\SignaturePayload;

describe('SignatureService', function () {
    it('generates consistent signatures for identical payloads', function () {
        $service = new SignatureService();
        $payload = new SignaturePayload(
            method: 'POST',
            path: '/api/test',
            body: '{"key":"value"}',
            timestamp: '1704067200',
            nonce: 'test-nonce-123'
        );

        $signature1 = $service->generate($payload, 'secret');
        $signature2 = $service->generate($payload, 'secret');

        expect($signature1)->toBe($signature2);
    });
});
```

### Test Coverage

- Maintain minimum 90% code coverage
- Focus on testing behavior, not implementation
- Test edge cases and error conditions
- Use data providers for testing multiple scenarios

## Reporting Bugs

### Before Reporting

1. Check existing issues to avoid duplicates
2. Verify the bug exists in the latest version
3. Collect relevant information (PHP version, Laravel version, etc.)

### Bug Report Contents

- Clear, descriptive title
- Steps to reproduce
- Expected behavior
- Actual behavior
- Environment details (PHP, Laravel, Redis versions)
- Relevant code snippets or logs

## Requesting Features

### Before Requesting

1. Check existing issues and discussions
2. Consider if it fits the project's scope
3. Think about backward compatibility

### Feature Request Contents

- Clear description of the problem you're solving
- Proposed solution
- Alternative approaches considered
- Potential impact on existing functionality

## Questions and Support

- **Documentation**: Check the `docs/` folder first
- **Discussions**: Use GitHub Discussions for questions
- **Issues**: Use for bugs and feature requests only

## Recognition

Contributors will be recognized in:

- GitHub contributors list
- CHANGELOG.md for significant contributions
- README.md acknowledgments section (for major contributors)

Thank you for contributing to Laravel HMAC Auth!
