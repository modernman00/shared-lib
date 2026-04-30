<?php

namespace helper\classes;

use eftec\bladeone\BladeOne;


class Blade
{
  private static ?BladeOne $instance = null;

  public static function get(): BladeOne
  {
    if (!self::$instance) {
      // $views = realpath(__DIR__ . '/../../resources/views');
      // $cache = realpath(__DIR__ . '/../../bootstrap/cache');

      $view = rtrim(__DIR__ . '/../../../../resources/views', '/'); // Remove trailing slash
      $cache = rtrim(__DIR__ . '/../../../../bootstrap/cache', '/');

      $mode = $_ENV['APP_ENV'] === 'production'
        ? BladeOne::MODE_AUTO
        : BladeOne::MODE_DEBUG;

      $blade = new BladeOne($view, $cache, $mode);
      $blade->pipeEnable = true;
      $blade->setBaseUrl($_ENV['APP_URL']);


      // Register directives
      $blade->directive('csrf', function () {
        return "<?php echo csrfField(); ?>";
      });

      self::$instance = $blade;
    }

    return self::$instance;
  }
}
