<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Components\Fortify\Mappers;

use Cubex\Data\Attribute\Attribute;
use Cubex\Data\Validator\Validator;
use Cubex\Mapper\Database\RecordMapper;
use Sidekick\Components\Fortify\Enums\FileSet;

class Command extends RecordMapper
{
  /**
   * @length 255
   */
  public $command;
  /**
   * @length 255
   */
  public $args;
  public $name;
  public $description;
  public $reportNamespace;

  public $causeBuildFailure = true;

  /**
   * @enumclass \Sidekick\Components\Fortify\Enums\FileSet
   */
  public $fileSet = FileSet::NONE;
  public $fileSetDirectory;
  public $filePattern;

  public $successExitCodes = [0];

  protected function _configure()
  {
    $this->_attribute("successExitCodes")->setSerializer(
      Attribute::SERIALIZATION_JSON
    );
    $this->_attribute("args")->setSerializer(Attribute::SERIALIZATION_JSON);
    $this->_attribute("name")->addValidator(Validator::VALIDATE_NOTEMPTY);
    $this->_attribute("command")->addValidator(Validator::VALIDATE_NOTEMPTY);
    $this->_attribute("fileset")->setRequired(true);
  }

  public function fileSets()
  {
    return new FileSet();
  }
}
