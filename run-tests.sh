#!/bin/sh

set -e

if hash composer; then
    composer install
fi

echo Running tests...
vendor/bin/phpunit

echo Ensuring PHP version compatibility...
vendor/bin/phpcs -p --runtime-set testVersion 5.5- --standard=vendor/phpcompatibility/php-compatibility/PHPCompatibility --extensions=php --ignore=/vendor .