<?php

namespace Devfaysal\Icombd\Tests;

use Devfaysal\Icombd\Facades\Icombd as IcombdFacade;
use Devfaysal\Icombd\Icombd;
use Devfaysal\Icombd\IcombdServiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class ServiceProviderTest extends TestCase
{
    public function test_default_configuration_is_merged_without_overwriting_credentials(): void
    {
        $this->assertSame('test-user', config('icombd.username'));
        $this->assertSame('https://api.icombd.com', config('icombd.base_url'));
        $this->assertSame(30, config('icombd.timeout'));
        $this->assertSame(10, config('icombd.connect_timeout'));
    }

    public function test_container_bindings_and_facade_resolve_the_client(): void
    {
        $this->assertInstanceOf(Icombd::class, $this->app->make('icombd'));
        $this->assertInstanceOf(Icombd::class, $this->app->make(Icombd::class));
        Http::fake(['*' => Http::response('accepted')]);

        $this->assertSame(200, IcombdFacade::send('01712345678', 'Hello'));
        Http::assertSentCount(1);
    }

    public function test_configuration_can_be_published_under_both_tags(): void
    {
        foreach (['config', 'icombd-config'] as $tag) {
            $this->assertSame(
                [dirname(__DIR__).'/src/../config/config.php' => config_path('icombd.php')],
                ServiceProvider::pathsToPublish(IcombdServiceProvider::class, $tag)
            );
        }
    }

    public function test_provider_registration_does_not_require_credentials_until_the_client_is_used(): void
    {
        config(['icombd.username' => '', 'icombd.password' => '']);

        (new IcombdServiceProvider($this->app))->register();

        $this->assertTrue($this->app->bound('icombd'));
        $this->expectException(InvalidArgumentException::class);
        $this->app->make('icombd');
    }

    #[DataProvider('invalidCredentials')]
    public function test_missing_or_invalid_credentials_fail_early(string $key, mixed $value): void
    {
        config(['icombd.'.$key => $value]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Icombd '.$key.' is not set.');
        $this->app->make(Icombd::class);
    }

    public static function invalidCredentials(): iterable
    {
        foreach (['username', 'password'] as $key) {
            foreach ([null, '', '   ', [], false] as $index => $value) {
                yield $key.' '.$index => [$key, $value];
            }
        }
    }

    #[DataProvider('invalidBaseUrls')]
    public function test_invalid_or_insecure_base_urls_are_rejected(mixed $url): void
    {
        config(['icombd.base_url' => $url]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('base_url');
        $this->app->make(Icombd::class);
    }

    public static function invalidBaseUrls(): iterable
    {
        foreach ([null, '', 'not-a-url', 'http://api.icombd.com', 'https://user:pass@example.test',
            'https://user@example.test', 'https://example.test?key=value', 'https://example.test#fragment'] as $url) {
            yield [$url];
        }
    }
}
