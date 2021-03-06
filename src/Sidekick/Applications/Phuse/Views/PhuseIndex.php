<?php
/**
 * Created by JetBrains PhpStorm.
 * User: oke.ugwu
 * Date: 31/05/13
 * Time: 11:42
 * To change this template use File | Settings | File Templates.
 */

namespace Sidekick\Applications\Phuse\Views;

use Cubex\View\TemplatedViewModel;

class PhuseIndex extends TemplatedViewModel
{
  public $baseUri;

  public function __construct($baseUri = null)
  {
    if($baseUri === null)
    {
      $baseUri = url("%d.%t");
    }
    $this->baseUri = $baseUri;
  }
}
