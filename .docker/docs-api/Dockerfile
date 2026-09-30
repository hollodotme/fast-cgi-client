FROM php:8.4-cli-alpine

ARG DOCTUM_VERSION=5.6.0

RUN apk add --no-cache git \
 && curl -fsSL -o /usr/local/bin/doctum "https://github.com/code-lts/doctum/releases/download/v${DOCTUM_VERSION}/doctum.phar" \
 && chmod +x /usr/local/bin/doctum \
 && git config --system --add safe.directory '*'

WORKDIR /repo
