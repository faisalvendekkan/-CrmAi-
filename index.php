<?php
/**
 * Meridian HR — front controller.
 * Every request (except static assets) is routed here by .htaccess.
 */
declare(strict_types=1);

define("APP_ROOT", __DIR__);

require __DIR__ . "/app/bootstrap.php";

App::run();
