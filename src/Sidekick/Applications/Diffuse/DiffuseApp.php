<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Applications\Diffuse;

use Bundl\Debugger\DebuggerBundle;
use Sidekick\Applications\BaseApp\BaseApp;
use Sidekick\Applications\Diffuse\Controllers\DefaultController;

class DiffuseApp extends BaseApp
{
  public function getRoutes()
  {
    return [
      'api/builds(.*)' => 'Api\Builds',
      'projects/(.*)'  => 'ProjectController',
    ];
  }

  public function defaultController()
  {
    return new DefaultController();
  }

  public function getBundles()
  {
    return [
      //new DebuggerBundle()
    ];
  }

  public function name()
  {
    return "Diffuse";
  }

  public function description()
  {
    return "Code Distribution";
  }
}
