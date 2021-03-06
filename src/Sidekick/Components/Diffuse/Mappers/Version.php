<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Components\Diffuse\Mappers;

use Cubex\Mapper\Database\RecordMapper;
use Sidekick\Components\Enums\ApprovalState;
use Sidekick\Components\Diffuse\Enums\VersionType;
use Sidekick\Components\Fortify\Mappers\BuildRun;
use Sidekick\Components\Projects\Mappers\Project;
use Sidekick\Components\Repository\Mappers\Source;

/**
 * Class Version
 * @link http://semver.org
 */
class Version extends RecordMapper
{
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
  /**
   * e.g. 2 for RC2
   * @datatype smallint
   */
  public $revision;
  /**
   * @enumclass \Sidekick\Components\Diffuse\Enums\VersionType
   */
  public $type = VersionType::STANDARD;

  /**
   * initiating build ID
   */
  public $buildId;
  /**
   * related project
   */
  public $projectId;
  /**
   * related repository
   */
  public $repoId;

  public $fromCommitHash;
  public $toCommitHash;

  /**
   * @datatype mediumtext
   */
  public $changeLog;

  /**
   * @enumclass \Sidekick\Components\Enums\ApprovalState
   */
  public $versionState = ApprovalState::UNKNOWN;

  public function format()
  {
    $formatted = implode(
      ".",
      [(int)$this->major, (int)$this->minor, (int)$this->build]
    );
    if($this->revision > 0 && (string)$this->type !== VersionType::STANDARD)
    {
      $formatted .= '-' . $this->type . $this->revision;
    }
    return $formatted;
  }

  public function actions()
  {
    return $this->hasMany(new Action());
  }

  public function deployments()
  {
    return $this->hasMany(new Deployment());
  }

  /**
   * @return Project
   */
  public function project()
  {
    return $this->belongsTo(new Project());
  }

  /**
   * @return Source
   */
  public function repo()
  {
    return $this->belongsTo(new Source(), "repo_id");
  }

  /**
   * @return BuildRun
   */
  public function fortifyBuild()
  {
    return $this->belongsTo(new BuildRun(), "id", "build_id");
  }

  public function platformStates()
  {
    return $this->hasMany(new PlatformVersionState());
  }
}
