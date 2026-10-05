#!/bin/sh
# Builds the API reference of all major versions with Doctum into website/static/api/<version>.
# Runs in the docs-api container with the repository mounted at /repo.
set -eu

export DOCTUM_SOURCE_DIR=/tmp/doctum-source

rm -rf "${DOCTUM_SOURCE_DIR}" /repo/website/static/api
git clone --quiet --no-checkout /repo "${DOCTUM_SOURCE_DIR}"

# Doctum checks out each version by its name, so the names of the major versions become branches of the clone
git -C "${DOCTUM_SOURCE_DIR}" branch 1.x v1.4.2
git -C "${DOCTUM_SOURCE_DIR}" branch 2.x v2.7.2
git -C "${DOCTUM_SOURCE_DIR}" branch 3.x v3.1.8
git -C "${DOCTUM_SOURCE_DIR}" branch 4.x "$(git -C /repo rev-parse HEAD)"
git -C "${DOCTUM_SOURCE_DIR}" checkout --quiet 4.x

# 1.x and 2.x use curly brace string offsets in internal classes, which the PHP 8 parser rejects
doctum update --no-interaction --no-progress --force --ignore-parse-errors /repo/website/doctum.php
