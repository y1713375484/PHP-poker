<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace support\view;

use Jenssegers\Blade\Blade as BladeView;
use Webman\View;

/**
 * Class Blade
 * composer require jenssegers/blade
 * @package support\view
 */
class Blade implements View
{
    /**
     * @var array
     */
    protected static $_vars = [];

    /**
     * @param string|array $name
     * @param mixed $value
     */
    public static function assign($name, $value = null)
    {
        static::$_vars = \array_merge(static::$_vars, \is_array($name) ? $name : [$name => $value]);
    }

    /**
     * @param string $template
     * @param array $vars
     * @param string|null $app
     * @return string
     */
    public static function render(string $template, array $vars, string $app = null)
    {
        static $views = [];
        $request = \request();
        $plugin = $request->plugin ?? '';
        $app = $app === null ? $request->app : $app;
        $config_prefix = $plugin ? "plugin.$plugin." : '';
        $base_view_path = $plugin ? \base_path() . "/plugin/$plugin/app" : \app_path();
        $key = "{$plugin}-{$request->app}";
        if (!isset($views[$key])) {
            $view_path = $app === '' ? "$base_view_path/view" : "$base_view_path/$app/view";
            $views[$key] = new BladeView($view_path, \runtime_path() . '/views');
            $extension = \config("{$config_prefix}view.extension");
            if ($extension) {
                $extension($views[$key]);
            }
        }
        $vars = \array_merge(static::$_vars, $vars);
        $content = $views[$key]->render($template, $vars);
        static::$_vars = [];
        return $content;
    }
}
