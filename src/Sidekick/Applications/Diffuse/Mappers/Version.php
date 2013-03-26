<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Applications\Diffuse\Mappers;

use Cubex\Mapper\Database\RecordMapper;

class Version extends RecordMapper
{
  public $projectId;
  public $repoId;
  /**
   * @datatype tinyint
   */
  public $major;
  /**
   * @datatype smallint
   */
  public $minor;
  /**
   * @datatype smallint
   */
  public $build;
  public $fromCommitHash;
  public $toCommitHash;
  public $releaseDate;
  public $stageReleaseDate;
  public $changeLog;
  /**
   * @enumclass \Sidekick\Applications\Diffuse\Mappers\VersionState
   */
  public $versionState;
}