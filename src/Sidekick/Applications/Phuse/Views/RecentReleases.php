<?php
/**
 * Author: oke.ugwu
 * Date: 16/07/13 17:47
 */

namespace Sidekick\Applications\Phuse\Views;

use Sidekick\Applications\BaseApp\Views\MapperList;

class RecentReleases extends MapperList
{
  public $releases;

  public function __construct($releases)
  {
    $this->releases = $releases;
  }
}
