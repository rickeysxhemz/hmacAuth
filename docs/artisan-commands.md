# Artisan Commands

Complete reference for the CLI commands provided by Laravel HMAC Auth.

## hmac:install

Interactive setup wizard for first-time installation.

### Usage

```bash
php artisan hmac:install
```

### Description

This command guides you through the initial setup:

1. Publishes the configuration file (`config/hmac.php`)
2. Publishes database migrations
3. Runs the migrations
4. Optionally generates your first API credentials

### Options

| Option | Description |
|--------|-------------|
| `--force` | Overwrite existing configuration file |

### Example

```bash
$ php artisan hmac:install

 HMAC Auth Installation
 ======================

 Publishing configuration...
 Configuration published successfully!

 Publishing migrations...
 Migrations published successfully!

 Running migrations...
 Migrating: 2024_01_01_000001_create_api_credentials_table
 Migrated:  2024_01_01_000001_create_api_credentials_table (15.23ms)
 Migrating: 2024_01_01_000002_create_api_request_logs_table
 Migrated:  2024_01_01_000002_create_api_request_logs_table (12.45ms)

 Would you like to generate your first API credentials? (yes/no) [yes]:
 > yes

 Company ID:
 > 1

 Environment (production/staging/testing) [production]:
 > production

 ┌───────────────┬──────────────────────────────────────────────────────────────────┐
 │ Field         │ Value                                                            │
 ├───────────────┼──────────────────────────────────────────────────────────────────┤
 │ Client ID     │ prod_a1b2c3d4e5f6g7h8                                            │
 │ Client Secret │ 9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08 │
 │ Environment   │ production                                                        │
 │ Expires At    │ Never                                                             │
 └───────────────┴──────────────────────────────────────────────────────────────────┘

 IMPORTANT: Save the Client Secret now! It cannot be retrieved later.

 Installation complete!
```

---

## hmac:generate

Generate new API credentials.

### Usage

```bash
php artisan hmac:generate [options]
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--company=ID` | Company ID to associate with credentials | Required |
| `--created-by=ID` | User ID who created the credentials | `1` |
| `--environment=ENV` | Environment (production/staging/testing) | `testing` |
| `--expires=DATE` | Expiration date (YYYY-MM-DD format) | Never |

### Examples

#### Basic Generation

```bash
php artisan hmac:generate --company=1
```

#### Production Credentials with Expiration

```bash
php artisan hmac:generate \
    --company=1 \
    --environment=production \
    --expires=2025-12-31 \
    --created-by=5
```

#### Output

```bash
$ php artisan hmac:generate --company=1 --environment=production

 Generating API credentials...

 ┌───────────────┬──────────────────────────────────────────────────────────────────┐
 │ Field         │ Value                                                            │
 ├───────────────┼──────────────────────────────────────────────────────────────────┤
 │ Client ID     │ prod_a1b2c3d4e5f6g7h8                                            │
 │ Client Secret │ 9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08 │
 │ Company ID    │ 1                                                                 │
 │ Environment   │ production                                                        │
 │ Created By    │ 1                                                                 │
 │ Expires At    │ Never                                                             │
 └───────────────┴──────────────────────────────────────────────────────────────────┘

 IMPORTANT: Save the Client Secret now! It will not be shown again.
```

---

## hmac:rotate

Rotate the secret for an existing API credential.

### Usage

```bash
php artisan hmac:rotate <credential> [options]
```

### Arguments

| Argument | Description |
|----------|-------------|
| `credential` | The credential ID or client_id to rotate |

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--grace-days=DAYS` | Number of days the old secret remains valid | `7` |

### Description

Secret rotation allows you to update credentials without immediate downtime:

1. A new secret is generated
2. The old secret is stored with an expiration date
3. Both secrets are valid during the grace period
4. After the grace period, only the new secret works

### Examples

#### Rotate by Client ID

```bash
php artisan hmac:rotate prod_a1b2c3d4e5f6g7h8
```

#### Rotate with Custom Grace Period

```bash
php artisan hmac:rotate prod_a1b2c3d4e5f6g7h8 --grace-days=14
```

#### Rotate by Database ID

```bash
php artisan hmac:rotate 42
```

#### Output

```bash
$ php artisan hmac:rotate prod_a1b2c3d4e5f6g7h8 --grace-days=7

 Rotating secret for credential: prod_a1b2c3d4e5f6g7h8

 Are you sure you want to rotate this secret? (yes/no) [no]:
 > yes

 Secret rotated successfully!

 ┌────────────────────┬──────────────────────────────────────────────────────────────────┐
 │ Field              │ Value                                                            │
 ├────────────────────┼──────────────────────────────────────────────────────────────────┤
 │ Client ID          │ prod_a1b2c3d4e5f6g7h8                                            │
 │ New Secret         │ b4d0c3e2a1f8g7h6i5j4k3l2m1n0o9p8q7r6s5t4u3v2w1x0y9z8a7b6c5d4e3f2 │
 │ Grace Period       │ 7 days                                                            │
 │ Old Secret Expires │ 2026-01-15 14:30:00                                              │
 └────────────────────┴──────────────────────────────────────────────────────────────────┘

 IMPORTANT: Update your application with the new secret.
 The old secret will remain valid until 2026-01-15 14:30:00
```

---

## hmac:cleanup

Clean up old request logs.

### Usage

```bash
php artisan hmac:cleanup [options]
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--days=DAYS` | Delete logs older than this many days | `30` |
| `--dry-run` | Preview what would be deleted without actually deleting | `false` |

### Description

Request logs can accumulate over time. This command helps manage database size by removing old logs while preserving recent audit trails.

### Examples

#### Default Cleanup (30 days)

```bash
php artisan hmac:cleanup
```

#### Preview Cleanup

```bash
php artisan hmac:cleanup --dry-run
```

#### Custom Retention Period

```bash
php artisan hmac:cleanup --days=90
```

#### Output

```bash
$ php artisan hmac:cleanup --days=30 --dry-run

 Scanning for logs older than 30 days...

 Found 1,234 logs to delete.

 This is a dry run. No logs were deleted.
 Run without --dry-run to delete these logs.

$ php artisan hmac:cleanup --days=30

 Scanning for logs older than 30 days...

 Found 1,234 logs to delete.

 Are you sure you want to delete these logs? (yes/no) [no]:
 > yes

 Deleted 1,234 request logs.
```

---

## Scheduling Commands

You can schedule the cleanup command to run automatically in `app/Console/Kernel.php` (Laravel 10) or `routes/console.php` (Laravel 11+):

### Laravel 11+

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('hmac:cleanup --days=30')
    ->daily()
    ->at('03:00')
    ->runInBackground();
```

### Laravel 10

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('hmac:cleanup --days=30')
        ->daily()
        ->at('03:00')
        ->runInBackground();
}
```

---

## Exit Codes

All commands follow standard exit codes:

| Code | Constant | Meaning |
|------|----------|---------|
| `0` | `SUCCESS` | Command completed successfully |
| `1` | `FAILURE` | Command failed with an error |

---

## Using in Scripts

Commands can be called programmatically:

```php
use Illuminate\Support\Facades\Artisan;

// Generate credentials
Artisan::call('hmac:generate', [
    '--company' => 1,
    '--environment' => 'production',
]);

$output = Artisan::output();

// Cleanup with confirmation bypass
Artisan::call('hmac:cleanup', [
    '--days' => 30,
    '--no-interaction' => true,
]);
```
