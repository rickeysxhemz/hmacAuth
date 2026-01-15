# Artisan Commands

## hmac:install

Interactive setup wizard.

```bash
php artisan hmac:install [--force]
```

Publishes config, runs migrations, optionally generates first credentials.

---

## hmac:generate

Generate new credentials.

```bash
php artisan hmac:generate --company=1 [--environment=production] [--expires=2025-12-31]
```

| Option | Default |
|--------|---------|
| `--company` | Required |
| `--created-by` | `1` |
| `--environment` | `testing` |
| `--expires` | Never |

---

## hmac:rotate

Rotate a secret with grace period.

```bash
php artisan hmac:rotate <client_id> [--grace-days=7]
```

Both old and new secrets work during grace period.

---

## hmac:cleanup

Remove old request logs.

```bash
php artisan hmac:cleanup [--days=30] [--dry-run]
```

---

## Scheduling

```php
// routes/console.php (Laravel 11+)
Schedule::command('hmac:cleanup --days=30')->daily()->at('03:00');
```

---

## Programmatic Use

```php
Artisan::call('hmac:generate', ['--company' => 1, '--environment' => 'production']);
```
