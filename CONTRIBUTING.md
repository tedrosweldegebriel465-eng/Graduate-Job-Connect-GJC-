# Contributing to Graduate Job Connect

Thank you for your interest in contributing. This guide explains the workflow.

---

## Getting Started

1. Fork the repository
2. Clone your fork: `git clone https://github.com/yourusername/graduate-job-connect.git`
3. Create a feature branch: `git checkout -b feat/your-feature-name`
4. Set up the project (see [README.md](README.md#installation))

---

## Branch Naming

| Prefix | Use case |
|---|---|
| `feat/` | New feature |
| `fix/` | Bug fix |
| `refactor/` | Code cleanup, no behaviour change |
| `docs/` | Documentation only |
| `test/` | Tests only |
| `chore/` | Build scripts, CI, dependencies |

Example: `feat/ai-job-recommendations`

---

## Code Standards

- PHP 8.1+, `declare(strict_types=1)` on all new files
- PSR-4 namespacing under `App\`
- No raw SQL in Controller or View files — always use Models
- All form submissions must verify a CSRF token via `AuthMiddleware::verifyCsrf()`
- Escape all output with `ViewHelper::e()` or `htmlspecialchars()`
- Use `PDO` prepared statements — never string-concatenate user input into SQL
- Follow the existing file structure (Controllers / Models / Services / Views pattern)

---

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add AI job recommendation scoring
fix: correct salary range validation in JobService
docs: update API reference for /applications endpoint
test: add ApplicationService unit tests
```

---

## Pull Request Checklist

- [ ] Code follows the style guidelines above
- [ ] No raw SQL in views or controllers
- [ ] All new forms include CSRF token validation
- [ ] All output is escaped
- [ ] `php -l` passes on all changed files
- [ ] PHPUnit tests pass (if applicable)
- [ ] `CHANGELOG.md` updated under `[Unreleased]`
- [ ] PR description explains what changed and why

---

## Running Tests

```bash
# Install PHPUnit
composer install --dev

# Run all tests
composer test

# Single test file
vendor/bin/phpunit tests/AuthServiceTest.php
```

---

## Reporting Bugs

See [SECURITY.md](SECURITY.md) for security vulnerabilities.

For non-security bugs, open a GitHub Issue with:
- PHP and MySQL version
- Steps to reproduce
- Expected vs. actual behaviour
- Relevant error logs
