<?php

namespace Devfaysal\Icombd\Tests;

use Devfaysal\Icombd\IcombdServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [IcombdServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set([
            'icombd.username' => 'test-user',
            'icombd.password' => 'test-password',
            'icombd.sms_sender' => 'SMS-SENDER',
            'icombd.voice_sender' => 'VOICE-SENDER',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}
