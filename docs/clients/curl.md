# cURL / Bash

## Script

```bash
#!/bin/bash

BASE_URL="https://api.example.com"
CLIENT_ID="prod_a1b2c3d4e5f6g7h8"
CLIENT_SECRET="your-secret"

hmac_request() {
    local method="$1"
    local path="$2"
    local body="${3:-}"

    local timestamp=$(date +%s)
    local nonce=$(openssl rand -hex 16)

    local payload="${method}
${path}
${body}
${timestamp}
${nonce}"

    local signature=$(echo -n "$payload" | \
        openssl dgst -sha256 -hmac "$CLIENT_SECRET" -binary | \
        base64 | tr '+/' '-_' | tr -d '=')

    curl -X "$method" \
        -H "X-Api-Key: $CLIENT_ID" \
        -H "X-Signature: $signature" \
        -H "X-Timestamp: $timestamp" \
        -H "X-Nonce: $nonce" \
        -H "Content-Type: application/json" \
        ${body:+-d "$body"} \
        "${BASE_URL}${path}"
}

# GET
hmac_request "GET" "/api/users"

# POST
hmac_request "POST" "/api/users" '{"name":"John"}'
```

## One-liner

```bash
METHOD="GET"; PATH="/api/users"; BODY=""; \
TIMESTAMP=$(date +%s); NONCE=$(openssl rand -hex 16); \
CLIENT_ID="prod_a1b2c3d4e5f6g7h8"; CLIENT_SECRET="your-secret"; \
SIGNATURE=$(printf "%s\n%s\n%s\n%s\n%s" "$METHOD" "$PATH" "$BODY" "$TIMESTAMP" "$NONCE" | \
  openssl dgst -sha256 -hmac "$CLIENT_SECRET" -binary | base64 | tr '+/' '-_' | tr -d '='); \
curl -X "$METHOD" \
  -H "X-Api-Key: $CLIENT_ID" \
  -H "X-Signature: $SIGNATURE" \
  -H "X-Timestamp: $TIMESTAMP" \
  -H "X-Nonce: $NONCE" \
  "https://api.example.com$PATH"
```
