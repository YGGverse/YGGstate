<?php

class Access
{
  public static function address(array $list)
  {
    return isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], $list);
  }
}
