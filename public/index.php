<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$_SERVER['REQUEST_SCHEME'] = ($_SERVER['HTTPS'] ?? null) === 'on' ? 'https' : 'http';


$redirect_url = $_GET['redirect'] ?? $_GET['redirect_url'] ?? $_GET['redirect_to'] ?? null;

if(isset($redirect_url))
{
  $redirect_url = urldecode(trim($redirect_url));

  if(mb_strlen($redirect_url))
  {
    $host = mb_strtolower(trim(parse_url($redirect_url, PHP_URL_HOST), 'www.'));
    $original_host = mb_strtolower(trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_HOST), 'www.'));

    if($host !== $original_host)
    {
        header('location: /');
        return;
    }
  }
}


/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../app/Helpers/Helpers.php';
require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
