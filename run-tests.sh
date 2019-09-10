#!/bin/sh

set -e

composer install

vendor/bin/phpunit
vendor/bin/phpcs -p --runtime-set testVersion 5.5- --standard=vendor/phpcompatibility/php-compatibility/PHPCompatibility --extensions=php --ignore=/vendor .