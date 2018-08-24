#!/bin/bash

cd "$(dirname "${BASH_SOURCE[0]}")/../../"

eval eslint \
    --ignore-pattern 'vendor/' \
    --ignore-pattern gulpfile.js \
    --ignore-pattern 'dt-core/dependencies/' \
    --ignore-pattern 'dt-core/libraries/' \
    --ignore-pattern '*.min.js' \
    --ignore-pattern 'dt-core/admin/multi-role/js/min/' \
    .
