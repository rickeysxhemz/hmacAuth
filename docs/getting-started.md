# Getting Started

## Installation

```bash
composer require hmacauth/laravel-hmac-auth
```

## Setup

```bash
# Run installer
php artisan hmac:install

# Run migrations
php artisan migrate
```

For multi-tenancy:

```bash
php artisan hmac:install --with-tenancy
```

## Generate Credentials

```bash
# Production
php artisan hmac:generate --environment=production

# Testing
php artisan hmac:generate --environment=testing

# With tenant (if multi-tenancy enabled)
php artisan hmac:generate --tenant=1
```

Save the `client_secret` shown - it cannot be retrieved later.

## Protect Routes

```php
Route::middleware('hmac.verify')->group(function () {
    Route::post('/api/orders', OrderController::class);
});
```

## Access Credential

```php
public function store(Request $request)
{
    $credential = $request->attributes->get('hmac_credential');
    $tenantId = $request->attributes->get('tenant_id'); // if tenancy enabled
}
```

## How It Works

```
Request Headers:
  X-Api-Key: {client_id}
  X-Signature: {hmac_signature}
  X-Timestamp: {unix_timestamp}
  X-Nonce: {unique_string}

Signature = HMAC-SHA256(
  "{METHOD}\n{PATH}\n{BODY}\n{TIMESTAMP}\n{NONCE}",
  client_secret
)
```

See [Client Examples](clients/) for implementation in various languages.
