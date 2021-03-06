<?php
/**
 * Created by JetBrains PhpStorm.
 * User: oke.ugwu
 * Date: 07/06/13
 * Time: 09:11
 * To change this template use File | Settings | File Templates.
 */

namespace Sidekick\Applications\Fortify\Views;

use Cubex\Form\Form;
use Cubex\View\TemplatedViewModel;

class AddBuildCommandsForm extends TemplatedViewModel
{
  protected $_buildId;
  protected $_allCommands;
  protected $_unAssignedCommits;
  protected $_form;

  public function __construct($buildId, $unAssignedCommits, $allCommands)
  {
    $this->_buildId           = $buildId;
    $this->_allCommands       = $allCommands;
    $this->_unAssignedCommits = $unAssignedCommits;
  }

  public function form()
  {
    if($this->_form === null)
    {
      $this->_form = new Form(
        'addBuildCommand', $this->appBaseUri() . '/buildCommands/create'
      );
      $this->_form->setDefaultElementTemplate('{{input}}');
      $this->_form->addHiddenElement('buildId', $this->_buildId);
      $this->_form->addSelectElement('commandId', $this->_unAssignedCommits);

      $this->_form->addCheckboxElements(
        'dependencies',
        '',
        $this->_allCommands
      )
      ->setElementTemplate('<div class="dep-checkboxes">{{input}}</div>');
      $this->_form->addSubmitElement('Add Command', 'add');
    }

    return $this->_form;
  }
}
