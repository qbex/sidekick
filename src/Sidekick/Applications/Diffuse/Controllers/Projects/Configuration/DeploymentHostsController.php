<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Applications\Diffuse\Controllers\Projects\Configuration;

use Cubex\Data\Transportable\TransportMessage;
use Cubex\Facade\Redirect;
use Cubex\Routing\Templates\ResourceTemplate;
use Cubex\View\RenderGroup;
use Sidekick\Applications\Diffuse\Controllers\DiffuseController;
use
Sidekick\Applications\Diffuse\Views\Projects\Configuration\DeploymentHostsView;
use Sidekick\Applications\Diffuse\Views\Projects\ProjectNav;
use Sidekick\Components\Diffuse\Mappers\HostPlatform;
use Sidekick\Components\Diffuse\Mappers\Platform;
use Sidekick\Components\Projects\Mappers\Project;
use Sidekick\Components\Servers\Mappers\Server;

class DeploymentHostsController extends DiffuseController
{
  public function renderIndex()
  {
    $projectId = $this->getInt("projectId");
    $project   = new Project($projectId);
    $hosts     = Server::collection();
    $platforms = Platform::orderedCollection();

    return new RenderGroup(
      $this->createView(new ProjectNav($this->request()->path(3), $project)),
      $this->createView(
        new DeploymentHostsView($project, $hosts, $platforms)
      )
    );
  }

  public function postCreate()
  {
    $projectId = $this->getInt('projectId');

    //out with the old...
    $oldHostPlatfoms = HostPlatform::collection(['project_id' => $projectId]);
    foreach($oldHostPlatfoms as $hostPlatform)
    {
      $hostPlatform->delete();
    }

    //...in with the new
    $deploymentHosts = $this->request()->postVariables('deploymentHosts');
    foreach($deploymentHosts as $platformId => $hosts)
    {
      $hosts = array_keys($hosts);
      foreach($hosts as $hostId)
      {
        $hp             = new HostPlatform();
        $hp->platformId = $platformId;
        $hp->projectId  = $projectId;
        $hp->hostId     = $hostId;
        $hp->saveChanges();
      }
    }

    Redirect::to($this->baseUri())->with(
      'msg',
      new TransportMessage('success', 'Deployment Hosts saved successfully')
    )->now();
  }

  public function getRoutes()
  {
    $routes = ResourceTemplate::getRoutes();
    return $routes;
  }
}
