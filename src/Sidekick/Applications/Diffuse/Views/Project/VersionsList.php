<?php
/**
 * Author: oke.ugwu
 * Date: 02/07/13 12:13
 */

namespace Sidekick\Applications\Diffuse\Views\Project;

use Cubex\Form\Form;
use Cubex\Form\OptionBuilder;
use Cubex\View\TemplatedViewModel;
use Sidekick\Components\Diffuse\Enums\VersionNumberType;
use Sidekick\Components\Diffuse\Enums\VersionState;
use Sidekick\Components\Diffuse\Enums\VersionType;
use Sidekick\Components\Diffuse\Mappers\Platform;
use Sidekick\Components\Diffuse\Mappers\PlatformVersionState;

class VersionsList extends TemplatedViewModel
{
  protected $_versions;
  protected $_projectId;
  protected $_createVersionForm;

  public function __construct($versions, $projectId)
  {
    $this->_versions  = $versions;
    $this->_projectId = $projectId;
  }

  /**
   * @return \Sidekick\Components\Diffuse\Mappers\Version[]
   */
  public function getVersions()
  {
    return $this->_versions;
  }

  public function createVersionForm()
  {
    if($this->_createVersionForm === null)
    {
      $this->_createVersionForm = new Form('createVersion', 'create-version');
      $this->_createVersionForm->addAttribute('class', 'form-inline');
      $this->_createVersionForm->setDefaultElementTemplate('{{input}}');
      $this->_createVersionForm->addHiddenElement(
        'projectId',
        $this->_projectId
      );
      $this->_createVersionForm->addSelectElement(
        'type',
        (new OptionBuilder(new VersionType))->getOptions()
      );

      $this->_createVersionForm->addSelectElement(
        'versionNumberType',
        (new OptionBuilder(new VersionNumberType()))->getOptions()
      );

      $this->_createVersionForm->addSubmitElement('Create');
      $this->_createVersionForm->getElement('submit')->addAttribute(
        'class',
        'btn btn-primary'
      );
    }
    return $this->_createVersionForm;
  }

  public function getStates($versionID)
  {
    $states = PlatformVersionState::collection()->loadWhere(
      [
      "version_id" => $versionID
      ]
    );
    return $states;
  }

  public function stateClass($state)
  {
    switch($state)
    {
      case VersionState::APPROVED:
        return "success";
      case VersionState::REJECTED:
        return "error";
      case VersionState::UNKNOWN:
        return "warning";
      default:
        return "info";
    }
  }

  public function getPlatformName($id)
  {
    $platform=Platform::collection()->loadOneWhere(["id"=>$id]);
    return ($platform==null) ? "" : $platform->name;
  }
}