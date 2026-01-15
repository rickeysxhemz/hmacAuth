# Client Examples

## Signature Format

```
{METHOD}\n{PATH}\n{BODY}\n{TIMESTAMP}\n{NONCE}
```

## Required Headers

| Header | Example |
|--------|---------|
| `X-Api-Key` | `prod_a1b2c3d4e5f6g7h8` |
| `X-Signature` | Base64URL encoded HMAC |
| `X-Timestamp` | `1704067200` |
| `X-Nonce` | 32+ char random string |

## Languages

- [PHP](php.md)
- [JavaScript / Node.js](javascript.md)
- [Python](python.md)
- [cURL / Bash](curl.md)

## Base64URL Encoding

```php
// PHP
rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
```

```javascript
// JavaScript
buffer.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
```

```python
# Python
base64.urlsafe_b64encode(data).rstrip(b'=').decode()
```
