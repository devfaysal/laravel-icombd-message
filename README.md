# Laravel ICOMBD Message

A Laravel wrapper for sending SMS to Bangladesh mobile numbers through ICOMBD.

Requires **Laravel 13** and **PHP 8.3 or newer**. Older Laravel versions are no longer supported. Laravel 13's PHP requirements are listed in its [release notes](https://laravel.com/docs/13.x/releases).

## Installation

```bash
composer require devfaysal/laravel-icombd-message
```

The service provider and `Icombd` alias are discovered automatically. Optionally publish the configuration:

```bash
php artisan vendor:publish --tag=icombd-config
```

Add your ICOMBD credentials and sender ID to `.env`:

```dotenv
ICOMBD_USERNAME=your-username
ICOMBD_PASSWORD=your-password
ICOMBD_SMS_SENDER=your-sender-id
ICOMBD_VOICE_SENDER=your-voice-sender-id
```

The voice sender is needed only for `sendVoice()`. Neither sender is required for `accountBalance()`.

Optional settings:

```dotenv
ICOMBD_BASE_URL=https://api.icombd.com
ICOMBD_TIMEOUT=30
ICOMBD_CONNECT_TIMEOUT=10
```

The base URL must use HTTPS and must not include credentials, a query string or a fragment. It is the gateway root, without `/api/v1`. Timeouts are positive numbers in seconds. After changing configuration in an application using cached configuration, rebuild it with `php artisan config:cache`.

## Sending SMS

```php
use Devfaysal\Icombd\Facades\Icombd;

$status = Icombd::send('01712345678', 'Hello from Laravel!');

$status = Icombd::send(
    ['01712345678', '+8801812345678'],
    'বাংলায় একটি বার্তা & special characters + are supported.'
);
```

Pass numbers as strings, either individually, in a comma-separated string, or as an array of individual strings. Local `01XXXXXXXXX`, international `8801XXXXXXXXX`, `+8801XXXXXXXXX`, and `008801XXXXXXXXX` forms are accepted. Spaces and hyphens are removed. Supported mobile prefixes are `013` through `019`.

Numbers are normalized to `8801XXXXXXXXX`, and duplicates in the same call are removed. An empty list or any invalid recipient rejects the entire call before a request is sent. Validation checks number format, not ownership, whether the number is active, or delivery eligibility. Messages are sent unchanged; do not URL-encode them yourself.

`send()` returns the HTTP status code for compatibility. To inspect the gateway's body or response headers, use `sendResponse()`:

```php
$response = Icombd::sendResponse('01712345678', 'Hello!');

$status = $response->status();
$body = $response->body();
```

An HTTP success does not prove delivery. Inspect the gateway response according to your account's API contract. The public guide does not define a consistent response body schema, so this package does not guess how to interpret application error codes inside HTTP 200 responses.

You can also inject `Devfaysal\Icombd\Icombd` into a constructor or resolve it using `app('icombd')`.

## Balance and voice

```php
$balance = Icombd::accountBalance();
$result = Icombd::sendVoice('01712345678', 'uploaded-audio.wav');
```

These methods return the decoded JSON response. The audio argument is a filename already available to ICOMBD; this package does not upload audio.

**Voice API limitation:** the existing GET request to `/api/v1/campaigns/sendsms/plain` with an `audio` parameter is preserved. The public [ICOMBD API guide](https://icombd.com/API_Documentation.pdf) documents SMS and balance, but no voice contract. Confirm the voice endpoint and response format with ICOMBD before relying on it. Tests verify the package's existing voice request; they do not verify live voice delivery.

SMS uses a form-encoded POST to the v1 endpoint. Balance uses GET `/balance`. The legacy voice and balance requests include credentials in query parameters, so avoid logging their full URLs.

## Errors and upgrading

- Only Laravel 13 is supported. Development uses Testbench 11 and PHPUnit 12.
- Requests use HTTPS, with a default 30-second total timeout and 10-second connection timeout. Redirects are rejected, and requests are not retried automatically, avoiding accidental duplicate paid sends.
- Missing credentials or sender IDs, invalid configuration, invalid recipients, and blank content throw `InvalidArgumentException` before any request.
- Non-2xx responses throw `Illuminate\Http\Client\RequestException`. HTTP 203 is also rejected because ICOMBD lists 203 as an error code. The exception's `response` property retains the status and body.
- Network failures throw `Illuminate\Http\Client\ConnectionException`. A timeout may occur after the gateway accepted a message; check delivery before retrying.
- Invalid JSON from voice or balance throws `JsonException` instead of silently returning `null`.
- Use `sms_sender` / `voice_sender` and `ICOMBD_SMS_SENDER` / `ICOMBD_VOICE_SENDER`. Rename any existing `sms_sendar` / `voice_sendar` keys and `*_SENDAR` environment variables; the old names are no longer supported.

Existing code that expects an error status from `send()` should now catch `RequestException`. Successful return types remain unchanged: an integer from `send()` and decoded JSON from voice/balance.

## Tests

```bash
composer install
composer test
```

Tests use Laravel's HTTP fakes and prevent stray network requests. They require no ICOMBD account and send no SMS. Coverage includes request encoding, Bangladesh number validation and normalization, duplicate recipients, configuration, provider/facade integration, gateway errors, connection failures, and malformed responses.

With Xdebug or PCOV installed:

```bash
composer test-coverage
```

## Code quality

[Laravel Pint](https://laravel.com/docs/13.x/pint) applies the Laravel coding style to the package and tests. [PHPStan](https://phpstan.org/user-guide/getting-started) checks `src` and `config` at level 8, targeting PHP 8.3.

```bash
composer format        # Fix code style
composer format:check  # Check code style without changing files
composer analyse       # Run static analysis
```

CI runs both checks alongside the test suite on PHP 8.3, 8.4, and 8.5.

## License

MIT. See [LICENSE.md](LICENSE.md).
