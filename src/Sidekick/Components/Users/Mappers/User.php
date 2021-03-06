<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Components\Users\Mappers;

use Cubex\Data\Validator\Validator;
use Cubex\Mapper\Database\RecordMapper;
use Sidekick\Components\Users\Enums\UserRole;

class User extends RecordMapper
{
  public $username;
  public $email;
  public $password;
  public $displayName;
  public $phoneNumber;
  /**
   * @enumclass \Sidekick\Components\Users\Enums\UserRole
   */
  public $userRole;

  public function userRoles()
  {
    return new UserRole();
  }
}
