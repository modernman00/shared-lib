<?php

namespace helpers\classes;

use eftec\bladeone\BladeOne;


class Blade
{
    private static ?BladeOne $instance = null;
    private static ?string $viewPath = null;
    private static ?string $cachePath = null;

    /**
     * Explicitly set paths for views and cache. 
     * Best practice for packages to avoid path guessing.
     */
    public static function configure(string $viewPath, string $cachePath): void
    {
        self::$viewPath = rtrim($viewPath, '/');
        self::$cachePath = rtrim($cachePath, '/');
        self::$instance = null; // Reset singleton to apply new paths
    }

    public static function get(): BladeOne
    {
        if (!self::$instance) {
            $root = self::getProjectRoot();
            
            $view = self::$viewPath ?: "$root/resources/views";
            $cache = self::$cachePath ?: "$root/bootstrap/cache";

            $mode = ($_ENV['APP_ENV'] ?? 'production') === 'production'
                ? BladeOne::MODE_AUTO
                : BladeOne::MODE_DEBUG;

            $blade = new BladeOne($view, $cache, $mode);
            $blade->pipeEnable = true;
            $blade->setBaseUrl($_ENV['APP_URL'] ?? '/');

            // Register directives
            $blade->directive('csrf', function () {
                return "<?php echo csrfField(); ?>";
            });

            self::$instance = $blade;
        }

        return self::$instance;
    }

    /**
     * Smart root discovery for vendor packages.
     */
    private static function getProjectRoot(): string
    {
        if (\defined('BASE_PATH')) {
            return \BASE_PATH;
        }

        // Locate project root via Composer's ClassLoader location
        if (\class_exists(\Composer\Autoload\ClassLoader::class)) {
            $reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
            return \dirname($reflection->getFileName(), 3);
        }

        return \dirname(__DIR__, 5);
    }
}
