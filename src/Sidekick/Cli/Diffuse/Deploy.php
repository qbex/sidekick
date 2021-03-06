<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Cli\Diffuse;

use Cubex\Cli\CliCommand;
use Cubex\Log\Log;
use Sidekick\Components\Diffuse\Helpers\VersionHelper;
use Sidekick\Components\Diffuse\Mappers\Deployment;
use Sidekick\Components\Diffuse\Mappers\DeploymentStep;
use Sidekick\Components\Diffuse\Mappers\DeploymentLog;
use Sidekick\Components\Diffuse\Mappers\DeploymentConfig;
use Sidekick\Components\Diffuse\Mappers\Version;
use Sidekick\Components\Fortify\FortifyHelper;
use Sidekick\Components\Fortify\Mappers\Build;
use Sidekick\Components\Fortify\Mappers\BuildRun;
use Sidekick\Components\Projects\Mappers\Project;
use Sidekick\Components\Servers\Mappers\Server;
use Sidekick\Components\Users\Mappers\User;
use Symfony\Component\Process\Process;

class Deploy extends CliCommand
{
  /**
   * @valuerequired
   */
  public $versionId;

  /**
   * @valuerequired
   */
  public $userId;

  public $verbose;

  /**
   * @valuerequired
   */
  public $platformId;

  /**
   * @valuerequired
   */
  public $deploymentId;

  protected $_echoLevel = 'debug';

  public function execute()
  {
    $deployment = null;
    try
    {
      if($this->deploymentId > 0)
      {
        $deployment = new Deployment($this->deploymentId);
        if(!$deployment->exists()
          || !in_array($deployment->pending, [1, "1", true])
        )
        {
          throw new \Exception(
            "The deployment you are trying to run is not pending, " .
            "or does not exist."
          );
        }
        else
        {
          $version   = new BuildRun($deployment->buildId);
          $depConfig = new DeploymentConfig($deployment->platformId);
          $project   = new Project($deployment->projectId);
          $user      = new User($deployment->userId);
        }
      }

      if($deployment === null)
      {
        $version = new BuildRun($this->versionId);
        if(!$version->exists())
        {
          throw new \Exception("The version specified does not exist");
        }

        $depConfig = new DeploymentConfig($this->platformId);
        if(!$depConfig->exists())
        {
          throw new \Exception(
            "The deployment config specified does not exist"
          );
        }

        $project = new Project($version->projectId);
        if(!$project->exists())
        {
          throw new \Exception("The project specified does not exist");
        }

        $user = new User($this->userId);
        if(!$user->exists())
        {
          throw new \Exception("The user specified does not exist");
        }
      }

      //$this->_createVersionDataFile($version);

      if($deployment === null)
      {
        $deployment             = new Deployment();
        $deployment->platformId = $depConfig->id();
        $deployment->versionId  = $version->id();
        $deployment->projectId  = $project->id();
        $deployment->userId     = $user->id();
      }

      $deployment->startedAt = new \DateTime();
      $deployment->saveChanges(); //Initiate deployment for the ID

      $servers   = Server::collection()->loadIds(
        json_decode($deployment->hosts)
      );
      $steps     = DeploymentStep::collection(
        ['platform_id' => $depConfig->id()]
      )->setOrderBy('order', 'ASC');
      $buildPath = FortifyHelper::buildPath($version->id());

      //work out build directory
      $build          = new Build($version->buildId);
      $buildSourceDir = build_path($buildPath, $build->sourceDirectory);

      if(file_exists($buildSourceDir))
      {
        /**
         * @var Server $server
         */
        foreach($servers as $server)
        {
          foreach($steps as $step)
          {
            $command = str_replace(
              [
                '{buildSource}',
                '{deployBase}',
                '{username}',
                '{server}',
                '{hostname}',
                '{sshport}',
                '{ipv4}',
                '{ipv6}',
                '{buildId}',
                '{monitGroup}'
              ],
              [
                $buildSourceDir,
                rtrim($deployment->deployBase, '/'),
                $server->sshUser,
                $server->getConnPreference(),
                $server->hostname,
                $server->sshPort,
                $server->ipv4,
                $server->ipv6,
                $version->id(),
                $project->monitGroup
              ],
              $step->command
            );

            echo "Running $step->name ($command) on " . $server->hostname . PHP_EOL;
            $process = new Process($command);
            $process->run();

            $sh                    = new DeploymentLog();
            $sh->deploymentId      = $deployment->id();
            $sh->deploymentStageId = $step->id();
            $sh->serverId          = $server->id();
            $sh->command           = $command;
            $sh->passed            = $process->getExitCode() == 0;
            $sh->stdOut            = $process->getOutput();
            $sh->stdErr            = $process->getErrorOutput();
            $sh->log               = 'TODO';
            $sh->saveChanges();
          }
        }

        $deployment->passed    = 1;
        $deployment->completed = 1;
        //Stop the deployment from being pending, to ensure it no longer gets
        //picked up by the queue consumer
        $deployment->pending = 0;
        $deployment->saveChanges();
      }
      else
      {
        Log::error(
          "Cannot deploy build #$version->id. "
          . "Artifact does no longer exist at $buildSourceDir"
        );
        $deployment->passed    = false;
        $deployment->completed = 0;
        $deployment->pending   = 0;
        $deployment->saveChanges();
      }
    }
    catch(\Exception $e)
    {
      if($deployment !== null)
      {
        $deployment->passed    = false;
        $deployment->completed = 1;
        $deployment->pending   = 0;
        $deployment->saveChanges();
      }
      throw $e;
    }
  }

  protected function _createVersionDataFile(Version $v)
  {
    $sourcePath = VersionHelper::sourceLocation($v);
    $filePath   = $sourcePath . 'DIFFUSE.VERSION';
    file_put_contents($filePath, $this->_versionFile($v));
  }

  protected function _versionFile(Version $v)
  {
    $content = '';
    $content .= 'Version: ' . $v->format() . "\n";
    $content .= 'Commits: ' . $v->fromCommitHash . ' - ' . $v->toCommitHash;
    $content .= "\n";
    $content .= 'State: ' . $v->versionState . "\n";
    $content .= 'Project ID: ' . $v->projectId . "\n";
    $content .= 'Build ID: ' . $v->buildId . "\n";
    $content .= 'Repository ID: ' . $v->repoId . "\n";
    $content .= 'Created: ' . $v->getData($v->createdAttribute()) . "\n";
    $content .= 'Updated: ' . $v->getData($v->updatedAttribute()) . "\n";
    $content .= "\n== Change Log ==\n";
    $content .= $v->changeLog;
    return $content;
  }
}
