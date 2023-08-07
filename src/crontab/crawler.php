<?php

// Stop crawler on cli running
$semaphore = sem_get(crc32('yggstate.cli.yggstate'), 1);

if (false === sem_acquire($semaphore, true)) {

  exit (PHP_EOL . 'yggstate.cli.yggstate process running in another thread.' . PHP_EOL);
}

// Lock multi-thread execution
$semaphore = sem_get(crc32('yggstate.crontab.crawler'), 1);

if (false === sem_acquire($semaphore, true)) {

  exit (PHP_EOL . 'yggstate.crontab.crawler process locked by another thread.' . PHP_EOL);
}

// Load system dependencies
require_once(__DIR__ . '/../config/app.php');
require_once(__DIR__ . '/../library/mysql.php');
require_once __DIR__ . '/../../vendor/autoload.php';

// Check disk quota
if (CRAWL_STOP_DISK_QUOTA_MB_LEFT > disk_free_space('/') / 1000000) {

  exit (PHP_EOL . 'Disk quota reached.' . PHP_EOL);
}

// Init Debug
$debug = [
  'time' => [
    'ISO8601' => date('c'),
    'total'   => microtime(true),
  ],
  'yggdrasil' => [
    'peer' => [
      'total' => [
        'online' => 0,
        'insert' => 0,
      ],
      'remote' => [
        'total' => [
          'insert' => 0,
          'update' => 0,
        ]
      ],
      'coordinate' => [
        'total' => [
          'insert' => 0,
        ],
        'route' => [
          'total' => [
            'insert' => 0,
            'delete' => 0,
          ]
        ]
      ],
    ]
  ]
];

// Connect database
try {

  $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// Collect connected peers
if ($connectedPeers = Yggverse\Yggdrasilctl\Yggdrasil::getPeers()) {

  foreach ($connectedPeers as $connectedPeerAddress => $connectedPeerInfo) {

    try {

      $db->beginTransaction();

      // Init peer
      if ($dbPeer = $db->findPeer($connectedPeerAddress)) {

        $dbPeerId = $dbPeer->peerId;

      } else {

        if ($dbPeerId = $db->addPeer($connectedPeerAddress, $connectedPeerInfo->key, time())) {

          $debug['yggdrasil']['peer']['total']['insert']++;
        }
      }

      // Init peer remote
      if ($connectedPeerRemoteUrl = Yggverse\Parser\Url::parse($connectedPeerInfo->remote)) {

        if ($dbPeerRemote = $db->findPeerRemote($dbPeerId,
                                                $connectedPeerRemoteUrl->host->scheme,
                                                $connectedPeerRemoteUrl->host->name,
                                                $connectedPeerRemoteUrl->host->port)) {

          // Update connection stats
          if ($dbPeerRemote->received < $connectedPeerInfo->bytes_recvd) {

            $debug['yggdrasil']['peer']['remote']['total']['update'] +=
            $db->updatePeerRemoteReceived($dbPeerRemote->peerRemoteId, $connectedPeerInfo->bytes_recvd, time());
          }

          if ($dbPeerRemote->sent < $connectedPeerInfo->bytes_sent) {

            $debug['yggdrasil']['peer']['remote']['total']['update'] +=
            $db->updatePeerRemoteSent($dbPeerRemote->peerRemoteId, $connectedPeerInfo->bytes_sent, time());
          }

          if ($dbPeerRemote->uptime < $connectedPeerInfo->uptime) {

            $debug['yggdrasil']['peer']['remote']['total']['update'] +=
            $db->updatePeerRemoteUptime($dbPeerRemote->peerRemoteId, $connectedPeerInfo->uptime, time());
          }

        } else {

          if ($peerRemoteId = $db->addPeerRemote($dbPeerId,
                                                 $connectedPeerRemoteUrl->host->scheme,
                                                 $connectedPeerRemoteUrl->host->name,
                                                 $connectedPeerRemoteUrl->host->port,
                                                 $connectedPeerInfo->bytes_recvd,
                                                 $connectedPeerInfo->bytes_sent,
                                                 $connectedPeerInfo->uptime,
                                                 time())) {

            $debug['yggdrasil']['peer']['remote']['total']['insert']++;
          }
        }

        // Init peer coordinate
        if ($dbPeerCoordinate = $db->getLastCoordinate($dbPeerId)) {

          $peerCoordinateId = $dbPeerCoordinate->peerCoordinateId;

          // Create new peer coordinate on port change
          if ($dbPeerCoordinate->port !== $connectedPeerInfo->port) {

            if ($peerCoordinateId = $db->addPeerCoordinate($dbPeerId, $connectedPeerInfo->port, time())) {

              $debug['yggdrasil']['peer']['coordinate']['total']['insert']++;
            }
          }

        } else {

          if ($peerCoordinateId = $db->addPeerCoordinate($dbPeerId, $connectedPeerInfo->port, time())) {

            $debug['yggdrasil']['peer']['coordinate']['total']['insert']++;
          }
        }

        // Init peer coordinate routing
        $localPeerCoordinateRoute = [];
        foreach ($db->getPeerCoordinateRoute($peerCoordinateId) as $dbPeerCoordinateRoute) {

          $localPeerCoordinateRoute[$dbPeerCoordinateRoute->level] = $dbPeerCoordinateRoute->port;
        }

        // Compare remote and local routes to prevent extra writing operations
        if ($localPeerCoordinateRoute !== $connectedPeerInfo->coords) {

          $debug['yggdrasil']['peer']['coordinate']['route']['total']['delete'] +=
          $db->flushPeerCoordinateRoute($peerCoordinateId);

          foreach ($connectedPeerInfo->coords as $level => $port) {

            if ($db->addPeerCoordinateRoute($peerCoordinateId, $level, $port)) {

              $debug['yggdrasil']['peer']['coordinate']['route']['total']['insert']++;
            }
          }
        }
      }

      $debug['yggdrasil']['peer']['total']['online']++;

      $db->commit();

    } catch(Exception $e) {

      $db->rollBack();

      var_dump($e);

      break;
    }
  }
}

// Debug output
$debug['time']['total'] = microtime(true) - $debug['time']['total'];

print_r(
  array_merge($debug, [
    'db' => [
      'total' => [
        'select' => $db->getDebug()->query->select->total,
        'insert' => $db->getDebug()->query->insert->total,
        'update' => $db->getDebug()->query->update->total,
        'delete' => $db->getDebug()->query->delete->total,
      ]
    ]
  ])
);