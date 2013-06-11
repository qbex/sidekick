<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Cli\Fortify;

use Cubex\Cli\CliCommand;
use Cubex\Cli\PidFile;
use Cubex\Cli\Shell;
use Cubex\Facade\Queue;
use Cubex\Log\Log;
use Cubex\Queue\CallableQueueConsumer;
use Cubex\Queue\StdQueue;
use Sidekick\Components\Fortify\Enums\BuildLevel;
use Sidekick\Components\Repository\Mappers\Source;
use Symfony\Component\Process\Process;
use Sidekick\Components\Fortify\Mappers\Build;

/**
 * Run Build Queue
 */
class BuildQueue extends CliCommand
{
  public $verbose;

  protected $_pidFile;

  protected $_echoLevel = 'debug';

  public function execute()
  {
    $this->_pidFile = new PidFile();
    Log::debug("Starting Queue Consumer");
    $queue = new StdQueue('BuildRequest');
    Queue::consume(
      $queue,
      new CallableQueueConsumer([$this, 'runBuild'], 10)
    );
    Log::debug("Completed Consume");
  }

  public function runBuild($queue, $data)
  {
    if(isset($data->buildId))
    {
      $buildIds = [$data->buildId];
    }
    else if(isset($data->buildIds))
    {
      $buildIds = $data->buildIds;
    }
    else
    {
      $buildIds = Build::collection(['build_level' => BuildLevel::BUILD])
                  ->get()->loadedIds();
    }

    Log::debug("Build IDs Available: " . implode(',', $buildIds));

    $project = (new Source($data->respositoryId))->projectId;

    foreach($buildIds as $buildId)
    {
      Log::debug("Entering Build Run for repo: " . $data->respositoryId);
      $cwd     = getcwd();
      $rawArgs = [
        '--cubex-env=' . CUBEX_ENV,
        'Fortify.Build',
        '-b',
        $buildId,
        '-p',
        $project
      ];

      if($this->verbose)
      {
        $rawArgs[] = '-v';
      }

      Log::debug("Starting Build");

      $command = 'php ' . WEB_ROOT . DS . 'cubex ' . implode(' ', $rawArgs);

      Log::debug("Executing: $command");

      $process = new Process($command);
      if($this->verbose)
      {
        $process->run(
          function ($type, $buffer)
          {
            echo $buffer;
          }
        );
      }
      else
      {
        $process->run();
      }

      Log::debug("Executed Build (Exit Code: " . $process->getExitCode() . ")");
      chdir($cwd);
      Log::debug("Completed Build Run");
    }
    return true;
  }
}
