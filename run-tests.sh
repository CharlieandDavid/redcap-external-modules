#!/bin/sh

set -e

composer install -q
# npm install > /dev/null

echo
echo Running tests...
vendor/bin/phpunit

echo
echo Checking code standards...
vendor/bin/phpcs -p --standard=tests/phpcs --extensions=php --ignore=/vendor .

echo Ensuring PHP version compatibility...
vendor/bin/phpcs -p --runtime-set testVersion 5.5- --standard=vendor/phpcompatibility/php-compatibility/PHPCompatibility --extensions=php --ignore=/vendor .

echo Ensuring JavaScript browser compatibility...
node_modules/.bin/eslint .
echo

echo All tests completed successfully!
echo