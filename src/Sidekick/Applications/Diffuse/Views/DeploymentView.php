<?php
/**
 * Author: oke.ugwu
 * Date: 28/08/13 18:24
 */

namespace Sidekick\Applications\Diffuse\Views;

use Cubex\Form\Form;
use Cubex\Form\OptionBuilder;
use Cubex\View\TemplatedViewModel;
use Sidekick\Components\Diffuse\Mappers\Deployment;
use Sidekick\Components\Fortify\Mappers\Build;
use Sidekick\Components\Fortify\Mappers\BuildChanges;
use Sidekick\Components\Fortify\Mappers\BuildRun;
use Sidekick\Components\Projects\Mappers\Project;

class DeploymentView extends TemplatedViewModel
{
  /**
   * @var Project $_project
   */
  protected $_project;
  protected $_hosts;
  protected $deploymentConfigs;
  protected $_form;
  protected $_buildRun;
  protected $_deploymentChanges;
  protected $_steps;
  protected $_lastDeployedBuild;

  public function __construct($project, $hosts, $deploymentConfigs, $buildRun, $steps)
  {
    $this->_project = $project;
    $this->_hosts = $hosts;
    $this->deploymentConfigs = $deploymentConfigs;
    $this->_buildRun = $buildRun;

    $this->_lastDeployedBuild = $this->_getLastDeployedBuildId(
      $buildRun->branch,
      $project->id()
    );

    if($this->_lastDeployedBuild)
    {
      $this->_builds = $this->_getBuildsNotDeployed(
        $this->_lastDeployedBuild,
        $buildRun->branch,
        $project->id()
      );

      if(!empty($this->_builds))
      {
        $this->_deploymentChanges = $this->_getChangesFromBuilds(
          $this->_builds
        );
      }
    }
    else
    {
      $this->_deploymentChanges = $this->_getChangesFromBuilds(
        [$this->_buildRun]
      );
    }
    $this->_steps = $steps;
  }

  /**
   * @return boolean
   */
  public function getLastDeployedBuild()
  {
    return $this->_lastDeployedBuild;
  }

  public function getSteps()
  {
    return !empty($this->_steps) ? $this->_steps : [];
  }
  
  public function getDeploymentChanges()
  {
    return !empty($this->_deploymentChanges) ? $this->_deploymentChanges : array();
  }

  protected function _getBuildsNotDeployed($lastBuildDeployed, $branch, $project
  )
  {
    $query = sprintf(
      "SELECT id FROM fortify_build_runs WHERE id > %d "
      . "AND branch = '%s' AND project_id = %d",
      $lastBuildDeployed,
      $branch,
      $project
    );
    return BuildRun::conn()->getKeyedRows(
      $query
    );
  }

  protected function _getChangesFromBuilds($buildRunIds)
  {
    return BuildChanges::collection()->loadWhere(
      "build_run_id IN(" . implode(",", $buildRunIds) . ')'
    );
  }

  /**
   * @param $branch
   * @param $projectId
   *
   * @return bool
   */
  protected function _getLastDeployedBuildId($branch, $projectId)
  {

    $lastDeployment = Deployment::collection()
      ->loadWhere('project_id = %d AND branch=%s', $projectId, $branch)
      ->setOrderBy('id', 'DESC')->first();
    if($lastDeployment)
    {
      return $lastDeployment->buildId;
    }
    return false;
  }

  public function hosts()
  {
    return $this->_hosts;
  }

  public function deploymentConfigs()
  {
    return $this->deploymentConfigs;
  }

  public function form()
  {
    if($this->_form != null)
    {
      return $this->_form;
    }
    $this->_form = new Form('deploymentHosts');
    $this->_form->setDefaultElementTemplate('{{input}}');
    $this->_form->addHiddenElement('buildId', $this->_buildRun->id());

    if((int)$this->_project->deploymentConfigId <= 0)
    {
      $options = (new OptionBuilder($this->deploymentConfigs))->getOptions();
      $options = [0 => 'Select a Config'] + $options;
      $this->_form->addSelectElement("configId", $options);
    }
    else
    {
      $this->_form->addHiddenElement(
        'configId',
        $this->_project->deploymentConfigId
      );
    }

    $this->_form->addTextElement('deploy_base', $this->_project->deployBase);

    foreach($this->hosts() as $host)
    {
      $this->_form->addCheckboxElement(
        "deploymentHosts[$host->id]",
        false,
        true,
        FORM::LABEL_BEFORE
      );

      $this->_form->getElement(
        "deploymentHosts[$host->id]"
      )->setLabel($host->hostname);
    }

    $this->_form->addTextAreaElement('comment');
    $this->_form->addSubmitElement('Deploy');
    $this->_form->getElement('submit')->addAttribute(
      'class',
      'btn btn-success'
    );

    //get previous deployment for this project and pre-populate form
    $lastDeployment = Deployment::collection()->loadWhere(
      ['project_id' => $this->_project->id()]
    )->setOrderBy('id', 'DESC')->first();

    if($lastDeployment)
    {
      if((int)$this->_project->deploymentConfigId <= 0)
      {
        $this->_form->getElement('configId')->setData(
          $lastDeployment->platformId
        );
      }

      if($lastDeployment->deployBase)
      {
        $this->_form->getElement('deploy_base')->setData(
          $lastDeployment->deployBase
        );
      }

      $lastDeployHosts = json_decode($lastDeployment->hosts);
      if(is_array($lastDeployHosts))
      {
        foreach($this->hosts() as $host)
        {
          if(in_array($host->id, $lastDeployHosts))
          {
            $this->_form->getElement("deploymentHosts[$host->id]")->setData(
              true
            );
          }
        }
      }
    }

    return $this->_form;
  }

  public function project()
  {
    return $this->_project;
  }
}
