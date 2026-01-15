# JavaScript Client

## Node.js

```javascript
const crypto = require('crypto');

class HmacClient {
  constructor(baseUrl, clientId, clientSecret, algorithm = 'sha256') {
    this.baseUrl = baseUrl;
    this.clientId = clientId;
    this.clientSecret = clientSecret;
    this.algorithm = algorithm;
  }

  async request(method, path, data = null) {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonce = crypto.randomBytes(16).toString('hex');
    const body = data ? JSON.stringify(data) : '';

    const signature = this.sign(method, path, body, timestamp, nonce);

    const response = await fetch(`${this.baseUrl}${path}`, {
      method,
      headers: {
        'X-Api-Key': this.clientId,
        'X-Signature': signature,
        'X-Timestamp': timestamp,
        'X-Nonce': nonce,
        'Content-Type': 'application/json',
      },
      body: body || undefined,
    });

    return response.json();
  }

  sign(method, path, body, timestamp, nonce) {
    const payload = [method.toUpperCase(), path, body, timestamp, nonce].join('\n');
    const hmac = crypto.createHmac(this.algorithm, this.clientSecret).update(payload).digest();
    return hmac.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }
}

module.exports = HmacClient;
```

## Browser

```javascript
class HmacClient {
  constructor(baseUrl, clientId, clientSecret) {
    this.baseUrl = baseUrl;
    this.clientId = clientId;
    this.clientSecret = clientSecret;
  }

  async request(method, path, data = null) {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonce = this.generateNonce();
    const body = data ? JSON.stringify(data) : '';

    const signature = await this.sign(method, path, body, timestamp, nonce);

    const response = await fetch(`${this.baseUrl}${path}`, {
      method,
      headers: {
        'X-Api-Key': this.clientId,
        'X-Signature': signature,
        'X-Timestamp': timestamp,
        'X-Nonce': nonce,
        'Content-Type': 'application/json',
      },
      body: body || undefined,
    });

    return response.json();
  }

  async sign(method, path, body, timestamp, nonce) {
    const payload = [method.toUpperCase(), path, body, timestamp, nonce].join('\n');
    const encoder = new TextEncoder();

    const key = await crypto.subtle.importKey(
      'raw', encoder.encode(this.clientSecret),
      { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']
    );

    const signature = await crypto.subtle.sign('HMAC', key, encoder.encode(payload));
    return btoa(String.fromCharCode(...new Uint8Array(signature)))
      .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  generateNonce() {
    const array = new Uint8Array(16);
    crypto.getRandomValues(array);
    return Array.from(array, b => b.toString(16).padStart(2, '0')).join('');
  }
}
```

## Usage

```javascript
const client = new HmacClient(
  'https://api.example.com',
  'prod_a1b2c3d4e5f6g7h8',
  'your-secret'
);

const users = await client.request('GET', '/api/users');
const response = await client.request('POST', '/api/users', { name: 'John' });
```
