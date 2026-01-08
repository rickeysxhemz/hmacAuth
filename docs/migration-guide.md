# Migration Guide

This guide covers upgrading between major versions of Laravel HMAC Auth.

## Version Compatibility Matrix

| Package Version | PHP Version | Laravel Version |
|-----------------|-------------|-----------------|
| 1.x             | 8.2 - 8.4   | 11.x - 12.x     |

---

## Upgrading to 1.x

### From Pre-Release Versions

If you were using a development version before the stable 1.0 release:

#### 1. Update Composer

```bash
composer require your-vendor/laravel-hmac-auth:^1.0
```

#### 2. Publish New Migrations

```bash
php artisan vendor:publish --tag=hmac-migrations --force
```

#### 3. Run Migrations

```bash
php artisan migrate
```

#### 4. Update Configuration

Publish the updated configuration:

```bash
php artisan vendor:publish --tag=hmac-config --force
```

Review and merge any custom settings from your backup.

#### 5. Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

---

## Breaking Changes Log

### Version 1.0.0

This is the initial stable release. No breaking changes from pre-release development versions that would affect standard usage.

---

## Future Migration Guides

This section will be updated with migration guides for future major versions.

### Preparing for Future Updates

To minimize upgrade friction:

1. **Use interfaces**: Depend on contracts, not concrete implementations
2. **Don't extend internal classes**: Use composition over inheritance
3. **Keep configuration up to date**: Review release notes for new options
4. **Test thoroughly**: Run your test suite after each update

---

## Database Migrations

### Manual Migration Updates

If you've customized the default migrations, you may need to manually update your database schema.

#### API Credentials Table

The `api_credentials` table requires these columns:

```php
Schema::create('api_credentials', function (Blueprint $table) {
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

    $table->index(['is_active', 'environment']);
});
```

#### API Request Logs Table

The `api_request_logs` table requires these columns:

```php
Schema::create('api_request_logs', function (Blueprint $table) {
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

    $table->index(['created_at']);
    $table->index(['status', 'created_at']);
});
```

---

## Configuration Changes

### Detecting Configuration Changes

After updating, compare your configuration with the new defaults:

```bash
# View current config
php artisan config:show hmac

# View default config
cat vendor/your-vendor/laravel-hmac-auth/config/hmac.php
```

### New Configuration Options

When new options are added in updates, they will use sensible defaults. To explicitly set them:

```bash
# Re-publish configuration (backup existing first!)
cp config/hmac.php config/hmac.php.backup
php artisan vendor:publish --tag=hmac-config --force

# Merge your customizations
# Compare config/hmac.php with config/hmac.php.backup
```

---

## Namespace Changes

If namespaces change in a major version, use class aliases for gradual migration:

```php
// In AppServiceProvider.php
public function register(): void
{
    // If OldNamespace\Class moved to NewNamespace\Class
    class_alias(
        \NewNamespace\SomeClass::class,
        'OldNamespace\SomeClass'
    );
}
```

---

## Event Changes

If event classes change, update your listeners:

```php
// EventServiceProvider.php
protected $listen = [
    // Old event
    // \HmacAuth\Events\OldEvent::class => [...]

    // New event
    \HmacAuth\Events\AuthenticationSucceeded::class => [
        \App\Listeners\LogSuccessfulAuth::class,
    ],
];
```

---

## Testing After Upgrade

### 1. Run Package Tests

```bash
composer test
```

### 2. Verify Authentication Flow

```php
// tests/Feature/HmacAuthTest.php
public function test_authentication_works_after_upgrade(): void
{
    $credential = ApiCredential::factory()->create();

    // Generate valid signature
    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));
    $signature = $this->generateSignature(
        'GET',
        '/api/test',
        '',
        $timestamp,
        $nonce,
        $credential->client_secret
    );

    $response = $this->withHeaders([
        'X-Api-Key' => $credential->client_id,
        'X-Signature' => $signature,
        'X-Timestamp' => $timestamp,
        'X-Nonce' => $nonce,
    ])->get('/api/test');

    $response->assertOk();
}
```

### 3. Check Redis Connection

```bash
php artisan tinker
>>> app(\HmacAuth\Contracts\NonceStoreInterface::class)->store('test-nonce');
>>> app(\HmacAuth\Contracts\NonceStoreInterface::class)->exists('test-nonce');
// Should return true
```

### 4. Verify Database

```bash
php artisan tinker
>>> \HmacAuth\Models\ApiCredential::count();
>>> \HmacAuth\Models\ApiRequestLog::count();
```

---

## Rollback Procedure

If you need to rollback after an upgrade:

### 1. Restore Previous Version

```bash
composer require your-vendor/laravel-hmac-auth:^0.x  # Previous version
```

### 2. Restore Configuration

```bash
cp config/hmac.php.backup config/hmac.php
```

### 3. Rollback Migrations (if needed)

```bash
php artisan migrate:rollback --step=X  # X = number of migrations to rollback
```

### 4. Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
```

---

## Getting Help

If you encounter issues during migration:

1. Check the [CHANGELOG](../CHANGELOG.md) for detailed changes
2. Review [Troubleshooting](troubleshooting.md) for common issues
3. Open an issue with:
   - Previous version
   - New version
   - Error messages
   - Steps to reproduce
