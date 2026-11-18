<?php

namespace Devfaysal\Icombd\Tests;

use Devfaysal\Icombd\Icombd;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('validRecipients')]
    public function test_normalizes_and_deduplicates_recipients(string $method, string|array $numbers, string $expected): void
    {
        Http::fake(['*' => Http::response(['accepted' => true])]);

        $this->app->make(Icombd::class)->{$method}($numbers, 'content');

        Http::assertSent(fn (Request $request) => $request['to'] === $expected);
        Http::assertSentCount(1);
    }

    public static function validRecipients(): iterable
    {
        $cases = [
            'local' => ['01712345678', '8801712345678'],
            'international' => ['8801712345678', '8801712345678'],
            'plus' => ['+8801712345678', '8801712345678'],
            'international access code' => ['008801712345678', '8801712345678'],
            'formatted' => [' +880 1712-345678 ', '8801712345678'],
            'comma separated' => ['01712345678, +8801812345678', '8801712345678,8801812345678'],
            'array' => [['01712345678', '01912345678'], '8801712345678,8801912345678'],
            'duplicates' => [['01712345678', '+8801712345678', '8801712345678'], '8801712345678'],
            'comma duplicates' => ['01712345678,8801712345678', '8801712345678'],
            'associative array' => [['first' => '01712345678'], '8801712345678'],
        ];

        foreach (range(3, 9) as $prefix) {
            $cases['prefix 01'.$prefix] = ['01'.$prefix.'12345678', '8801'.$prefix.'12345678'];
        }

        foreach (['send', 'sendVoice'] as $method) {
            foreach ($cases as $name => [$numbers, $expected]) {
                yield $method.' '.$name => [$method, $numbers, $expected];
            }
        }
    }

    #[DataProvider('invalidRecipients')]
    public function test_invalid_recipients_abort_the_whole_request(string $method, string|array $numbers): void
    {
        try {
            $this->app->make(Icombd::class)->{$method}($numbers, 'content');
            $this->fail('Expected invalid recipients to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public static function invalidRecipients(): iterable
    {
        $cases = [
            'empty string' => '',
            'whitespace' => " \t\n",
            'empty array' => [],
            'empty item' => [''],
            'trailing comma' => '01712345678,',
            'leading comma' => ',01712345678',
            'empty recipient' => '01712345678,,01812345678',
            'foreign' => '+919876543210',
            'landline' => '029123456',
            'short' => '0171234567',
            'long' => '017123456789',
            'invalid prefix' => '01212345678',
            'letters' => '0171234567a',
            'mixed valid and invalid' => ['01712345678', '+14155552671'],
            'integer array item' => [8801712345678],
            'null array item' => [null],
            'nested array' => [['01712345678']],
            'comma inside array item' => ['01712345678,01812345678'],
        ];

        foreach (['send', 'sendVoice'] as $method) {
            foreach ($cases as $name => $numbers) {
                yield $method.' '.$name => [$method, $numbers];
            }
        }
    }
}
