<?php

class SphinxQL {

  private $_sphinx;

  public function __construct(string $host, int $port) {

    $this->_sphinx = new PDO('mysql:host=' . $host . ';port=' . $port . ';charset=utf8', false, false, [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']);
    $this->_sphinx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->_sphinx->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
  }

  public function searchPeersTotal(string $keyword) {

    $query = $this->_sphinx->prepare('SELECT COUNT(*) AS `total` FROM `peer` WHERE MATCH(?)');

    $query->execute(
      [
        self::_match($keyword)
      ]
    );

    return $query->fetch()->total;
  }

  public function searchPeers(string $keyword, int $start, int $limit, int $maxMatches) {

    $query = $this->_sphinx->prepare("SELECT *

                                      FROM `peer`

                                      WHERE MATCH(?)

                                      ORDER BY WEIGHT() DESC

                                      LIMIT " . (int) ($start >= $maxMatches ? ($maxMatches > 0 ? $maxMatches - 1 : 0) : $start) . "," . (int) $limit . "

                                      OPTION `max_matches`=" . (int) ($maxMatches >= 1 ? $maxMatches : 1));

    $query->execute(
      [
        self::_match($keyword)
      ]
    );

    return $query->fetchAll();
  }

  private static function _match(string $keyword) : string {

    $keyword = trim($keyword);

    // Parse url request
    $peerAddress      = $keyword;
    $peerRemoteScheme = $keyword;
    $peerRemoteHost   = $keyword;
    $peerRemotePort   = $keyword;

    if ($url = Yggverse\Parser\Url::parse($keyword)) {

      $peerAddress      = $url->host->name   ? $url->host->name   : $keyword;
      $peerRemoteScheme = $url->host->scheme ? $url->host->scheme : $keyword;
      $peerRemoteHost   = $url->host->name   ? $url->host->name   : $keyword;
      $peerRemotePort   = $url->host->port   ? $url->host->port   : $keyword;
    }

    return sprintf(
      '@peerAddress "%s" | @peerKey "%s" | @peerCoordinatePort "%s" | @peerCoordinateRoute "%s" | @peerRemoteScheme "%s" | @peerRemoteHost "%s" | @peerRemotePort "%s"',
      preg_replace('/[^A-z0-9\:\[\]]/',   '', $peerAddress),
      preg_replace('/[^A-z0-9]/',         '', $keyword),
      preg_replace('/[^\d]/',             '', $keyword),
      preg_replace('/[^\d]/',             '', $keyword),
      preg_replace('/[^A-z]/',            '', $peerRemoteScheme),
      preg_replace('/[^A-z0-9\.\:\[\]]/', '', $peerRemoteHost),
      preg_replace('/[^\d]/',             '', $peerRemotePort),
    );
  }
}
