<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Applications\Diffuse\Controllers\Projects;

use Cubex\Facade\Redirect;
use Cubex\View\RenderGroup;
use Sidekick\Applications\BaseApp\Controllers\ProjectAwareBaseControl;
use Sidekick\Applications\Diffuse\Views\DeploymentsView;
use Sidekick\Applications\Diffuse\Views\DeploymentView;
use Sidekick\Components\Diffuse\Mappers\Deployment;
use Sidekick\Components\Diffuse\Mappers\DeploymentConfig;
use Sidekick\Components\Fortify\Mappers\BuildRun;
use Sidekick\Components\Projects\Mappers\Project;
use Sidekick\Components\Servers\Mappers\Server;

class OverviewController extends ProjectAwareBaseControl
{
  protected $_projectId;

  public function preProcess()
  {
    $this->_projectId = $this->getProjectId();
  }

  public function getSidebar()
  {
    return null;
  }

  public function renderIndex()
  {
    $project = new Project($this->_projectId);
    if($project->exists())
    {
      $hosts   = Server::collection();
      $configs = DeploymentConfig::collection();

      $buildRun = new BuildRun($this->getInt('buildId'));

      return new RenderGroup(
        $this->createView(
          new DeploymentView($project, $hosts, $configs, $buildRun)
        )
      );
      /*$versions = Version::collection(['project_id' => $this->_projectId])
      ->setOrderBy("id", "DESC")->setLimit(0, 50)->preFetch("platformStates");
      return new RenderGroup(
        $this->createView(new ProjectNav($this->appBaseUri(), $project)),
        $this->createView(
          new OverviewView($project, $versions, DeploymentConfig::collection())
        )
      );*/
    }
    else
    {
      throw new \Exception("You seem to have stumbed upon.... nothing.");
    }
  }

  public function postIndex()
  {
    $postData = $this->request()->postVariables();
    //create a new deployment
    $deployment             = new Deployment();
    $deployment->pending    = true;
    $deployment->platformId = $postData["platformId"]; //TODO rename to deploymentConfigId
    $deployment->projectId  = $this->_projectId;
    $deployment->versionId  = $this->getInt("buildId"); //this is now build id. todo rename field
    $deployment->userId     = \Auth::user()->getId();
    $deployment->hosts      = json_encode(array_keys($postData['deploymentHosts']));
    $deployment->comment    = $postData['comment'];
    $deployment->deployBase = $postData['deploy_base'];

    $deployment->saveChanges();

    Redirect::to('/P'.$this->_projectId.'/diffuse/deployments')->now();
  }

  public function renderDeployments()
  {
    $deployments = Deployment::collection();
    $project = new Project();
    if($this->_projectId)
    {
      $project = new Project($this->_projectId);
      if($project->exists())
      {
        $deployments = Deployment::collection()->loadWhere("project_id = %d", $this->_projectId);
      }
    }
    else
    {
      $deployments = Deployment::conn()->getKeyedRows(
        'SELECT id FROM (
         SELECT * FROM diffuse_deployments WHERE pending != 1 ORDER BY id DESC) AS sub GROUP BY project_id'
      );
      $deployments = Deployment::collection()->loadWhere("id IN (%s)", implode("','",$deployments ));
    }

    return new DeploymentsView($deployments, $project);
  }


  public function getRoutes()
  {
    return [
    ];
  }
}
