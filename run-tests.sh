#!/bin/sh

set -e

phpunitPath='vendor/bin/phpunit'

if [ ! -f $phpunitPath ]; then
    composer install
fi

$phpunitPath

vendor/bin/phpcs -p --runtime-set testVersion 5.5- --standard=vendor/phpcompatibility/php-compatibility/PHPCompatibility --extensions=php --ignore=/vendor .