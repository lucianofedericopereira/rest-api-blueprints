#!/usr/bin/env bash
# A.9: Generate RSA key pair for JWT signing
# Run once during setup: chmod +x jwt_setup.sh && ./jwt_setup.sh

set -euo pipefail

JWT_DIR="$(dirname "$0")/config/jwt"
mkdir -p "$JWT_DIR"

PASSPHRASE="${JWT_PASSPHRASE:-ChangeMe}"

echo "Generating JWT RSA key pair in $JWT_DIR"

openssl genpkey \
    -algorithm RSA \
    -out "$JWT_DIR/private.pem" \
    -pkeyopt rsa_keygen_bits:4096 \
    -aes256 \
    -pass pass:"$PASSPHRASE"

openssl pkey \
    -in "$JWT_DIR/private.pem" \
    -out "$JWT_DIR/public.pem" \
    -pubout \
    -passin pass:"$PASSPHRASE"

chmod 600 "$JWT_DIR/private.pem"
chmod 644 "$JWT_DIR/public.pem"

echo "JWT keys generated successfully."
echo "  Private key: $JWT_DIR/private.pem"
echo "  Public key:  $JWT_DIR/public.pem"
