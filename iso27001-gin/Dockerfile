# Multi-stage build — keeps final image small and free of build toolchain.
# A.10: non-root user; A.14: minimal attack surface.

# ── Build stage ──────────────────────────────────────────────────────────────
FROM golang:1.22-alpine AS builder

WORKDIR /build

# Cache module downloads separately from source changes
COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /bin/server .

# ── Runtime stage ─────────────────────────────────────────────────────────────
FROM gcr.io/distroless/static-debian12:nonroot

COPY --from=builder /bin/server /server

EXPOSE 8003

ENTRYPOINT ["/server"]
