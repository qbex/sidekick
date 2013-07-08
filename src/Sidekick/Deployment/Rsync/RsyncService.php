<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Deployment\Rsync;

use Cubex\Data\Handler\DataHandler;
use Cubex\Log\Log;
use Sidekick\Components\Diffuse\Helpers\VersionHelper;
use Sidekick\Components\Diffuse\Mappers\DeploymentStageHost;
use Sidekick\Components\Diffuse\Mappers\Version;
use Sidekick\Deployment\BaseDeploymentService;
use Symfony\Component\Process\Process;

class RsyncService extends BaseDeploymentService
{

  public function deploy()
  {
    $cfg = (new DataHandler())->hydrate($this->_stage->configuration);

    //Base path to deploy code to (remote)
    $remoteBase = $cfg->getStr('deploy_base');
    if($remoteBase === null)
    {
      throw new \Exception("No deploy_base value has been configured.");
    }

    //-z optional (disable for lan sync)
    $options = $cfg->getStr('options', 'z'); //Get options

    foreach($this->_hosts as $stageHost)
    {
      $host = $stageHost->host();

      //Automatically deploy with hard links
      $cmd = 'rsync -aH' . $options . ' --link-dest ';

      //Remote Old Version Path
      $cmd .= build_path(
        $remoteBase,
        $this->_versionPath($this->_previsionVersion())
      );

      //Local Source Path
      $cmd .= " " . VersionHelper::sourceLocation($this->_version);

      //Remote Path
      $cmd .= ' ' . $host->hostname . ':';
      $cmd .= build_path($remoteBase, $this->_versionPath($this->_version));

      Log::info(
        "Deploying to " . $host->hostname . " with '" . $cmd . "'"
      );

      $stageHost->log = $cmd;

      $proc = new Process($cmd);
      $proc->run();
      $stageHost->passed = $proc->getExitCode() === 0;
    }
  }

  protected function _versionPath(Version $v)
  {
    return $v->format() . DS;
  }

  protected function _previsionVersion()
  {
    return new Version();
  }
}
