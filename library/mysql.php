<?php

class MySQL {

  private PDO $_db;

  private object $_debug;

  public function __construct(string $host, int $port, string $database, string $username, string $password) {

    $this->_db = new PDO('mysql:dbname=' . $database . ';host=' . $host . ';port=' . $port . ';charset=utf8', $username, $password, [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']);
    $this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    $this->_db->setAttribute(PDO::ATTR_TIMEOUT, 600);

    $this->_debug = (object)
    [
      'query' => (object)
      [
        'select' => (object)
        [
          'total' => 0
        ],
        'insert' => (object)
        [
          'total' => 0
        ],
        'update' => (object)
        [
          'total' => 0
        ],
        'delete' => (object)
        [
          'total' => 0
        ],
      ]
    ];
  }

  // Tools
  public function beginTransaction() {

    $this->_db->beginTransaction();
  }

  public function commit() {

    $this->_db->commit();
  }

  public function rollBack() {

    $this->_db->rollBack();
  }

  public function getDebug() {

    return $this->_debug;
  }

  // Peer
  public function addPeer(string $address, string $key, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `peer` SET `address` = ?, `key` = ?, `timeAdded` = ?');

    $query->execute([$address, $key, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function findPeer(string $address) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peer` WHERE `address` = ? LIMIT 1');

    $query->execute([$address]);

    return $query->fetch();
  }

  public function getPeers() {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peer`');

    $query->execute();

    return $query->fetchAll();
  }

  // Peer remote
  public function addPeerRemote(int $peerId, string $scheme, string $host, int $port, int $received, int $sent, float $uptime, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `peerRemote` SET  `peerId`    = ?,
                                                                `scheme`    = ?,
                                                                `host`      = ?,
                                                                `port`      = ?,
                                                                `received`  = ?,
                                                                `sent`      = ?,
                                                                `uptime`    = ?,
                                                                `timeAdded` = ?');

    $query->execute([$peerId, $scheme, $host, $port, $received, $sent, $uptime, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function updatePeerRemoteReceived(int $peerRemoteId, int $received, int $timeUpdated) {

    $this->_debug->query->update->total++;

    $query = $this->_db->prepare('UPDATE `peerRemote` SET `received` = ?, `timeUpdated` = ? WHERE `peerRemoteId` = ? LIMIT 1');

    $query->execute([$received, $timeUpdated, $peerRemoteId]);

    return $query->rowCount();
  }

  public function updatePeerRemoteSent(int $peerRemoteId, int $sent, int $timeUpdated) {

    $this->_debug->query->update->total++;

    $query = $this->_db->prepare('UPDATE `peerRemote` SET `sent` = ?, `timeUpdated` = ? WHERE `peerRemoteId` = ? LIMIT 1');

    $query->execute([$sent, $timeUpdated, $peerRemoteId]);

    return $query->rowCount();
  }

  public function updatePeerRemoteUptime(int $peerRemoteId, float $uptime, int $timeUpdated) {

    $this->_debug->query->update->total++;

    $query = $this->_db->prepare('UPDATE `peerRemote` SET `uptime` = ?, `timeUpdated` = ? WHERE `peerRemoteId` = ? LIMIT 1');

    $query->execute([$uptime, $timeUpdated, $peerRemoteId]);

    return $query->rowCount();
  }

  public function findPeerRemote(int $peerId, string $scheme, string $host, int $port) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peerRemote` WHERE `peerId` = ? AND `scheme` = ? AND `host` = ? AND `port` = ? LIMIT 1');

    $query->execute([$peerId, $scheme, $host, $port]);

    return $query->fetch();
  }

  // Other
  public function optimize() {

    $this->_db->query('OPTIMIZE TABLE `peer`');
    $this->_db->query('OPTIMIZE TABLE `peerRemote`');
  }
}
