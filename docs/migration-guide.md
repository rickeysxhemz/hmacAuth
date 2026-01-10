# Migration Guide

## Compatibility

| Package | PHP | Laravel |
|---------|-----|---------|
| 1.x | 8.2 - 8.4 | 11.x - 12.x |

---

## Upgrade to 1.x

```bash
composer require your-vendor/laravel-hmac-auth:^1.0
php artisan vendor:publish --tag=hmac-migrations --force
php artisan migrate
php artisan config:clear && php artisan cache:clear
```

---

## Database Schema

### api_credentials

```php
$table->id();
$table->string('client_id', 64)->unique();
$table->text('client_secret');
$table->text('old_client_secret')->nullable();
$table->timestamp('old_secret_expires_at')->nullable();
$table->string('hmac_algorithm', 16)->default('sha256');
$table->unsignedBigInteger('company_id')->index();
$table->unsignedBigInteger('created_by')->index();
$table->string('environment', 32)->default('testing');
$table->boolean('is_active')->default(true);
$table->timestamp('expires_at')->nullable();
$table->timestamp('last_used_at')->nullable();
$table->timestamps();
```

### api_request_logs

```php
$table->id();
$table->string('client_id', 64)->index();
$table->foreignId('api_credential_id')->nullable()->constrained()->nullOnDelete();
$table->string('request_method', 10);
$table->string('request_path', 2048);
$table->string('ip_address', 45)->index();
$table->string('user_agent', 512)->nullable();
$table->string('status', 16);
$table->string('failure_reason', 64)->nullable();
$table->timestamp('created_at')->useCurrent();
```

---

## Verify After Upgrade

```bash
composer test
php artisan config:show hmac
```

---

## Rollback

```bash
composer require your-vendor/laravel-hmac-auth:^0.x
cp config/hmac.php.backup config/hmac.php
php artisan migrate:rollback --step=X
php artisan config:clear
```
