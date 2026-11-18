<?php

namespace Devfaysal\Icombd;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class Icombd
{
    protected string $baseUrl;

    protected string $username;

    protected string $password;

    public function __construct()
    {
        $this->username = $this->requiredConfig('username');
        $this->password = $this->requiredConfig('password');
        $this->baseUrl = rtrim($this->requiredConfig('base_url'), '/');

        $parts = parse_url($this->baseUrl);

        if (! filter_var($this->baseUrl, FILTER_VALIDATE_URL)
            || ($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Icombd base_url must be an HTTPS URL without credentials, a query or a fragment.');
        }
    }

    /**
     * @param  string|array<array-key, string>  $numbers
     */
    public function send(string|array $numbers, string $message): int
    {
        return $this->sendResponse($numbers, $message)->status();
    }

    /**
     * @param  string|array<array-key, string>  $numbers
     */
    public function sendResponse(string|array $numbers, string $message): Response
    {
        $this->requireContent($message, 'message');

        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'sender' => $this->sender('sms'),
            'text' => $message,
            'to' => $this->normalizeNumbers($numbers),
        ];

        // The v1 API uses URL-encoded parameters; v2 has a different JSON contract.
        return $this->checkResponse(
            $this->request()->asForm()->post($this->baseUrl.'/api/v1/campaigns/sendsms/plain', $data)
        );
    }

    /**
     * @param  string|array<array-key, string>  $numbers
     */
    public function sendVoice(string|array $numbers, string $audioFileName): mixed
    {
        $this->requireContent($audioFileName, 'audio filename');

        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'sender' => $this->sender('voice'),
            'to' => $this->normalizeNumbers($numbers),
            'audio' => $audioFileName,
        ];

        // Preserve the legacy voice contract until ICOMBD documents a replacement.
        $response = $this->checkResponse(
            $this->request()->get($this->baseUrl.'/api/v1/campaigns/sendsms/plain', $data)
        );

        return json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function accountBalance(): mixed
    {
        $data = [
            'username' => $this->username,
            'password' => $this->password,
        ];
        $response = $this->checkResponse($this->request()->get($this->baseUrl.'/balance', $data));

        return json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function request(): PendingRequest
    {
        // A retry can send the same paid message twice. Let the caller decide.
        return Http::connectTimeout($this->timeout('connect_timeout'))
            ->timeout($this->timeout('timeout'))
            ->withoutRedirecting();
    }

    protected function checkResponse(Response $response): Response
    {
        // ICOMBD documents 203 as a gateway error, despite it being HTTP 2xx.
        if (! $response->successful() || $response->status() === 203) {
            throw new RequestException($response);
        }

        return $response;
    }

    protected function requiredConfig(string $key): string
    {
        $value = config('icombd.'.$key);

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Icombd '.$key.' is not set.');
        }

        return $value;
    }

    protected function sender(string $type): string
    {
        $sender = config('icombd.'.$type.'_sender');

        if ((! is_string($sender) && ! is_int($sender)) || trim((string) $sender) === '') {
            throw new InvalidArgumentException('Icombd '.$type.'_sender is not set.');
        }

        return (string) $sender;
    }

    protected function timeout(string $key): float
    {
        $value = config('icombd.'.$key);

        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0) {
            throw new InvalidArgumentException('Icombd '.$key.' must be a positive number.');
        }

        return (float) $value;
    }

    protected function requireContent(string $value, string $name): void
    {
        if (preg_match('/\A\s*\z/u', $value) === 1 || trim($value) === '') {
            throw new InvalidArgumentException('The '.$name.' must not be empty.');
        }
    }

    /**
     * Validate array values at runtime before normalizing recipients.
     *
     * @param  string|array<array-key, mixed>  $numbers
     */
    protected function normalizeNumbers(string|array $numbers): string
    {
        $numbers = is_array($numbers) ? $numbers : explode(',', $numbers);

        if ($numbers === []) {
            throw new InvalidArgumentException('At least one Bangladesh mobile number is required.');
        }

        $normalized = [];

        foreach ($numbers as $number) {
            if (! is_string($number)) {
                throw new InvalidArgumentException('Phone numbers must be strings.');
            }

            $number = preg_replace('/[\s-]+/', '', trim($number));

            if ($number === null || ! preg_match('/\A(?:\+?88|0088)?(01[3-9][0-9]{8})\z/', $number, $matches)) {
                throw new InvalidArgumentException('Invalid Bangladesh mobile number. Use 01XXXXXXXXX or +8801XXXXXXXXX.');
            }

            $normalized[] = '88'.$matches[1];
        }

        // Different input formats can refer to the same recipient.
        return implode(',', array_unique($normalized));
    }
}
