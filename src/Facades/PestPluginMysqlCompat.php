<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JeffersonGoncalves\PestPluginMysqlCompat\PestPluginMysqlCompat
 */
class PestPluginMysqlCompat extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'pest-plugin-mysql-compat';
    }
}
