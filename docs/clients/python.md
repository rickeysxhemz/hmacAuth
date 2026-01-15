# Python Client

```python
import hmac
import hashlib
import time
import secrets
import base64
import json
import requests

class HmacClient:
    def __init__(self, base_url: str, client_id: str, client_secret: str, algorithm: str = "sha256"):
        self.base_url = base_url.rstrip("/")
        self.client_id = client_id
        self.client_secret = client_secret
        self.algorithm = algorithm

    def request(self, method: str, path: str, data: dict = None) -> dict:
        timestamp = str(int(time.time()))
        nonce = secrets.token_hex(16)
        body = json.dumps(data, separators=(",", ":")) if data else ""

        signature = self._sign(method, path, body, timestamp, nonce)

        response = requests.request(
            method=method,
            url=f"{self.base_url}{path}",
            headers={
                "X-Api-Key": self.client_id,
                "X-Signature": signature,
                "X-Timestamp": timestamp,
                "X-Nonce": nonce,
                "Content-Type": "application/json",
            },
            data=body if body else None,
        )

        return response.json()

    def _sign(self, method: str, path: str, body: str, timestamp: str, nonce: str) -> str:
        payload = "\n".join([method.upper(), path, body, timestamp, nonce])
        hash_func = getattr(hashlib, self.algorithm)
        signature = hmac.new(
            self.client_secret.encode(),
            payload.encode(),
            hash_func
        ).digest()
        return base64.urlsafe_b64encode(signature).rstrip(b"=").decode()
```

## Usage

```python
client = HmacClient(
    "https://api.example.com",
    "prod_a1b2c3d4e5f6g7h8",
    "your-secret"
)

users = client.request("GET", "/api/users")
response = client.request("POST", "/api/users", {"name": "John"})
```
