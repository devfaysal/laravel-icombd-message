<?php

namespace Devfaysal\Icombd\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Devfaysal\Icombd\Icombd
 */
class Icombd extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'icombd';
    }
}
