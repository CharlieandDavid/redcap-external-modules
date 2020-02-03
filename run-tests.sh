#!/bin/sh

set -e

if hash composer 2>/dev/null; then
    composer install -q
fi

echo Running tests...
vendor/bin/phpunit

echo Checking code standards...
vendor/bin/phpcs -p --standard=tests/phpcs --extensions=php --ignore=/vendor .

echo Ensuring PHP version compatibility...
vendor/bin/phpcs -p --runtime-set testVersion 5.5- --standard=vendor/phpcompatibility/php-compatibility/PHPCompatibility --extensions=php --ignore=/vendor .