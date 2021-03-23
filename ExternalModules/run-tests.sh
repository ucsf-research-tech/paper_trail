#!/bin/sh

set -e

composer install -q
npm install > /dev/null

echo
echo Running tests...
php `php bin/get-phpunit-path.php`

echo
echo Checking code standards...
vendor/bin/phpcs -p --standard=tests/phpcs --extensions=php --ignore=/vendor .

echo Ensuring PHP version compatibility...
vendor/bin/phpcs -ps --runtime-set testVersion 5.5- --standard=vendor/phpcompatibility/php-compatibility/PHPCompatibility --extensions=php --ignore=/tests,/vendor .

echo Ensuring JavaScript browser compatibility...
node_modules/.bin/eslint .
echo

echo All tests completed successfully!
echo