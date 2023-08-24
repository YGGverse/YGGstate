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
      'session' => [
        'total' => [
          'insert' => 0,
          'update' => 0,
        ]
      ],
      'remote' => [
        'total' => [
          'insert' => 0,
        ],
        'scheme' => [
          'total' => [
            'insert' => 0,
          ]
        ],
        'host' => [
          'total' => [
            'insert' => 0,
          ]
        ],
        'port' => [
          'total' => [
            'insert' => 0,
          ]
        ]
      ],
      'coordinate' => [
        'total' => [
          'insert' => 0,
        ],
        'route' => [
          'total' => [
            'insert' => 0,
          ]
        ]
      ],
      'connection' => [
        'total' => [
          'insert' => 0,
        ],
      ],
    ],
    'geo' => [
      'total' => [
        'insert' => 0,
      ],
      'country' => [
        'total' => [
          'insert' => 0,
        ],
      ],
      'city' => [
        'total' => [
          'insert' => 0,
        ],
      ],
      'coordinate' => [
        'total' => [
          'insert' => 0,
        ],
      ],
    ]
  ]
];

// Connect DB
try {

  $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// GeoIp2
try {

  $geoIp2Country = new GeoIp2\Database\Reader(GEOIP_LITE_2_COUNTRY_DB);
  $geoIp2City    = new GeoIp2\Database\Reader(GEOIP_LITE_2_CITY_DB);

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// Delete cache
@unlink(__DIR__ . '/../public/api/peers.json');
@unlink(__DIR__ . '/../public/api/trackers.json');

// Update peers
if ($connectedPeers = Yggverse\Yggdrasilctl\Yggdrasil::getPeers()) {

  if ($handle = fopen(__DIR__ . '/../public/api/peers.json', 'w+')) {
                fwrite($handle, json_encode($connectedPeers));
                fclose($handle);
  }

} else {

  exit;
}

// Update trackers
if (API_PEERS) {

  if ($handle = fopen(__DIR__ . '/../public/api/trackers.json', 'w+')) {
                fwrite($handle, json_encode(API_PEERS));
                fclose($handle);
  }

} else {

  exit;
}

// @TODO merge peers data from remote trackers

// Collect connected peers
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

    // Init peer session
    if ($dbLastPeerSession = $db->findLastPeerSessionByPeerId($dbPeerId)) {

      $dbPeerSessionId = $dbLastPeerSession->peerSessionId;

      // If remote session uptime < than stored, register new one
      if ($connectedPeerInfo->uptime < $dbLastPeerSession->uptime) {

        if ($dbPeerSessionId = $db->addPeerSession($dbPeerId,
                                                  round($connectedPeerInfo->uptime),
                                                  $connectedPeerInfo->bytes_sent,
                                                  $connectedPeerInfo->bytes_recvd,
                                                  time())) {

          $debug['yggdrasil']['peer']['session']['total']['insert']++;
        }

      } else {

        $debug['yggdrasil']['peer']['session']['total']['update'] +=
        $db->updatePeerSession($dbLastPeerSession->peerSessionId,
                              round($connectedPeerInfo->uptime),
                              $connectedPeerInfo->bytes_sent,
                              $connectedPeerInfo->bytes_recvd,
                              time());
      }

    } else {

      if ($dbPeerSessionId = $db->addPeerSession($dbPeerId,
                                                round($connectedPeerInfo->uptime),
                                                $connectedPeerInfo->bytes_sent,
                                                $connectedPeerInfo->bytes_recvd,
                                                time())) {

        $debug['yggdrasil']['peer']['session']['total']['insert']++;
      }
    }

    // Init peer coordinate
    if ($dbPeerCoordinate = $db->findLastPeerCoordinateByPeerId($dbPeerId)) {

      $dbPeerCoordinateId = $dbPeerCoordinate->peerCoordinateId;

      // Peer have changed it port, init new coordinate
      if ($dbPeerCoordinate->port != $connectedPeerInfo->port) {

        if ($dbPeerCoordinateId = $db->addPeerCoordinate($dbPeerId, $connectedPeerInfo->port, time())) {

          $debug['yggdrasil']['peer']['coordinate']['total']['insert']++;
        }
      }

    } else {

      if ($dbPeerCoordinateId = $db->addPeerCoordinate($dbPeerId, $connectedPeerInfo->port, time())) {

        $debug['yggdrasil']['peer']['coordinate']['total']['insert']++;
      }
    }

    // Init peer coordinate route
    $dbCoords = [];
    foreach ($db->findPeerCoordinateRouteByCoordinateId($dbPeerCoordinateId) as $dbPeerCoordinateRoute) {
      $dbCoords[$dbPeerCoordinateRoute->level] = $dbPeerCoordinateRoute->port;
    }

    // Compare remote / local route, create new on changed
    if ($dbCoords !== $connectedPeerInfo->coords) {

      if ($dbPeerCoordinateId = $db->addPeerCoordinate($dbPeerId, $connectedPeerInfo->port, time())) {

        $debug['yggdrasil']['peer']['coordinate']['total']['insert']++;
      }

      foreach ($connectedPeerInfo->coords as $level => $port) {

        if ($db->addPeerCoordinateRoute($dbPeerCoordinateId, $level, $port)) {

          $debug['yggdrasil']['peer']['coordinate']['route']['total']['insert']++;
        }
      }
    }

    // Init peer remote
    if ($connectedPeerRemoteUrl = Yggverse\Parser\Url::parse($connectedPeerInfo->remote)) {

      // Init peer scheme
      if ($dbPeerRemoteScheme = $db->findPeerRemoteScheme($connectedPeerRemoteUrl->host->scheme)) {

        $dbPeerRemoteSchemeId = $dbPeerRemoteScheme->peerRemoteSchemeId;

      } else {

        if ($dbPeerRemoteSchemeId = $db->addPeerRemoteScheme($connectedPeerRemoteUrl->host->scheme, time())) {

          $debug['yggdrasil']['peer']['remote']['scheme']['total']['insert']++;
        }
      }

      // Init peer host
      if ($dbPeerRemoteHost = $db->findPeerRemoteHost($connectedPeerRemoteUrl->host->name)) {

        $dbPeerRemoteHostId = $dbPeerRemoteHost->peerRemoteHostId;

      } else {

        if ($dbPeerRemoteHostId = $db->addPeerRemoteHost($connectedPeerRemoteUrl->host->name, time())) {

          $debug['yggdrasil']['peer']['remote']['host']['total']['insert']++;
        }
      }

      // Init peer port
      if ($dbPeerRemotePort = $db->findPeerRemotePort($connectedPeerRemoteUrl->host->port)) {

        $dbPeerRemotePortId = $dbPeerRemotePort->peerRemotePortId;

      } else {

        if ($dbPeerRemotePortId = $db->addPeerRemotePort($connectedPeerRemoteUrl->host->port, time())) {

          $debug['yggdrasil']['peer']['remote']['port']['total']['insert']++;
        }
      }

      // Init geo data

      /// Country
      $countryIsoCode = $geoIp2Country->country($connectedPeerRemoteUrl->host->name)->country->isoCode;
      $countryName    = $geoIp2Country->country($connectedPeerRemoteUrl->host->name)->country->name;

      $dbGeoCountryId = null;

      if (!empty($countryIsoCode) && !empty($countryName)) {

        if ($dbGeoCountry = $db->findGeoCountryByIsoCode($countryIsoCode)) {

          $dbGeoCountryId = $dbGeoCountry->geoCountryId;

        } else {

          if ($dbGeoCountryId = $db->addGeoCountry($countryIsoCode, $countryName)) {

            $debug['yggdrasil']['geo']['country']['total']['insert']++;
          }
        }
      }

      /// City
      $cityName = $geoIp2City->city($connectedPeerRemoteUrl->host->name)->city->name;

      $dbGeoCityId = null;

      if (!empty($cityName)) {

        if ($dbGeoCity = $db->findGeoCityByName($cityName)) {

          $dbGeoCityId = $dbGeoCity->geoCityId;

        } else {

          if ($dbGeoCityId = $db->addGeoCity($cityName)) {

            $debug['yggdrasil']['geo']['city']['total']['insert']++;
          }
        }
      }

      /// Coordinate
      $latitude  = $geoIp2City->city($connectedPeerRemoteUrl->host->name)->location->latitude;
      $longitude = $geoIp2City->city($connectedPeerRemoteUrl->host->name)->location->longitude;

      $dbGeoCoordinateId = null;

      if (!empty($latitude) && !empty($longitude)) {

        if ($dbGeoCoordinate = $db->findGeoCoordinate($geoIp2City->city($connectedPeerRemoteUrl->host->name)->location->latitude,
                                                      $geoIp2City->city($connectedPeerRemoteUrl->host->name)->location->longitude)) {

          $dbGeoCoordinateId = $dbGeoCoordinate->geoCoordinateId;

        } else {

          if ($dbGeoCoordinateId = $db->addGeoCoordinate($geoIp2City->city($connectedPeerRemoteUrl->host->name)->location->latitude,
                                                        $geoIp2City->city($connectedPeerRemoteUrl->host->name)->location->longitude)) {

            $debug['yggdrasil']['geo']['coordinate']['total']['insert']++;
          }
        }
      }

      /// Geo
      $dbGeoId = null;

      if ($dbGeo = $db->findGeo($dbGeoCountryId, $dbGeoCityId, $dbGeoCoordinateId)) {

        $dbGeoId = $dbGeo->geoId;

      } else {

        if ($dbGeoId = $db->addGeo($dbGeoCountryId, $dbGeoCityId, $dbGeoCoordinateId)) {

          $debug['yggdrasil']['geo']['total']['insert']++;
        }
      }

      // Init peer remote
      if ($dbPeerRemote = $db->findPeerRemote($dbPeerId,
                                              $dbGeoId,
                                              $dbPeerRemoteSchemeId,
                                              $dbPeerRemoteHostId,
                                              $dbPeerRemotePortId)) {

        $dbPeerRemoteId = $dbPeerRemote->peerRemoteId;

      } else {

        if ($dbPeerRemoteId = $db->addPeerRemote($dbPeerId,
                                                $dbGeoId,
                                                $dbPeerRemoteSchemeId,
                                                $dbPeerRemoteHostId,
                                                $dbPeerRemotePortId,
                                                time())) {

          $debug['yggdrasil']['peer']['remote']['total']['insert']++;
        }
      }

    // If something went wrong with URL parse, skip next operations for this peer
    } else {

      $db->rollBack();

      continue;
    }

    // Init peer connection
    if (!$db->findPeerConnection($dbPeerSessionId, $dbPeerRemoteId, $dbPeerCoordinateId)) {

      if ($db->addPeerConnection($dbPeerSessionId, $dbPeerRemoteId, $dbPeerCoordinateId, time())) {

        $debug['yggdrasil']['peer']['connection']['total']['insert']++;
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