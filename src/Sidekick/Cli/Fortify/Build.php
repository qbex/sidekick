<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Cli\Fortify;

use Cubex\Cli\CliCommand;
use Cubex\Cli\Shell;
use Cubex\FileSystem\FileSystem;
use Cubex\Helpers\DependencyArray;
use Cubex\Helpers\Strings;
use Cubex\Helpers\System;
use Cubex\Log\Debug;
use Cubex\Log\Log;
use Sidekick\Components\Fortify\Enums\BuildResult;
use Sidekick\Components\Fortify\Enums\FileSet;
use Sidekick\Components\Fortify\FortifyBuildChanges;
use Sidekick\Components\Fortify\FortifyHelper;
use Sidekick\Components\Fortify\Mappers\Command;
use Sidekick\Components\Fortify\Mappers\BuildLog;
use Sidekick\Components\Fortify\Mappers\BuildRun;
use Sidekick\Components\Fortify\Mappers\BuildsCommands;
use Sidekick\Components\Fortify\Mappers\BuildsProjects;
use Sidekick\Components\Fortify\Mappers\Patch;
use Sidekick\Components\Projects\Mappers\Project;
use Sidekick\Components\Repository\Enums\RepositoryProvider;
use Sidekick\Components\Repository\Mappers\Commit;
use Sidekick\Components\Repository\Mappers\CommitFile;
use Sidekick\Components\Repository\Mappers\Source;
use Symfony\Component\Process\Process;

class Build extends CliCommand
{
  protected $_echoLevel = 'debug';

  /**
   * The Project ID you wish to run a build for
   * @required
   * @valuerequired
   * @example PIDX
   */
  public $project;

  /**
   * The Build ID you wish to run a build for
   * @required
   * @valuerequired
   * @example BIDX
   */
  public $build;

  /**
   * The Patch ID you wish to build with
   * @valuerequired
   * @example 1
   */
  public $patch;

  public $verbose;

  /**
   * Number of seconds before a single process will timeout
   * @valuerequired
   */
  public $timeout = 300;

  /**
   * Number of seconds before a single process will timeout (after no output)
   * @valuerequired
   */
  public $idleTimeout = 120;

  protected $_buildRunId;
  protected $_buildResult;
  protected $_buildSourceDir;
  protected $_buildPath;
  protected $_branch = 'master';
  protected $_commitHash;

  protected $_totalTests = 0;
  protected $_testsRun = 0;
  protected $_testsPass = 0;
  protected $_testsFail = 0;
  protected $_lineSplit = '';

  public function execute()
  {
    $this->_lineSplit = str_repeat('=', 80);

    $projectId = (int)$this->project;
    $buildId   = (int)$this->build;

    $project      = new Project($projectId);
    $build        = new \Sidekick\Components\Fortify\Mappers\Build($buildId);
    $buildProject = new BuildsProjects([$build, $project]);
    if($buildProject->buildSourceId < 1)
    {
      $buildProject->buildSourceId = $project->repository('master')->id();
    }

    $buildRun            = new BuildRun();
    $buildRun->buildId   = $build->id();
    $buildRun->projectId = $project->id();
    $buildRun->startTime = new \DateTime();
    $buildRun->commands  = [];
    $this->_buildResult  = $buildRun->result = BuildResult::RUNNING;
    $buildRun->saveChanges();
    $this->_buildRunId = $buildRun->id();

    if(!System::isWindows())
    {
      declare(ticks = 1);
      pcntl_signal(SIGINT, array($buildRun, "exited"));
      pcntl_signal(SIGTERM, array($buildRun, "exited"));
      pcntl_signal(SIGHUP, array($buildRun, "exited"));
    }

    Log::notice(
      "Starting Build for: " . $project->name . " (" . $build->name . ")"
    );
    Log::notice("Build ID: " . $this->_buildRunId);

    $buildPath = FortifyHelper::buildPath($buildRun->id());
    mkdir($buildPath, 0777, true);
    chdir($buildPath);
    $this->_buildPath      = $buildPath;
    $this->_buildSourceDir = $build->sourceDirectory;

    $buildRun->commands = array_merge($buildRun->commands, ['source']);
    $buildRun->endTime  = new \DateTime();
    $buildRun->saveChanges();

    $buildSource   = new Source($buildProject->buildSourceId);
    $this->_branch = $buildSource->branch;
    $this->_downloadSourceCode($buildSource, $this->_buildSourceDir);

    chdir($this->_buildSourceDir);
    $process = new Process("git rev-parse --verify HEAD");
    $process->setTimeout($this->timeout);
    $process->setIdleTimeout($this->idleTimeout);
    $process->run();
    $this->_commitHash = $buildRun->commitHash = trim($process->getOutput());
    chdir($buildPath);

    if($this->patch !== null)
    {
      $this->_applyPatch($this->patch, $this->_buildSourceDir);
    }

    $commands     = BuildsCommands::collectionOn($build);
    $dependencies = new DependencyArray();

    foreach($commands as $com)
    {
      /**
       * @var $com BuildsCommands
       */
      $dependencies->add(
        $com->getData("command_id"),
        $com->getData("dependencies")
      );
      $this->_totalTests++;
    }

    $commandList = $dependencies->getLoadOrder();
    foreach($commandList as $commandId)
    {
      $buildRun->commands = array_merge($buildRun->commands, [$commandId]);
      $buildRun->endTime  = new \DateTime();
      $buildRun->saveChanges();

      $pass = $this->_runCommand($commandId);
      Log::debug("Command $commandId " . ($pass ? 'passed' : 'failed'));
      if(!$pass)
      {
        break;
      }
    }

    if($this->_buildResult === BuildResult::RUNNING)
    {
      $this->_buildResult = BuildResult::PASS;
    }

    $buildRun->result  = $this->_buildResult;
    $buildRun->endTime = new \DateTime();
    $buildRun->saveChanges();

    //Only update commit hash if its the pure repo source
    if($this->patch === null)
    {
      $buildProject->lastCommitHash = $buildRun->commitHash;
      $buildProject->saveChanges();
    }

    $this->_buildResults($buildRun);

    if(!System::isWindows())
    {
      //Unregister Signals
      pcntl_signal(SIGINT, SIG_IGN);
      pcntl_signal(SIGTERM, SIG_IGN);
      pcntl_signal(SIGHUP, SIG_IGN);
    }

    return true;
  }

  protected function _runCommand($commandId)
  {
    $lineSplitter = $this->_lineSplit;
    $this->_testsRun++;
    $command = new Command($commandId);

    Log::info("Running " . $command->name . " ($commandId)");

    echo "\n\n$lineSplitter\n";
    echo " Running " . $command->name;
    echo "\n$lineSplitter\n";

    $log = new BuildLog();
    if($this->verbose)
    {
      $log->enableOutput();
    }
    $log->setId($this->_buildRunId . '-' . $command->id());
    $log->startTime = microtime(true);
    $log->exitCode  = -1;
    $log->saveChanges();

    $exitCode = $this->_processCommand($log, $command);

    echo "\n$command->name Result: ";

    $returnValue   = $exitCode;
    $log->exitCode = (int)$returnValue;
    $log->endTime  = microtime(true);
    $log->saveChanges();

    if($returnValue === 0)
    {
      $this->_testsPass++;
      echo Shell::colourText("PASS", Shell::COLOUR_FOREGROUND_GREEN);
    }
    else
    {
      echo Shell::colourText(
        "FAIL ($returnValue)",
        Shell::COLOUR_FOREGROUND_RED
      );

      $this->_testsFail++;

      if($command->causeBuildFailure)
      {
        $this->_buildResult = BuildResult::FAIL;
        return false;
      }
    }
    return true;
  }

  protected function _processCommand(BuildLog $log, Command $command)
  {
    $args = null;
    if(is_array($command->args))
    {
      $args = ' ' . implode(" ", $command->args);
    }

    $runCommand = $command->command . $args;

    $runCommand = str_replace(
      [
      '{sourcedirectory}',
      '{CUBEX_BIN}',
      '{CUBEX_ENV}',
      '{PROJECT_ID}',
      '{BUILD_ID}',
      '{BUILD_RUN_ID}',
      '{branch}'
      ],
      [
      $this->_buildPath . DS . $this->_buildSourceDir,
      WEB_ROOT . DS . 'cubex',
      CUBEX_ENV,
      (int)$this->project,
      (int)$this->build,
      (int)$this->_buildRunId,
      $this->_branch
      ],
      $runCommand
    );

    if($command->fileSet !== FileSet::NONE)
    {
      $command->fileSetDirectory = str_replace(
        '{sourcedirectory}',
        $this->_buildSourceDir,
        $command->fileSetDirectory
      );

      $returnExitCode = -1;
      $fileList       = null;

      if($command->fileSet === FileSet::MODIFIED)
      {
        $commitIds = $this->buildCommitRange()
        ->setColumns(['id'])
        ->get()
        ->loadedIds();

        if($commitIds)
        {
          $files = CommitFile::collection()
          ->whereIn('commit_id', $commitIds, 'd')
          ->whereIn("change_type", ['A', 'M'])
          ->setColumns(['file_path'])
          ->get()
          ->getUniqueField('file_path');

          foreach($files as $file)
          {
            if(preg_match("/$command->filePattern/", $file))
            {
              $fileList[] = $command->fileSetDirectory . $file;
            }
          }
        }
      }
      else
      {
        $fileList = $this->_getFullFilelisting(
          $command->fileSetDirectory,
          $command->filePattern
        );
      }

      if($fileList)
      {
        foreach($fileList as $file)
        {
          if(!file_exists($file))
          {
            continue;
          }

          if(stristr($runCommand, '{iteratedFilePath}'))
          {
            $process = new Process(
              str_replace(
                '{iteratedFilePath}',
                $file,
                $runCommand
              )
            );
          }
          else
          {
            $process = new Process($runCommand . ' ' . $file);
          }

          $process->setTimeout($this->timeout);
          $process->setIdleTimeout($this->idleTimeout);
          Log::debug(
            "Running (with timeout $this->timeout) $runCommand . ' ' . $file"
          );
          try
          {
            $process->run([$log, 'writeBuffer']);
          }
          catch(\Exception $e)
          {
            Log::error(
              "Command Exception (" . $e->getCode() . ') ' . $e->getMessage()
            );
          }
          $exitCode = $process->getExitCode();
          Log::debug("Command finished with exit code $exitCode");
          if($exitCode > $returnExitCode)
          {
            $returnExitCode = $exitCode;
          }
        }
      }

      if($returnExitCode === -1)
      {
        $returnExitCode = 0;
      }

      return $returnExitCode;
    }
    else
    {
      $process = new Process($runCommand);
      $process->setTimeout($this->timeout);
      $process->setIdleTimeout($this->idleTimeout);
      echo "\nRunning: " . $runCommand . "\n";
      Log::debug("Running (with timeout $this->timeout) $runCommand");
      try
      {
        $process->run([$log, 'writeBuffer']);
      }
      catch(\Exception $e)
      {
        Log::error(
          "Command Exception (" . $e->getCode() . ') ' . $e->getMessage()
        );
      }
      Log::debug("Command finished with exit code " . $process->getExitCode());
      return $process->getExitCode();
    }
  }

  /**
   * @return \Cubex\Mapper\Database\RecordCollection
   */
  public function buildCommitRange()
  {
    $changes = new FortifyBuildChanges(
      $this->project, $this->build, $this->_commitHash
    );

    return $changes->buildCommitRange();
  }

  protected function _getFullFilelisting($directory, $filePatten = '.*')
  {
    $files = [];

    $recursive = new \RecursiveDirectoryIterator($directory);
    foreach($recursive as $file)
    {
      /***
       * @var $file \SplFileInfo
       */
      $filePath = $file->getRealPath();
      if($file->isDir() && !in_array($file->getFilename(), ['.', '..']))
      {
        $files = array_merge(
          $files,
          $this->_getFullFilelisting($filePath, $filePatten)
        );
      }
      else if(preg_match("/$filePatten/", $filePath))
      {
        $files[] = $filePath;
      }
    }

    return $files;
  }

  protected function _buildResults(BuildRun $buildRun)
  {
    $lineSplitter = $this->_lineSplit;
    echo "\n\n\n$lineSplitter\n";

    echo Shell::colourText(
      "         Build Results",
      Shell::COLOUR_FOREGROUND_LIGHT_PURPLE
    );
    echo "\n$lineSplitter\n\n";

    $results = [
      'Build ID'       => $this->_buildRunId,
      null,
      'Tests Run'      => $this->_testsRun . '/' . $this->_totalTests,
      'Tests Passed'   => $this->_testsPass,
      'Tests Failed'   => $this->_testsFail,
      null,
      'Start Time'     => $buildRun->startTime->format('Y-m-d H:i:s'),
      'End Time'       => $buildRun->endTime->format('Y-m-d H:i:s'),
      'Total Duration' => $buildRun->startTime->diff($buildRun->endTime)
      ->format("%H:%I:%S"),
    ];

    foreach($results as $name => $value)
    {
      if($value !== null)
      {
        echo " " . str_pad($name, 20, ' ', STR_PAD_RIGHT) . ' : ';
        echo Shell::colourText($value, Shell::COLOUR_FOREGROUND_YELLOW);
      }
      echo "\n";
    }

    echo "\n$lineSplitter\n";
    echo "Final Result: ";

    if($buildRun->result !== BuildResult::PASS)
    {
      echo Shell::colourText("FAIL", Shell::COLOUR_FOREGROUND_RED);
    }
    else
    {
      echo Shell::colourText("PASS", Shell::COLOUR_FOREGROUND_GREEN);
    }

    echo "\n$lineSplitter\n\n";
  }

  protected function _downloadSourceCode(Source $source, $location)
  {
    $log = new BuildLog();
    if($this->verbose)
    {
      $log->enableOutput();
    }
    $log->setId($this->_buildRunId . '-source');
    $log->startTime = microtime(true);
    $log->exitCode  = -1;
    $log->saveChanges();

    switch($source->repositoryType)
    {
      case RepositoryProvider::GIT:
        $this->_outputStep("Cloning Repo");

        $cloneCommand = 'git clone -v';
        $cloneCommand .= " $source->fetchUrl";
        $cloneCommand .= " --branch " . $source->branch;
        $cloneCommand .= " $location";

        $process = new Process($cloneCommand);
        $process->setTimeout($this->timeout);
        $process->setIdleTimeout($this->idleTimeout);
        $process->run([$log, 'writeBuffer']);
        $log->exitCode = $process->getExitCode();
        break;
      default:
        throw new \Exception(
          "The repository type '" . $source->repositoryType . "' " .
          "is currently unsupported"
        );
    }

    $log->endTime = microtime(true);
    $log->saveChanges();

    return $log->exitCode;
  }

  protected function _applyPatch($patchId)
  {
    $log = new BuildLog();
    if($this->verbose)
    {
      $log->enableOutput();
    }
    $log->setId($this->_buildRunId . '-patch');
    $log->startTime = microtime(true);
    $log->exitCode  = -1;
    $log->saveChanges();

    $patch = new Patch($patchId);
    if($patch->exists())
    {
      $this->_outputStep("Applying Patch");

      $patchPath = $this->_buildPath . DS . $patch->filename;
      file_put_contents($patchPath, $patch->patch);

      $cwd = getcwd();
      chdir($this->_buildSourceDir);
      $runCommand = "git apply -v " . $patchPath . ' -p' . $patch->leadingSlashes;
      $process    = new Process($runCommand);
      $process->setTimeout($this->timeout);
      $process->setIdleTimeout($this->idleTimeout);
      try
      {
        $process->run([$log, 'writeBuffer']);
      }
      catch(\Exception $e)
      {
        Log::error(
          "Command Exception (" . $e->getCode() . ') ' . $e->getMessage()
        );
      }
      $log->exitCode = $process->getExitCode();
      chdir($cwd);
    }

    $log->endTime = microtime(true);
    $log->saveChanges();

    return $log->exitCode;
  }

  protected function _outputStep($message)
  {
    echo Shell::colourText("\n  $message\n", Shell::COLOUR_FOREGROUND_CYAN);
  }
}
