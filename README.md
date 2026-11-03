# php-plextv-api
This is a PHP library framework for working with the [Plex.tv](https://plex.tv/)
API service.

## Requirements

- PHP 8.3 or newer (64-bit)
- The `curl` and `xmlreader` PHP extensions

## Install

Run: `composer require carlbennett/php-plextv-api`

## Usage

Set a client identifier before calling any method that talks to Plex.tv.
Generate it once and store it, then reuse it for every later request:

```php
use CarlBennett\PlexTvAPI\Auth;
use CarlBennett\PlexTvAPI\User;

Auth::$client_id = $stored_client_id ?? Auth::generateClientId();
Auth::$app_name = 'My App';

// Step 1: request a PIN and send the user to Plex.tv to sign in.
[$pin_id, $pin_code] = Auth::generatePINCode();
$url = Auth::getInteractiveAuthUrl(Auth::$client_id, $pin_code, Auth::getForwardUrl(), Auth::$app_name);

// Step 2: once the user returns, exchange the PIN for an access token.
$token = Auth::verifyPINCode($pin_id, $pin_code);

// Later: check the token is still valid, and list shared users.
if ($token !== null && Auth::verifyUserAccessToken($token)) {
    $users = User::getUsers($token);
}
```

`Auth::getForwardUrl()` builds the return URL from the current request. In
production, set `Auth::$forward_url_base` (e.g. `https://example.com`) so that it does
not trust the client-supplied `Host` header.

## Development

```sh
composer install
lib/bin/phpunit --testdox
lib/bin/phpstan analyse
```

CI runs the linter and tests on every supported PHP version (8.3 to 8.5), and
PHPStan at level 8, which checks the code against PHP 8.3. The linter fails on
deprecations as well as syntax errors. The linter and tests also run on nightly
PHP 8.6 and 8.7 builds as an early warning; those jobs may fail without failing
the run.
