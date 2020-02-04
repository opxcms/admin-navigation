<?php

namespace Modules\Admin\Navigation;

use Core\Foundation\Application;
use Core\Foundation\Module\BaseModule;
use Core\Foundation\UserSettings\UserSettingsRepository;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Admin\Authorization\AdminAuthorization;

class Navigation extends BaseModule
{
    /** @var string  Module name */
    protected $name = 'admin_navigation';

    /** @var string  Module path */
    protected $path = __DIR__;

    /** @var  array  Array of item keys that needs to be transformed. */
    protected $keysToTransform = [
        'parent',
        'section',
    ];

    /**
     * Get all navigation tree.
     *
     * @return  array
     *
     * @throws  Exception
     */
    public function getNavigation(): array
    {

        $systemList = $this->getSystemNavigationList();
        $moduleList = $this->getModuleNavigationList();
        $favorites = $this->getSystemFavorites();

        return array_merge_recursive($favorites, $moduleList, $systemList);
    }

    /**
     * Set navigation favorites.
     *
     * @param array $favorites
     *
     * @return  void
     *
     * @throws  Exception
     */
    public function storeFavorites(array $favorites): void
    {
        $repository = app()->make(UserSettingsRepository::class);

        $user = $this->getUser();

        if ($user) {
            $repository->setSettings($user->getAuthIdentifier(), $favorites, 'favorites');
        }
    }

    /**
     * Get favorites section.
     *
     * @return  array
     *
     * @throws  Exception
     */
    protected function getSystemFavorites(): array
    {
        $repository = app()->make(UserSettingsRepository::class);

        $favorites = $this->transformKeys(require 'favorites.php', 'system');

        $user = $this->getUser();

        $favorites['favorites'] = $user ? $repository->getSettings($user->getAuthIdentifier(), 'favorites') : null;

        return $favorites;
    }

    /**
     * Get navigation list from system.
     *
     * @return  array
     *
     * @throws  Exception
     */
    protected function getSystemNavigationList(): array
    {
        return $this->transformKeys(require 'default.php', 'system');
    }

    /**
     * Get navigation list from modules.
     *
     * @return  array
     */
    protected function getModuleNavigationList(): array
    {
        $navigation = [[]];

        /** @var  Application $app */
        $app = app();

        foreach (array_keys($app->getModulesList()) as $name) {
            $module = $app->getModule($name);

            if ($module) {
                $menu = $module->getManageNavigationMenu();
                $navigation[] = $this->transformKeys($menu, $name);
            }
        }

        return $this->cleanUpEmptySections(array_merge_recursive(...$navigation));
    }

    /**
     * Transform keys in navigation array
     *
     * @param array $navigation
     * @param string $prefix
     *
     * @return  array
     */
    protected function transformKeys($navigation, $prefix): array
    {
        // Check is home properly set and transform it
        $home = null;

        if (isset($navigation['home'])) {
            if (strpos($navigation['home'], '/') !== false) {
                $home = $navigation['home'];
            } else {
                $home = "{$prefix}/{$navigation['home']}";
            }
        }

        $sections = [];
        if (isset($navigation['sections']) && is_array($navigation['sections'])) {
            foreach ($navigation['sections'] as $sectionName => $sectionContent) {
                if (!isset($sectionContent['permission']) || AdminAuthorization::can($sectionContent['permission'])) {
                    $sections["{$prefix}/{$sectionName}"] = $sectionContent;
                }
            }
        }

        $items = [];
        if (isset($navigation['items']) && is_array($navigation['items'])) {
            foreach ($navigation['items'] as $itemName => $itemContent) {
                if (!isset($itemContent['permission']) || AdminAuthorization::can($itemContent['permission'])) {
                    $items["{$prefix}/{$itemName}"] = $itemContent;
                    foreach ($items["{$prefix}/{$itemName}"] as $key => &$value) {
                        if (
                            in_array($key, $this->keysToTransform, true)
                            && strpos($value, '/') === false
                        ) {
                            $value = "{$prefix}/{$value}";
                        }
                    }
                    unset($value);
                }
            }
        }

        $routes = $navigation['routes'] ?? [];

        return array_merge(
            ($home === null) ? [] : ['home' => $home],
            empty($sections) ? [] : ['sections' => $sections],
            empty($items) ? [] : ['items' => $items],
            empty($routes) ? [] : ['routes' => $routes]
        );
    }

    /**
     * Cleanup unused sections.
     *
     * @param array $navigation
     *
     * @return  array
     */
    protected function cleanUpEmptySections(array $navigation): array
    {
        if (empty($navigation['items'])) {
            return [];
        }

        $usedSections = [];

        foreach ($navigation['items'] as $item) {
            if (isset($item['section']) && !in_array($item['section'], $usedSections, true)) {
                $usedSections[] = $item['section'];
            }
        }

        $navigation['sections'] = array_intersect_key($navigation['sections'], array_flip($usedSections));

        return $navigation;
    }

    /**
     * Get current logged in user.
     *
     * @return  Authenticatable|null
     */
    protected function getUser(): ?Authenticatable
    {
        if (auth('admin')->check()) {
            return auth('admin')->user();
        }

        if (auth('manager')->check()) {
            return auth('manager')->user();
        }

        return null;
    }
}
