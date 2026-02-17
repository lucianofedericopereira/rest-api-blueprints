# ── Build stage ──────────────────────────────────────────────────────────────
FROM elixir:1.16-alpine AS builder

RUN apk add --no-cache build-base git

WORKDIR /app

ENV MIX_ENV=prod

COPY mix.exs mix.lock ./
RUN mix local.hex --force && mix local.rebar --force
RUN mix deps.get --only prod
RUN mix deps.compile

COPY config config
COPY lib lib
COPY priv priv

RUN mix compile
RUN mix release

# ── Runtime stage ─────────────────────────────────────────────────────────────
FROM alpine:3.19 AS runtime

# A.10: Minimal attack surface — no shell tools in final image
RUN apk add --no-cache libstdc++ openssl ncurses-libs

RUN addgroup -S app && adduser -S app -G app
WORKDIR /app
USER app

COPY --from=builder --chown=app:app /app/_build/prod/rel/iso27001_phoenix ./

EXPOSE 8004

CMD ["bin/iso27001_phoenix", "start"]
