# Client Implementation

This guide provides examples for implementing HMAC authentication in various programming languages.

## Overview

### Required Headers

| Header | Description | Example |
|--------|-------------|---------|
| `X-Api-Key` | Your client ID | `prod_a1b2c3d4e5f6g7h8` |
| `X-Signature` | Base64URL-encoded HMAC signature | `dGhpcyBpcyBhIHNpZ25hdHVyZQ` |
| `X-Timestamp` | Unix timestamp (seconds) | `1704067200` |
| `X-Nonce` | Unique random string (min 16 chars) | `a1b2c3d4e5f6g7h8i9j0` |

### Signature Payload Format

The signature is computed over a canonical string in this exact format:

```
{METHOD}\n{PATH}\n{BODY}\n{TIMESTAMP}\n{NONCE}
```

**Example:**

```
POST
/api/data
{"key":"value"}
1704067200
a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

### Signature Algorithm

1. Construct the canonical string
2. Compute HMAC-SHA256 (or SHA384/SHA512) of the canonical string using your client secret
3. Encode the result as Base64URL (URL-safe Base64 without padding)

---

## PHP Client

### Using Guzzle

```php
<?php

declare(strict_types=1);

namespace App\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class HmacApiClient
{
    private Client $client;
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private string $algorithm;

    public function __construct(
        string $baseUrl,
        string $clientId,
        string $clientSecret,
        string $algorithm = 'sha256'
    ) {
        $this->client = new Client(['base_uri' => $baseUrl]);
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->algorithm = $algorithm;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, [], $data);
    }

    public function put(string $path, array $data = []): array
    {
        return $this->request('PUT', $path, [], $data);
    }

    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    private function request(
        string $method,
        string $path,
        array $query = [],
        array $data = []
    ): array {
        $timestamp = (string) time();
        $nonce = $this->generateNonce();
        $body = !empty($data) ? json_encode($data) : '';

        // Build path with query string
        $fullPath = $path;
        if (!empty($query)) {
            $fullPath .= '?' . http_build_query($query);
        }

        $signature = $this->generateSignature($method, $fullPath, $body, $timestamp, $nonce);

        $headers = [
            'X-Api-Key' => $this->clientId,
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $options = [
            'headers' => $headers,
            'query' => $query,
        ];

        if (!empty($data)) {
            $options['body'] = $body;
        }

        $response = $this->client->request($method, $path, $options);

        return json_decode($response->getBody()->getContents(), true);
    }

    private function generateSignature(
        string $method,
        string $path,
        string $body,
        string $timestamp,
        string $nonce
    ): string {
        $payload = implode("\n", [
            strtoupper($method),
            $path,
            $body,
            $timestamp,
            $nonce,
        ]);

        $hmac = hash_hmac($this->algorithm, $payload, $this->clientSecret, true);

        return $this->base64UrlEncode($hmac);
    }

    private function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
```

### Usage

```php
$client = new HmacApiClient(
    baseUrl: 'https://api.example.com',
    clientId: 'prod_a1b2c3d4e5f6g7h8',
    clientSecret: 'your-client-secret'
);

// GET request
$users = $client->get('/api/users', ['page' => 1]);

// POST request
$response = $client->post('/api/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);
```

---

## JavaScript / Node.js

### Using Node.js Crypto

```javascript
const crypto = require('crypto');
const https = require('https');

class HmacApiClient {
  constructor(baseUrl, clientId, clientSecret, algorithm = 'sha256') {
    this.baseUrl = new URL(baseUrl);
    this.clientId = clientId;
    this.clientSecret = clientSecret;
    this.algorithm = algorithm;
  }

  async get(path, query = {}) {
    return this.request('GET', path, query);
  }

  async post(path, data = {}) {
    return this.request('POST', path, {}, data);
  }

  async put(path, data = {}) {
    return this.request('PUT', path, {}, data);
  }

  async delete(path) {
    return this.request('DELETE', path);
  }

  async request(method, path, query = {}, data = null) {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonce = this.generateNonce();
    const body = data ? JSON.stringify(data) : '';

    // Build path with query string
    let fullPath = path;
    const queryString = new URLSearchParams(query).toString();
    if (queryString) {
      fullPath += '?' + queryString;
    }

    const signature = this.generateSignature(method, fullPath, body, timestamp, nonce);

    const options = {
      hostname: this.baseUrl.hostname,
      port: this.baseUrl.port || 443,
      path: fullPath,
      method: method,
      headers: {
        'X-Api-Key': this.clientId,
        'X-Signature': signature,
        'X-Timestamp': timestamp,
        'X-Nonce': nonce,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    };

    return new Promise((resolve, reject) => {
      const req = https.request(options, (res) => {
        let responseData = '';
        res.on('data', (chunk) => (responseData += chunk));
        res.on('end', () => {
          try {
            resolve(JSON.parse(responseData));
          } catch (e) {
            resolve(responseData);
          }
        });
      });

      req.on('error', reject);

      if (body) {
        req.write(body);
      }
      req.end();
    });
  }

  generateSignature(method, path, body, timestamp, nonce) {
    const payload = [method.toUpperCase(), path, body, timestamp, nonce].join('\n');

    const hmac = crypto.createHmac(this.algorithm, this.clientSecret);
    hmac.update(payload);
    const hash = hmac.digest();

    return this.base64UrlEncode(hash);
  }

  generateNonce() {
    return crypto.randomBytes(16).toString('hex');
  }

  base64UrlEncode(buffer) {
    return buffer.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }
}

module.exports = HmacApiClient;
```

### Usage (Node.js)

```javascript
const HmacApiClient = require('./hmac-client');

const client = new HmacApiClient(
  'https://api.example.com',
  'prod_a1b2c3d4e5f6g7h8',
  'your-client-secret'
);

// GET request
const users = await client.get('/api/users', { page: 1 });

// POST request
const response = await client.post('/api/users', {
  name: 'John Doe',
  email: 'john@example.com',
});
```

### Browser (Web Crypto API)

```javascript
class HmacApiClient {
  constructor(baseUrl, clientId, clientSecret, algorithm = 'SHA-256') {
    this.baseUrl = baseUrl;
    this.clientId = clientId;
    this.clientSecret = clientSecret;
    this.algorithm = algorithm;
  }

  async generateSignature(method, path, body, timestamp, nonce) {
    const payload = [method.toUpperCase(), path, body, timestamp, nonce].join('\n');

    const encoder = new TextEncoder();
    const keyData = encoder.encode(this.clientSecret);
    const messageData = encoder.encode(payload);

    const key = await crypto.subtle.importKey(
      'raw',
      keyData,
      { name: 'HMAC', hash: this.algorithm },
      false,
      ['sign']
    );

    const signature = await crypto.subtle.sign('HMAC', key, messageData);
    return this.base64UrlEncode(new Uint8Array(signature));
  }

  generateNonce() {
    const array = new Uint8Array(16);
    crypto.getRandomValues(array);
    return Array.from(array, (b) => b.toString(16).padStart(2, '0')).join('');
  }

  base64UrlEncode(array) {
    const base64 = btoa(String.fromCharCode(...array));
    return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  async request(method, path, data = null) {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonce = this.generateNonce();
    const body = data ? JSON.stringify(data) : '';

    const signature = await this.generateSignature(method, path, body, timestamp, nonce);

    const response = await fetch(`${this.baseUrl}${path}`, {
      method,
      headers: {
        'X-Api-Key': this.clientId,
        'X-Signature': signature,
        'X-Timestamp': timestamp,
        'X-Nonce': nonce,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: body || undefined,
    });

    return response.json();
  }
}
```

---

## Python

```python
import hmac
import hashlib
import time
import secrets
import base64
import json
import requests
from urllib.parse import urlencode


class HmacApiClient:
    def __init__(self, base_url: str, client_id: str, client_secret: str, algorithm: str = "sha256"):
        self.base_url = base_url.rstrip("/")
        self.client_id = client_id
        self.client_secret = client_secret
        self.algorithm = algorithm

    def get(self, path: str, params: dict = None) -> dict:
        return self._request("GET", path, params=params)

    def post(self, path: str, data: dict = None) -> dict:
        return self._request("POST", path, data=data)

    def put(self, path: str, data: dict = None) -> dict:
        return self._request("PUT", path, data=data)

    def delete(self, path: str) -> dict:
        return self._request("DELETE", path)

    def _request(self, method: str, path: str, params: dict = None, data: dict = None) -> dict:
        timestamp = str(int(time.time()))
        nonce = self._generate_nonce()
        body = json.dumps(data, separators=(",", ":")) if data else ""

        # Build path with query string
        full_path = path
        if params:
            full_path += "?" + urlencode(params)

        signature = self._generate_signature(method, full_path, body, timestamp, nonce)

        headers = {
            "X-Api-Key": self.client_id,
            "X-Signature": signature,
            "X-Timestamp": timestamp,
            "X-Nonce": nonce,
            "Content-Type": "application/json",
            "Accept": "application/json",
        }

        url = f"{self.base_url}{path}"

        response = requests.request(
            method=method,
            url=url,
            headers=headers,
            params=params,
            data=body if body else None,
        )

        return response.json()

    def _generate_signature(self, method: str, path: str, body: str, timestamp: str, nonce: str) -> str:
        payload = "\n".join([method.upper(), path, body, timestamp, nonce])

        hash_func = getattr(hashlib, self.algorithm)
        signature = hmac.new(
            self.client_secret.encode(),
            payload.encode(),
            hash_func
        ).digest()

        return self._base64url_encode(signature)

    def _generate_nonce(self) -> str:
        return secrets.token_hex(16)

    def _base64url_encode(self, data: bytes) -> str:
        return base64.urlsafe_b64encode(data).rstrip(b"=").decode()


# Usage
if __name__ == "__main__":
    client = HmacApiClient(
        base_url="https://api.example.com",
        client_id="prod_a1b2c3d4e5f6g7h8",
        client_secret="your-client-secret"
    )

    # GET request
    users = client.get("/api/users", params={"page": 1})

    # POST request
    response = client.post("/api/users", data={
        "name": "John Doe",
        "email": "john@example.com"
    })
```

---

## cURL

### Bash Script

```bash
#!/bin/bash

# Configuration
BASE_URL="https://api.example.com"
CLIENT_ID="prod_a1b2c3d4e5f6g7h8"
CLIENT_SECRET="your-client-secret"
ALGORITHM="sha256"

# Function to generate HMAC signature
generate_signature() {
    local method="$1"
    local path="$2"
    local body="$3"
    local timestamp="$4"
    local nonce="$5"

    # Construct payload
    local payload="${method}
${path}
${body}
${timestamp}
${nonce}"

    # Generate HMAC and Base64URL encode
    echo -n "$payload" | \
        openssl dgst -${ALGORITHM} -hmac "$CLIENT_SECRET" -binary | \
        base64 | \
        tr '+/' '-_' | \
        tr -d '='
}

# Function to make authenticated request
hmac_request() {
    local method="$1"
    local path="$2"
    local body="${3:-}"

    local timestamp=$(date +%s)
    local nonce=$(openssl rand -hex 16)
    local signature=$(generate_signature "$method" "$path" "$body" "$timestamp" "$nonce")

    local curl_opts=(
        -X "$method"
        -H "X-Api-Key: $CLIENT_ID"
        -H "X-Signature: $signature"
        -H "X-Timestamp: $timestamp"
        -H "X-Nonce: $nonce"
        -H "Content-Type: application/json"
        -H "Accept: application/json"
    )

    if [ -n "$body" ]; then
        curl_opts+=(-d "$body")
    fi

    curl "${curl_opts[@]}" "${BASE_URL}${path}"
}

# Examples
# GET request
hmac_request "GET" "/api/users"

# POST request
hmac_request "POST" "/api/users" '{"name":"John Doe","email":"john@example.com"}'
```

### One-liner Example

```bash
# Set variables
METHOD="GET"
PATH="/api/users"
BODY=""
TIMESTAMP=$(date +%s)
NONCE=$(openssl rand -hex 16)
CLIENT_ID="prod_a1b2c3d4e5f6g7h8"
CLIENT_SECRET="your-client-secret"

# Generate signature
PAYLOAD="${METHOD}
${PATH}
${BODY}
${TIMESTAMP}
${NONCE}"

SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$CLIENT_SECRET" -binary | base64 | tr '+/' '-_' | tr -d '=')

# Make request
curl -X "$METHOD" \
    -H "X-Api-Key: $CLIENT_ID" \
    -H "X-Signature: $SIGNATURE" \
    -H "X-Timestamp: $TIMESTAMP" \
    -H "X-Nonce: $NONCE" \
    "https://api.example.com$PATH"
```

---

## Common Issues

### Clock Synchronization

Ensure your client's clock is synchronized with the server. Use NTP to keep accurate time.

```bash
# Check time offset
curl -I https://api.example.com | grep -i date
```

### Body Encoding

The request body must be identical when signing and sending:
- Use consistent JSON encoding (no pretty printing)
- Ensure UTF-8 encoding
- Empty body should be an empty string, not null

### Path Normalization

The path used for signing must match the path in the request:
- Include query parameters in the signed path
- Use consistent URL encoding
- No trailing slashes (unless the server expects them)

### Base64URL Encoding

Standard Base64 uses `+/` and `=` padding. Base64URL uses `-_` without padding:

```php
// PHP
rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

// JavaScript
buffer.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

// Python
base64.urlsafe_b64encode(data).rstrip(b'=').decode()
```
