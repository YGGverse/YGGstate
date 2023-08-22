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

  public function getPeersTotal() : int {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT COUNT(*) AS `result` FROM `peer`');

    $query->execute();

    return $query->fetch()->result;
  }

  public function getPeer(int $peerId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peer` WHERE `peerId` = ? LIMIT 1');

    $query->execute([$peerId]);

    return $query->fetch();
  }

  public function getPeers() {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peer`');

    $query->execute();

    return $query->fetchAll();
  }

  // Port
  public function findPeerPortByValue(int $peerId, int $value) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peerPort` WHERE `peerId` = ? AND `value` = ? LIMIT 1');

    $query->execute([$peerId, $value]);

    return $query->fetch();
  }

  public function addPeerPort(int $peerId, int $value) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `peerPort` SET `peerId` = ?, `value` = ?');

    $query->execute([$peerId, $value]);

    return $this->_db->lastInsertId();
  }

  public function addPeerPortStatus(int $peerPortId, bool $value, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `peerPortStatus` SET `peerPortId` = ?, `value` = ?, `timeAdded` = ?');

    $query->execute([$peerPortId, $value ? "1" : "0", $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function findLastPeerPortStatusesByPeerId(int $peerId, int $limit = 5) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT  `peerPort`.`value` AS `port`,
                                          `peerPortStatus`.`value` AS `status`,
                                          `peerPortStatus`.`timeAdded`

                                          FROM `peerPort`
                                          JOIN `peerPortStatus` ON (`peerPortStatus`.`peerPortId` = `peerPort`.`peerPortId`)

                                          WHERE `peerPort`.`peerId` = ?

                                          ORDER BY `peerPortStatus`.`timeAdded` DESC

                                          LIMIT ' . (int) $limit);

    $query->execute([$peerId]);

    return $query->fetchAll();
  }

  public function findLastPeerPortStatusByPeerId(int $peerId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT  `peerPort`.`value` AS `port`,
                                          `peerPortStatus`.`value` AS `status`,
                                          `peerPortStatus`.`timeAdded`

                                          FROM `peerPort`
                                          JOIN `peerPortStatus` ON (`peerPortStatus`.`peerPortId` = `peerPort`.`peerPortId`)

                                          WHERE `peerPort`.`peerId` = ?

                                          ORDER BY `peerPortStatus`.`timeAdded` DESC

                                          LIMIT 1');

    $query->execute([$peerId]);

    return $query->fetch();
  }

  // Geo
  public function findGeo(mixed $geoCountryId, mixed $geoCityId, mixed $geoCoordinateId) { // int|null

    $this->_debug->query->select->total++;

    // @TODO
    // UNIQUE keys does not applying for the NULL values,
    // another problem that MySQL return no results for = NULL condition
    // if someone have better solution, feel free to PR this issue https://github.com/YGGverse/YGGstate/pulls
    $query = $this->_db->query("SELECT * FROM  `geo`
                                         WHERE `geoCountryId`    " . (empty($geoCountryId) ?    " IS NULL " : " = " . (int) $geoCountryId   ) . "
                                         AND   `geoCityId`       " . (empty($geoCityId) ?       " IS NULL " : " = " . (int) $geoCityId      ) . "
                                         AND   `geoCoordinateId` " . (empty($geoCoordinateId) ? " IS NULL " : " = " . (int) $geoCoordinateId) . "
                                         LIMIT 1");

    return $query->fetch();
  }

  public function addGeo(mixed $geoCountryId, mixed $geoCityId, mixed $geoCoordinateId) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `geo` SET `geoCountryId`    = ?,
                                                        `geoCityId`       = ?,
                                                        `geoCoordinateId` = ?');

    $query->execute([$geoCountryId, $geoCityId, $geoCoordinateId]);

    return $this->_db->lastInsertId();
  }

  public function findGeoCountryByIsoCode(string $isoCode) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `geoCountry` WHERE `isoCode` = ? LIMIT 1');

    $query->execute([$isoCode]);

    return $query->fetch();
  }

  public function addGeoCountry(string $isoCode, string $name) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `geoCountry` SET `isoCode` = ?, `name` = ?');

    $query->execute([$isoCode, $name]);

    return $this->_db->lastInsertId();
  }

  public function findGeoCityByName(string $name) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `geoCity` WHERE `name` = ? LIMIT 1');

    $query->execute([$name]);

    return $query->fetch();
  }

  public function addGeoCity(string $name) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `geoCity` SET `name` = ?');

    $query->execute([$name]);

    return $this->_db->lastInsertId();
  }

  public function findGeoCoordinate(float $latitude, float $longitude) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `geoCoordinate` WHERE `point` = POINT(?, ?) LIMIT 1');

    $query->execute([$latitude, $longitude]);

    return $query->fetch();
  }

  public function addGeoCoordinate(float $latitude, float $longitude) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `geoCoordinate` SET `point` = POINT(?, ?)');

    $query->execute([$latitude, $longitude]);

    return $this->_db->lastInsertId();
  }

  // Peer remote
  public function addPeerRemote(int $peerId, int $geoId, int $peerRemoteSchemeId, int $peerRemoteHostId, int $peerRemotePortId, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `peerRemote` SET  `peerId`             = ?,
                                                                `geoId`              = ?,
                                                                `peerRemoteSchemeId` = ?,
                                                                `peerRemoteHostId`   = ?,
                                                                `peerRemotePortId`   = ?,
                                                                `timeAdded`          = ?');

    $query->execute([$peerId, $geoId, $peerRemoteSchemeId, $peerRemoteHostId, $peerRemotePortId, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function findPeerRemote(int $peerId, int $geoId, int $peerRemoteSchemeId, int $peerRemoteHostId, int $peerRemotePortId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peerRemote` WHERE `peerId` = ? AND `geoId` = ? AND `peerRemoteSchemeId` = ? AND `peerRemoteHostId` = ? AND `peerRemotePortId` = ? LIMIT 1');

    $query->execute([$peerId, $geoId, $peerRemoteSchemeId, $peerRemoteHostId, $peerRemotePortId]);

    return $query->fetch();
  }

  public function findPeerRemoteScheme(string $name) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peerRemoteScheme` WHERE `name` = ? LIMIT 1');

    $query->execute([$name]);

    return $query->fetch();
  }

  public function addPeerRemoteScheme(string $name, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `peerRemoteScheme` SET `name` = ?, `timeAdded` = ?');

    $query->execute([$name, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function findPeerRemoteHost(string $name) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peerRemoteHost` WHERE `name` = ? LIMIT 1');

    $query->execute([$name]);

    return $query->fetch();
  }

  public function addPeerRemoteHost(string $name, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `peerRemoteHost` SET `name` = ?, `timeAdded` = ?');

    $query->execute([$name, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function findPeerRemotePort(mixed $name) { // int|null

    $this->_debug->query->select->total++;

    // @TODO
    // UNIQUE keys does not applying for the NULL values,
    // another problem that MySQL return no results for = NULL condition
    // if someone have better solution, feel free to PR this issue https://github.com/YGGverse/YGGstate/pulls
    if (empty($name)) {

      $query = $this->_db->query('SELECT * FROM `peerRemotePort` WHERE `name` IS NULL LIMIT 1');

    } else {

      $query = $this->_db->prepare('SELECT * FROM `peerRemotePort` WHERE `name` = ? LIMIT 1');

      $query->execute([$name]);
    }


    return $query->fetch();
  }

  public function addPeerRemotePort(mixed $name, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `peerRemotePort` SET `name` = ?, `timeAdded` = ?');

    $query->execute([$name, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  // Peer session
  public function addPeerSession(int $peerId, int $uptime, int $sent, int $received, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `peerSession` SET `peerId`      = ?,
                                                                `uptime`      = ?,
                                                                `sent`        = ?,
                                                                `received`    = ?,
                                                                `timeAdded`   = ?,
                                                                `timeUpdated` = ?');

    $query->execute([$peerId, $uptime, $sent, $received, $timeAdded, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function updatePeerSession(int $peerSessionId, int $uptime, int $sent, int $received, int $timeUpdated) {

    $this->_debug->query->update->total++;

    $query = $this->_db->prepare('UPDATE `peerSession` SET `uptime` = ?, `sent` = ?, `received` = ?, `timeUpdated` = ? WHERE `peerSessionId` = ? LIMIT 1');

    $query->execute([$uptime, $sent, $received, $timeUpdated, $peerSessionId]);

    return $query->rowCount();
  }

  public function findLastPeerSessionByPeerId(int $peerId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peerSession` WHERE `peerId` = ? ORDER BY `peerSessionId` DESC LIMIT 1');

    $query->execute([$peerId]);

    return $query->fetch();
  }

  public function getPeerSessionSentSum() {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT SUM(`sent`) AS `result` FROM `peerSession`');

    $query->execute();

    return $query->fetch()->result;
  }

  public function getPeerSessionReceivedSum() {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT SUM(`received`) AS `result` FROM `peerSession`');

    $query->execute();

    return $query->fetch()->result;
  }

  // Peer connection
  public function addPeerConnection(int $peerSessionId, int $peerRemoteId, int $peerCoordinateId, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `peerConnection` SET  `peerSessionId`    = ?,
                                                                    `peerRemoteId`     = ?,
                                                                    `peerCoordinateId` = ?,
                                                                    `timeAdded`        = ?');

    $query->execute([$peerSessionId, $peerRemoteId, $peerCoordinateId, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function findPeerConnection(int $peerSessionId, int $peerRemoteId, int $peerCoordinateId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM  `peerConnection`
                                           WHERE `peerSessionId` = ? AND `peerRemoteId` = ? AND `peerCoordinateId` = ?
                                           LIMIT 1');

    $query->execute([$peerSessionId, $peerRemoteId, $peerCoordinateId]);

    return $query->fetch();
  }

  // Peer coordinate
  // https://github.com/matrix-org/pinecone/wiki/2.-Spanning-Tree#coordinates

  public function addPeerCoordinate(int $peerId, int $port, int $timeAdded) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `peerCoordinate` SET `peerId`      = ?,
                                                                   `port`        = ?,
                                                                   `timeAdded`   = ?');

    $query->execute([$peerId, $port, $timeAdded]);

    return $this->_db->lastInsertId();
  }

  public function addPeerCoordinateRoute(int $peerCoordinateId, int $level, int $port) {

    $this->_debug->query->insert->total++;

    $query = $this->_db->prepare('INSERT INTO `peerCoordinateRoute` SET `peerCoordinateId` = ?,
                                                                        `level`            = ?,
                                                                        `port`             = ?');

    $query->execute([$peerCoordinateId, $level, $port]);

    return $this->_db->lastInsertId();
  }

  public function findLastPeerCoordinateByPeerId(int $peerId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peerCoordinate` WHERE `peerId` = ? ORDER BY `peerCoordinateId` DESC LIMIT 1');

    $query->execute([$peerId]);

    return $query->fetch();
  }

  public function findPeerCoordinateRouteByCoordinateId(int $peerCoordinateId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peerCoordinateRoute` WHERE `peerCoordinateId` = ? ORDER BY `level` ASC');

    $query->execute([$peerCoordinateId]);

    return $query->fetchAll();
  }

  // Analytics
  public function getPeerFirstByTimeAdded() {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT * FROM `peer` ORDER BY `timeAdded` ASC LIMIT 1');

    $query->execute();

    return $query->fetch();
  }

  public function findPeerSessionReceivedSumByTimeUpdated(int $timeFrom, int $timeTo, int $requestPeerId = 0) : int {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT SUM(`received`) AS `result`

    FROM  `peerSession`
    WHERE `timeUpdated` >= :timeFrom AND `timeUpdated` <= :timeTo' . ($requestPeerId > 0 ? ' AND `peerId` = ' . (int) $requestPeerId : false));

    $query->execute(
      [
        ':timeFrom' => $timeFrom,
        ':timeTo'   => $timeTo
      ]
    );

    return (int) $query->fetch()->result;
  }

  public function findPeerSessionSentSumByTimeUpdated(int $timeFrom, int $timeTo, int $requestPeerId = 0) : int {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT SUM(`sent`) AS `result`

    FROM `peerSession`
    WHERE `timeUpdated` >= :timeFrom AND `timeUpdated` <= :timeTo' . ($requestPeerId > 0 ? ' AND `peerId` = ' . (int) $requestPeerId : false));

    $query->execute(
      [
        ':timeFrom' => $timeFrom,
        ':timeTo'   => $timeTo
      ]
    );

    return (int) $query->fetch()->result;
  }

  public function findPeerTotalByTimeAdded(int $timeFrom, int $timeTo, int $requestPeerId = 0) : int {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT COUNT(DISTINCT `peerId`) AS `result`

    FROM `peerSession` WHERE `timeAdded` >= :timeFrom AND `timeAdded` <= :timeTo' .

    ($requestPeerId > 0 ? ' AND `peerId` = ' . (int) $requestPeerId : false));

    $query->execute(
      [
        ':timeFrom' => $timeFrom,
        ':timeTo'   => $timeTo
      ]
    );

    return (int) $query->fetch()->result;
  }

  public function findPeerTotalByTimeUpdated(int $timeFrom, int $timeTo, int $requestPeerId = 0) : int {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT COUNT(DISTINCT `peerId`) AS `result`

    FROM `peerSession` WHERE (`timeUpdated` >= :timeFrom AND `timeUpdated` <= :timeTo)' .

    ($requestPeerId > 0 ? ' AND `peerId` = ' . (int) $requestPeerId : false));

    $query->execute(
      [
        ':timeFrom' => $timeFrom,
        ':timeTo'   => $timeTo
      ]
    );

    return (int) $query->fetch()->result;
  }

  // Month page
  public function findPeers(int $start = 0, int $limit = 20, string $sort = 'timeOnline', string $order = 'DESC') {

    if (!in_array($sort,
      [
        'timeOnline',
        'uptimeAvg',
        'sentSum',
        'receivedSum',
        'address',
      ])) {
        $sort = 'timeOnline';
      }

    if (!in_array($order,
      [
        'ASC',
        'DESC',
      ])) {
        $order = 'DESC';
      }

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT

    `peer`.`peerId`,
    `peer`.`address`,

    (SELECT MAX(`peerSession`.`timeUpdated`) FROM `peerSession` WHERE `peerSession`.`peerId` = `peer`.`peerId`) AS `timeOnline`,
    (SELECT SUM(`peerSession`.`sent`) FROM `peerSession` WHERE `peerSession`.`peerId` = `peer`.`peerId`) AS `sentSum`,
    (SELECT SUM(`peerSession`.`received`) FROM `peerSession` WHERE `peerSession`.`peerId` = `peer`.`peerId`) AS `receivedSum`,
    (SELECT AVG(`peerSession`.`uptime`) FROM `peerSession` WHERE `peerSession`.`peerId` = `peer`.`peerId`) AS `uptimeAvg`

    FROM `peer`

    GROUP BY `peer`.`peerId`

    ORDER BY ' . $sort . ' ' . $order . '

    LIMIT ' . (int) $start . ', ' . (int) $limit);

    $query->execute();

    return $query->fetchAll();
  }

  // Peer page
  public function findPeerPeerConnections(int $peerId, int $start = 0, int $limit = 20, string $sort = 'timeOnline', string $order = 'DESC') {

    if (!in_array($sort,
      [
        'peerConnection.timeAdded',
      ])) {
        $sort = 'peerConnection.timeAdded';
      }

    if (!in_array($order,
      [
        'ASC',
        'DESC',
      ])) {
        $order = 'DESC';
      }

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare("SELECT

    MAX(`peerSession`.`timeUpdated`) AS `timeOnline`,

    `peerConnection`.`peerRemoteId`,
    `peerConnection`.`timeAdded`,

    `peerRemoteScheme`.`name` AS `remoteScheme`,
    `peerRemoteHost`.`name` AS `remoteHost`,
    `peerRemotePort`.`name` AS `remotePort`,

    `peerCoordinate`.`port` AS `connectionPort`,

    (
      SELECT GROUP_CONCAT(`port` SEPARATOR ' &rarr; ')
      FROM `peerCoordinateRoute`
      WHERE `peerCoordinateRoute`.`peerCoordinateId` = `peerConnection`.`peerCoordinateId`
      ORDER BY `peerCoordinateRoute`.`level` ASC
    ) AS `route`,

    CONCAT
    (
      IF  (`peerRemotePort`.`name` IS NOT NULL,
                CONCAT(`peerRemoteScheme`.`name`, '://', `peerRemoteHost`.`name`, ':', `peerRemotePort`.`name`),
                CONCAT(`peerRemoteScheme`.`name`, '://', `peerRemoteHost`.`name`)
          )
    ) AS `remote`,

    `geoCountry`.`isoCode` AS `geoCountryIsoCode`,
    `geoCountry`.`name` `geoCountryName`,
    `geoCity`.`name` AS `geoCityName`

    FROM `peerConnection`

    JOIN `peerSession` ON (`peerSession`.`peerSessionId` = `peerConnection`.`peerSessionId`)
    JOIN `peerRemote` ON (`peerRemote`.`peerRemoteId` = `peerConnection`.`peerRemoteId`)
    JOIN `peerCoordinate` ON (`peerCoordinate`.`peerCoordinateId` = `peerConnection`.`peerCoordinateId`)

    JOIN `peerRemoteScheme` ON (`peerRemoteScheme`.`peerRemoteSchemeId` = `peerRemote`.`peerRemoteSchemeId`)
    JOIN `peerRemoteHost` ON (`peerRemoteHost`.`peerRemoteHostId` = `peerRemote`.`peerRemoteHostId`)
    JOIN `peerRemotePort` ON (`peerRemotePort`.`peerRemotePortId` = `peerRemote`.`peerRemotePortId`)

    JOIN `peer` ON (`peer`.`peerId` = `peerRemote`.`peerId`)

    LEFT JOIN `geo` ON (`geo`.`geoId` = `peerRemote`.`geoId`)
    LEFT JOIN `geoCountry` ON (`geoCountry`.`geoCountryId` = `geo`.`geoCountryId`)
    LEFT JOIN `geoCity` ON (`geoCity`.`geoCityId` = `geo`.`geoCityId`)

    WHERE `peerRemote`.`peerId` = :peerId

    GROUP BY `peerConnection`.`peerConnectionId`

    ORDER BY " . $sort . " " . $order . "

    LIMIT " . (int) $start . ", " . (int) $limit);

    $query->execute(
      [
        ':peerId'   => $peerId,
      ]
    );

    return $query->fetchAll();
  }

  public function getPeerInfo(int $peerId) {

    $this->_debug->query->select->total++;

    $query = $this->_db->prepare('SELECT

    `peer`.`peerId`,
    `peer`.`key`,
    `peer`.`address`,
    `peer`.`timeAdded`,

    MAX(`peerSession`.`timeUpdated`) AS `timeOnline`,
    SUM(`peerSession`.`sent`) AS `sentSum`,
    SUM(`peerSession`.`received`) AS `receivedSum`,
    ROUND(AVG(`peerSession`.`uptime`)) AS `uptimeAvg`,

   (SELECT COUNT(*) FROM `peerSession` WHERE `peerSession`.`peerId` = `peer`.`peerId`) AS `sessionTotal`,
   (SELECT COUNT(*) FROM `peerRemote` WHERE `peerRemote`.`peerId` = `peer`.`peerId`) AS `remoteTotal`,
   (SELECT COUNT(*) FROM `peerCoordinate` WHERE `peerCoordinate`.`peerId` = `peer`.`peerId`) AS `coordinateTotal`

    FROM `peer`
    JOIN `peerSession` ON (`peerSession`.`peerId` = `peer`.`peerId`)

    WHERE `peer`.`peerId` = ?

    GROUP BY `peer`.`peerId`

    LIMIT 1');

    $query->execute([$peerId]);

    return $query->fetch();
  }

  // Other
  public function optimize() {

    $this->_db->query('OPTIMIZE TABLE `peer`');
    $this->_db->query('OPTIMIZE TABLE `peerRemote`');
    $this->_db->query('OPTIMIZE TABLE `peerCoordinate`');
    $this->_db->query('OPTIMIZE TABLE `peerCoordinateRoute`');
  }
}
