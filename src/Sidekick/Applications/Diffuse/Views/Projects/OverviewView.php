<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Applications\Diffuse\Views\Projects;

use Cubex\Mapper\Database\RecordCollection;
use Cubex\View\HtmlElement;
use Cubex\View\TemplatedViewModel;
use Sidekick\Components\Enums\ApprovalState;
use Sidekick\Components\Diffuse\Mappers\DeploymentConfig;
use Sidekick\Components\Diffuse\Mappers\Version;
use Sidekick\Components\Projects\Mappers\Project;

class OverviewView extends TemplatedViewModel
{
  protected $_project;
  protected $_versions;
  protected $_platforms;

  /**
   * @param Version[]|RecordCollection  $versions
   * @param Project                     $project
   * @param DeploymentConfig[]|RecordCollection $platforms
   */
  public function __construct(Project $project, $versions, $platforms)
  {
    $this->_project   = $project;
    $this->_versions  = $versions;
    $this->_platforms = $platforms;
  }

  public function project()
  {
    return $this->_project;
  }

  public function versions()
  {
    return $this->_versions;
  }

  public function platforms()
  {
    return $this->_platforms;
  }

  public function colourState($state)
  {
    $class = '';
    $text  = '-';

    switch($state)
    {
      case ApprovalState::APPROVED:
        $class = 'text-success';
        $text  = 'Approved';
        break;
    }
    return new HtmlElement('span', ['class' => $class], $text);
  }
}
