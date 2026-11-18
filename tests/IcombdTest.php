<?php

namespace Devfaysal\Icombd\Tests;

use Devfaysal\Icombd\Icombd;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;

class IcombdTest extends TestCase
{
    public function test_sms_uses_the_v1_form_contract_and_preserves_message_content(): void
    {
        $message = 'বাংলায় SMS & price = ৳50 + VAT? @test #1';
        Http::fake(['https://api.icombd.com/api/v1/campaigns/sendsms/plain' => Http::response('accepted', 200)]);

        $status = $this->app->make(Icombd::class)->send('01712345678', $message);

        $this->assertSame(200, $status);
        Http::assertSent(function (Request $request) use ($message) {
            parse_str($request->body(), $body);

            return $request->method() === 'POST'
                && $request->url() === 'https://api.icombd.com/api/v1/campaigns/sendsms/plain'
                && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
                && $body === [
                    'username' => 'test-user',
                    'password' => 'test-password',
                    'sender' => 'SMS-SENDER',
                    'text' => $message,
                    'to' => '8801712345678',
                ];
        });
        Http::assertSentCount(1);
    }

    public function test_callers_can_inspect_the_sms_response_body_and_headers(): void
    {
        Http::fake(['*' => Http::response('gateway-result', 202, ['X-Request-ID' => 'test-id'])]);

        $response = $this->app->make(Icombd::class)->sendResponse('01712345678', 'Hello');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(202, $response->status());
        $this->assertSame('gateway-result', $response->body());
        $this->assertSame('test-id', $response->header('X-Request-ID'));
    }

    public function test_voice_preserves_the_legacy_get_contract_and_returns_json(): void
    {
        Http::fake(['https://api.icombd.com/api/v1/campaigns/sendsms/plain?*' => Http::response(['accepted' => true])]);

        $result = $this->app->make(Icombd::class)->sendVoice(['01712345678', '+8801812345678'], 'greeting & intro.wav');

        $this->assertSame(['accepted' => true], $result);
        Http::assertSent(function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && $query === [
                    'username' => 'test-user',
                    'password' => 'test-password',
                    'sender' => 'VOICE-SENDER',
                    'to' => '8801712345678,8801812345678',
                    'audio' => 'greeting & intro.wav',
                ];
        });
        Http::assertSentCount(1);
    }

    public function test_balance_does_not_require_a_sender_and_uses_the_configured_host(): void
    {
        config([
            'icombd.sms_sender' => '',
            'icombd.voice_sender' => '',
            'icombd.base_url' => 'https://gateway.example.test/',
        ]);
        Http::fake(['https://gateway.example.test/balance?*' => Http::response(['balance' => 100.5])]);

        $this->assertSame(['balance' => 100.5], $this->app->make(Icombd::class)->accountBalance());
        Http::assertSent(function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && $query === ['username' => 'test-user', 'password' => 'test-password'];
        });
        Http::assertSentCount(1);
    }

    #[DataProvider('sendMethods')]
    public function test_sending_uses_the_configured_host(string $method): void
    {
        config(['icombd.base_url' => 'https://gateway.example.test/']);
        Http::fake(['https://gateway.example.test/api/v1/campaigns/sendsms/plain*' => Http::response(['accepted' => true])]);

        $this->app->make(Icombd::class)->{$method}('01712345678', 'content');

        Http::assertSentCount(1);
    }

    #[DataProvider('failedResponses')]
    public function test_gateway_failures_throw_and_retain_the_response(string $method, int $status): void
    {
        Http::fake(['*' => Http::response('gateway failure', $status)]);

        try {
            $this->callMethod($method);
            $this->fail('Expected a request exception.');
        } catch (RequestException $exception) {
            $this->assertSame($status, $exception->response->status());
            $this->assertSame('gateway failure', $exception->response->body());
        }

        Http::assertSentCount(1);
    }

    public static function failedResponses(): iterable
    {
        foreach (['send', 'sendResponse', 'sendVoice', 'accountBalance'] as $method) {
            foreach ([203, 302, 401, 422, 429, 500] as $status) {
                yield $method.' '.$status => [$method, $status];
            }
        }
    }

    #[DataProvider('allMethods')]
    public function test_connection_failures_propagate_without_retrying(string $method): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Connection timed out.');
        });

        try {
            $this->callMethod($method);
            $this->fail('Expected a connection exception.');
        } catch (ConnectionException $exception) {
            $this->assertSame('Connection timed out.', $exception->getMessage());
        }

        $this->assertSame(1, $attempts);
    }

    public static function allMethods(): iterable
    {
        foreach (['send', 'sendResponse', 'sendVoice', 'accountBalance'] as $method) {
            yield $method => [$method];
        }
    }

    #[DataProvider('jsonMethods')]
    public function test_invalid_json_is_not_silently_returned_as_null(string $method): void
    {
        Http::fake(['*' => Http::response('<html>Unavailable</html>')]);

        $this->expectException(JsonException::class);
        $this->callMethod($method);
    }

    public static function jsonMethods(): iterable
    {
        yield 'voice' => ['sendVoice'];
        yield 'balance' => ['accountBalance'];
    }

    #[DataProvider('emptyContent')]
    public function test_empty_content_is_rejected_before_a_request(string $method, string $content): void
    {
        try {
            $this->app->make(Icombd::class)->{$method}('01712345678', $content);
            $this->fail('Expected invalid content to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('must not be empty', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public static function emptyContent(): iterable
    {
        foreach (['send', 'sendVoice'] as $method) {
            foreach (['', " \t\n", "\u{00a0}"] as $index => $content) {
                yield $method.' '.$index => [$method, $content];
            }
        }
    }

    #[DataProvider('sendMethods')]
    public function test_the_sender_for_the_selected_channel_is_required(string $method): void
    {
        $channel = $method === 'sendVoice' ? 'voice' : 'sms';
        config(['icombd.'.$channel.'_sender' => '   ']);

        try {
            $this->app->make(Icombd::class)->{$method}('01712345678', 'content');
            $this->fail('Expected a missing sender to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString($channel.'_sender', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public static function sendMethods(): iterable
    {
        yield 'sms' => ['send'];
        yield 'voice' => ['sendVoice'];
    }

    #[DataProvider('allMethods')]
    public function test_timeouts_and_disabled_redirects_apply_to_every_request(string $method): void
    {
        config(['icombd.timeout' => '12.5', 'icombd.connect_timeout' => 3]);
        $options = null;
        Http::fake(function (Request $request, array $requestOptions) use (&$options) {
            $options = $requestOptions;

            return Http::response(['accepted' => true]);
        });

        $this->callMethod($method);

        $this->assertSame(12.5, $options['timeout']);
        $this->assertSame(3.0, $options['connect_timeout']);
        $this->assertFalse($options['allow_redirects']);
    }

    #[DataProvider('invalidTimeouts')]
    public function test_invalid_timeouts_are_rejected(string $key, mixed $value): void
    {
        config(['icombd.'.$key => $value]);

        try {
            $this->app->make(Icombd::class)->send('01712345678', 'Hello');
            $this->fail('Expected invalid configuration to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString($key, $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public static function invalidTimeouts(): iterable
    {
        foreach (['timeout', 'connect_timeout'] as $key) {
            foreach ([0, -1, null, 'invalid', INF, true] as $index => $value) {
                yield $key.' '.$index => [$key, $value];
            }
        }
    }

    private function callMethod(string $method): mixed
    {
        $client = $this->app->make(Icombd::class);

        return $method === 'accountBalance'
            ? $client->accountBalance()
            : $client->{$method}('01712345678', 'content');
    }
}
