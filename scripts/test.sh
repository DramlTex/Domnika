#!/bin/sh
set -e

if [ -f vendor/bin/phpunit ]; then
    vendor/bin/phpunit
else
    echo "phpunit is not installed. Skipping tests."
fi

