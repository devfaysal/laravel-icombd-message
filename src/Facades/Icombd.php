<?php

namespace Devfaysal\Icombd\Facades;

use Illuminate\Support\Facades\Facade;


class Icombd extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'icombd';
    }
}