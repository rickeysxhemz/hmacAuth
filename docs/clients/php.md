# PHP Client

```php
<?php

class HmacClient
{
    public function __construct(
        private string $baseUrl,
        private string $clientId,
        private string $clientSecret,
        private string $algorithm = 'sha256'
    ) {}

    public function request(string $method, string $path, array $data = []): array
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $body = $data ? json_encode($data) : '';

        $signature = $this->sign($method, $path, $body, $timestamp, $nonce);

        $response = (new \GuzzleHttp\Client)->request($method, $this->baseUrl . $path, [
            'headers' => [
                'X-Api-Key' => $this->clientId,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
                'Content-Type' => 'application/json',
            ],
            'body' => $body ?: null,
        ]);

        return json_decode($response->getBody(), true);
    }

    private function sign(string $method, string $path, string $body, string $timestamp, string $nonce): string
    {
        $payload = implode("\n", [strtoupper($method), $path, $body, $timestamp, $nonce]);
        $hmac = hash_hmac($this->algorithm, $payload, $this->clientSecret, true);
        return rtrim(strtr(base64_encode($hmac), '+/', '-_'), '=');
    }
}
```

## Usage

```php
$client = new HmacClient(
    'https://api.example.com',
    'prod_a1b2c3d4e5f6g7h8',
    'your-secret'
);

$users = $client->request('GET', '/api/users');
$response = $client->request('POST', '/api/users', ['name' => 'John']);
```
