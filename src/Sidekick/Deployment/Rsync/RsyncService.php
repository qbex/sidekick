<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Deployment\Rsync;

use Cubex\Cli\Shell;
use Cubex\Data\Handler\DataHandler;
use Cubex\Helpers\System;
use Cubex\Log\Log;
use Sidekick\Components\Diffuse\Helpers\VersionHelper;
use Sidekick\Components\Diffuse\Mappers\DeploymentLog;
use Sidekick\Components\Diffuse\Mappers\Version;
use Sidekick\Components\Enums\ApprovalState;
use Sidekick\Deployment\BaseDeploymentService;
use Symfony\Component\Process\Process;

class RsyncService extends BaseDeploymentService
{

  public function deploy()
  {
    // Timeout for running the rsync command
    $timeout = 1200;

    $cfg = (new DataHandler())->hydrate($this->_stage->configuration);

    //Base path to deploy code to (remote)
    $remoteBase = $cfg->getStr('deploy_base');
    if($remoteBase === null)
    {
      throw new \Exception("No deploy_base value has been configured.");
    }

    //-z optional (disable for lan sync)
    $optionString = " -rltH";
    $options = $cfg->getStr('options', '-z'); //Get options
    if(System::isWindows())
    {
      //--chmod=u=rwX,go=rX
      //--chmod=Du+rwx,og-w,Dog+rx,Fu+rw,Fog+r,F-x
      $optionString .= " --chmod=Du+rwx,og-w,Dog+rx,Fu+rw,Fog+r,F-x " . $options;
    }
    else
    {
      $optionString .= "p " . $options;
    }

    foreach($this->_hosts as $stageHost)
    {
      $host = $stageHost->server();
      if($host->sshPort < 1)
      {
        $host->sshPort = 22;
      }

      //Automatically deploy with hard links
      $cmd = "rsync --rsh='ssh -p $host->sshPort'";
      $cmd .= $optionString . ' --link-dest ';

      //Remote Old Version Path
      $cmd .= build_path_unix(
        $remoteBase,
        $this->_versionPath($this->_previsionVersion())
      );

      //Local Source Path
      $sourcePath = VersionHelper::sourceLocation($this->_version);
      if(CUBEX_ENV === 'development' && Shell::commandExists('cygpath'))
      {
        $sourcePath = trim(shell_exec('cygpath "' . $sourcePath . '"'));
      }
      $cmd .= ' ' . $sourcePath . ' ';

      //Remote Path
      $cmd .= $host->sshUser !== null ? $host->sshUser . '@' : ''; //Username
      $cmd .= $host->getConnPreference() . ':'; //Hostname | IP
      $cmd .= build_path_unix(
        $remoteBase,
        $this->_versionPath($this->_version)
      );

      Log::info(
        "Deploying to " . $host->name . " with '" . $cmd . "'"
      );

      $start = microtime(true);
      $proc  = new Process($cmd);
      $proc->setTimeout($timeout);
      $proc->setIdleTimeout($timeout);
      $proc->run(
        function ($type, $buffer)
        {
          Log::debug($type . ") " . $buffer);
        }
      );

      $stageHost->executionTime = microtime(true) - $start;
      $stageHost->command       = $cmd;
      $stageHost->stdErr        = $proc->getErrorOutput();
      $stageHost->stdOut        = $proc->getOutput();
      $stageHost->passed        = $proc->getExitCode() === 0;
    }
  }

  protected function _versionPath(Version $v)
  {
    return $v->format() . '/';
  }

  protected function _previsionVersion()
  {
    if($this->_version->build < 1)
    {
      return new Version();
    }
    $previous = Version::loadWhere(
      [
        "projectId"    => $this->_version->projectId,
        "versionState" => ApprovalState::APPROVED,
        "major"        => $this->_version->major,
        "minor"        => $this->_version->minor,
        "build"        => $this->_version->build - 1,
      ]
    );

    if($previous === null)
    {
      return new Version();
    }

    return $previous;
  }

  public static function getConfigurationItems()
  {
    return [
      'deploy_base' => 'Remote base path to deploy code to',
      'options'     => 'Rsync options, defaults to: z'
    ];
  }
}
