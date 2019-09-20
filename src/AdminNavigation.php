<?php

namespace Modules\Admin\Navigation;

use Illuminate\Support\Facades\Facade;

/**
 * @method  static array getNavigation()
 * @method  static void storeFavorites(array $favorites)
 */
class AdminNavigation extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'admin_navigation';
    }
}
