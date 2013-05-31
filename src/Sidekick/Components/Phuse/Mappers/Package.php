<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Components\Phuse\Mappers;

use Cubex\Mapper\Database\RecordMapper;

class Package extends RecordMapper
{
  public $name;
  public $description;
  public $author;

  public function releases()
  {
    return $this->hasMany(new Release());
  }
}
