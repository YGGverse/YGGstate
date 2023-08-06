<?php

class Yggdrasil {

  private static function _exec(string $cmd) : mixed {

    if (false !== exec('yggdrasilctl -json getPeers', $output)) {

      $rows = [];

      foreach($output as $row){

        $rows[] = $row;
      }

      if ($result = @json_decode(implode(PHP_EOL, $rows))) {

        return $result;
      }
    }

    return false;
  }

  public static function getPeers() : mixed {

    if (false === $result = self::_exec('yggdrasilctl -json getPeers')) {

      return false;
    }

    if (empty($result->peers)) {

      return false;
    }

    foreach ((object) $result->peers as $peer) {

      switch (false) {

        case  isset($peer->bytes_recvd):
        case  isset($peer->bytes_sent):
        case  isset($peer->remote):
        case  isset($peer->port):
        case  isset($peer->key):
        case  isset($peer->uptime):
        case !empty($peer->coords):

          return false;
      }
    }

    return $result->peers;
  }
}