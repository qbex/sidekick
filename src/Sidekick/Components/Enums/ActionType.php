<?php
/**
 * @author  brooke.bryan
 */

namespace Sidekick\Components\Enums;

use Cubex\Type\Enum;

class ActionType extends Enum
{
  const __default = 'comment';
  const COMMENT   = 'comment';
  const APPROVE   = 'approve';
  const REJECT    = 'reject';
}
