<?php

// Load dependencies
require_once (__DIR__ . '/../config/app.php');
require_once (__DIR__ . '/../library/access.php');
require_once (__DIR__ . '/../library/mysql.php');
require_once (__DIR__ . '/../../vendor/autoload.php');

// Connect database
try {

  $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// Connect memcached
try {

  $memory = new Yggverse\Cache\Memory(MEMCACHED_HOST, MEMCACHED_PORT, MEMCACHED_NAMESPACE, MEMCACHED_TIMEOUT + time());

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// Prepare request
$requestPeerId   = isset($_GET['peerId']) ? (int) $_GET['peerId'] : 0;

$requestTheme    = isset($_GET['theme']) && in_array(['default'], $_GET['theme']) ? $_GET['theme'] : 'default';
$requestTime     = time(); // @TODO !empty($_GET['time']) ? (int) $_GET['time'] : time();
$requestSort     = isset($_GET['sort']) && in_array($_GET['sort'], ['peerConnection.timeAdded']) ? $_GET['sort'] : 'peerConnection.timeAdded';
$requestOrder    = isset($_GET['order']) && in_array($_GET['order'], ['ASC', 'DESC']) ? $_GET['order'] : 'DESC';
$requestPage     = isset($_GET['page']) && $_GET['page'] > 1 ? (int) $_GET['page'] : 1;
$requestCalendar = isset($_GET['calendar']) && in_array($_GET['calendar'], ['traffic']) ? $_GET['calendar'] : 'traffic';
$requestPort     = isset($_POST['port']) && 6 > strlen($_POST['port']) && $_POST['port'] > 0 ? (int) $_POST['port'] : false;

// App controller begin
$calendar = new Yggverse\Graph\Calendar\Month($requestTime);

foreach ($calendar->getNodes() as $day => $node) {

  switch ($requestCalendar) {

    case 'traffic':

      $timeThis = strtotime(sprintf('%s-%s-%s 00:00', date('Y'), date('n'), date('d')));
      $timeFrom = strtotime(sprintf('%s-%s-%s 00:00', date('Y', $requestTime), date('n', $requestTime), $day));
      $timeTo   = strtotime('+1 day', strtotime(sprintf('%s-%s-%s 00:00', date('Y', $requestTime), date('n', $requestTime), $day)));

      // Calculate today and previous days only
      if ($timeFrom <= $timeThis) {

        $dbPeerSessionSentSumByTimeUpdated = $memory->getByMethodCallback(
          $db, 'findPeerSessionSentSumByTimeUpdated', [$timeFrom, $timeTo, $requestPeerId], time() + ($timeTo <= $timeThis ? 31556952 : 300)
        );

        $dbPeerSessionReceivedSumByTimeUpdated = $memory->getByMethodCallback(
          $db, 'findPeerSessionReceivedSumByTimeUpdated', [$timeFrom, $timeTo, $requestPeerId], time() + ($timeTo <= $timeThis ? 31556952 : 300)
        );

        // Add daily stats
        $calendar->addNode($day, $dbPeerSessionSentSumByTimeUpdated, sprintf(_('&uarr; %s'), number_format($dbPeerSessionSentSumByTimeUpdated / 1000000, 3)), 'red', 0);
        $calendar->addNode($day, $dbPeerSessionReceivedSumByTimeUpdated, sprintf(_('&darr; %s'), number_format($dbPeerSessionReceivedSumByTimeUpdated / 1000000, 3)), 'green', 0);

        // Add hourly stats
        for ($hour = 0; $hour < 24; $hour++) {

          $timeThis = strtotime(sprintf('%s-%s-%s %s:00', date('Y'), date('n'), date('d'), date('H')));
          $timeFrom = strtotime(sprintf('%s-%s-%s %s:00', date('Y', $requestTime), date('n', $requestTime), $day, $hour));
          $timeTo   = strtotime(sprintf('%s-%s-%s %s:00', date('Y', $requestTime), date('n', $requestTime), $day, $hour + 1));

          // Calculate this hour of today and previous hours only
          if ($timeFrom <= $timeThis) {

            $dbPeerSessionSentSumByTimeUpdated = $memory->getByMethodCallback(
              $db, 'findPeerSessionSentSumByTimeUpdated', [$timeFrom, $timeTo, $requestPeerId], time() + ($timeTo <= $timeThis ? 31556952 : 300)
            );

            $dbPeerSessionReceivedSumByTimeUpdated = $memory->getByMethodCallback(
              $db, 'findPeerSessionReceivedSumByTimeUpdated', [$timeFrom, $timeTo, $requestPeerId], time() + ($timeTo <= $timeThis ? 31556952 : 300)
            );

          } else {

            $dbPeerSessionSentSumByTimeUpdated = 0;
            $dbPeerSessionReceivedSumByTimeUpdated = 0;
          }

          $calendar->addNode($day, $dbPeerSessionSentSumByTimeUpdated, sprintf(_('%s:00-%s:00 &uarr; %s'), $hour, $hour + 1, number_format($dbPeerSessionSentSumByTimeUpdated / 1000000, 3)), 'red', 1);
          $calendar->addNode($day, $dbPeerSessionReceivedSumByTimeUpdated, sprintf(_('%s:00-%s:00 &darr; %s'), $hour, $hour + 1, number_format($dbPeerSessionReceivedSumByTimeUpdated / 1000000, 3)), 'green', 1);

        }
      }
    break;
  }
}

$peerRemoteConnections = $memory->getByMethodCallback(
  $db,
  'findPeerPeerConnections',
  [
    $requestPeerId,
    ($requestPage - 1) * WEBSITE_PEER_REMOTE_PAGINATION_LIMIT,
    $requestPage + WEBSITE_PEER_REMOTE_PAGINATION_LIMIT,
    $requestSort,
    $requestOrder,
  ]
);

$peerInfo = $memory->getByMethodCallback($db, 'getPeerInfo', [$requestPeerId]);

// Port check
$responsePort = (object)
[
  'status'  => false,
  'message' => null,
];

if ($requestPort) {

  if ($peerInfo) {

    // Check requests quota
    $lastPeerPortStatus = $db->findLastPeerPortStatusByPeerId($requestPeerId);

    if (!Access::address(array_merge([$peerInfo->address], ADMIN_REMOTE_ADDRESS_WHITELIST)) &&
        $lastPeerPortStatus &&
        $lastPeerPortStatus->timeAdded > time() - WEBSITE_PEER_PORT_CHECK_TIMEOUT) {

      $responsePort = (object)
      [
        'status'  => false,
        'message' => sprintf(_('request quota %s minutes'), WEBSITE_PEER_PORT_CHECK_TIMEOUT / 60),
      ];

    } else {

      // Get port connection
      $connection = @fsockopen(
        sprintf('[%s]', $peerInfo->address),
        $requestPort,
        $error_code,
        $error_message,
        5
      );

      if (is_resource($connection)) {

        $responsePort = (object)
        [
          'status'  => true,
          'message' => sprintf(_('%s port open'), $requestPort),
        ];

        fclose($connection);

      } else {

        $responsePort = (object)
        [
          'status'  => false,
          'message' => sprintf(_('%s port closed'), $requestPort),
        ];
      }

      // Init port
      if ($peerPort = $db->findPeerPortByValue($requestPeerId, $requestPort)) {

        $peerPortId = $peerPort->peerPortId;

      } else {

        $peerPortId = $db->addPeerPort($requestPeerId, $requestPort);
      }

      // Save connection result
      $db->addPeerPortStatus($peerPortId, $responsePort->status, time());
    }
  }
}

$peerPortStatuses = $db->findLastPeerPortStatusesByPeerId($requestPeerId);

?>

<!DOCTYPE html>
<html lang="en-US">
  <head>
    <link rel="stylesheet" type="text/css" href="<?php echo WEBSITE_URL ?>/assets/theme/<?php echo $requestTheme ?>/css/common.css?<?php echo WEBSITE_CSS_VERSION ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo WEBSITE_URL ?>/assets/theme/<?php echo $requestTheme ?>/css/framework.css?<?php echo WEBSITE_CSS_VERSION ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo WEBSITE_URL ?>/assets/theme/<?php echo $requestTheme ?>/css/yggverse/graph/calendar/month.css?<?php echo WEBSITE_CSS_VERSION ?>" />
    <title>
      <?php if ($peerInfo) { ?>
        <?php echo sprintf(_('Peer %s - %s'), $peerInfo->address, WEBSITE_NAME) ?>
      <?php } else { ?>
        <?php echo _('Not found') ?>
      <?php } ?>
    </title>
    <meta name="description" content="<?php echo _('Yggdrasil peer info analytics: ip, traffic, timing, routing, geo-location, port status') ?>" />
    <meta name="keywords" content="yggdrasil, yggstate, yggverse, analytics, explorer, search engine, crawler, ip info, geo location, node city, node country, traffic stats, port open status, node coordinates, connection time, routes, open-source, js-less" />
    <meta name="author" content="YGGverse" />
    <meta charset="UTF-8" />
  </head>
  <body>
    <header>
      <div class="container">
        <div class="row">
          <a class="logo" href="<?php echo WEBSITE_URL ?>"><?php echo str_replace('YGG', '<span>YGG</span>', WEBSITE_NAME) ?></a>
          <?php if (Access::address(ADMIN_REMOTE_ADDRESS_WHITELIST)) { ?>
            <sup class="label label-green font-size-12 font-width-normal cursor-default"><?php echo _('admin') ?></sup>
          <?php } ?>
          <form name="search" method="get" action="<?php echo WEBSITE_URL ?>/search.php">
            <input type="text" name="query" value="" placeholder="<?php echo _('this, address, ip, geo, port, keyword...') ?>" />
            <button type="submit"><?php echo _('search') ?></button>
          </form>
        </div>
      </div>
    </header>
    <main>
      <?php if ($peerInfo) { ?>
        <div class="container">
          <div class="row">
            <div class="column width-100">
              <div class="padding-4">
                <h1>
                  <?php echo sprintf(_('Peer %s'), $peerInfo->address) ?>
                  <?php if (Access::address([$peerInfo->address])) { ?>
                    <span class="label label-green font-size-12 font-width-normal cursor-default" title="<?php echo _('you have connected from this peer') ?>">
                      <?php echo _('this connection') ?>
                    </span>
                  <?php } ?>
                </h1>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="column width-50 width-tablet-100 width-mobile-100">
              <div class="padding-4">
                <h2><?php echo _('Connections') ?></h2>
                <table>
                  <?php if ($peerRemoteConnections) { ?>
                    <thead>
                      <tr>
                        <th class="text-left">
                          <a href="<?php echo WEBSITE_URL ?>/peer.php?peerId=<?php echo $requestPeerId ?>&sort=peerConnection.timeAdded&order=<?php echo $requestOrder == 'DESC' ? 'ASC' : 'DESC' ?>">
                            <?php echo _('Time') ?>
                          </a>
                        </th>
                        <?php if (Access::address(array_merge([$peerInfo->address], ADMIN_REMOTE_ADDRESS_WHITELIST))) { ?>
                          <th class="text-left">
                            <?php echo _('Remote') ?>
                            <sub title="<?php echo _('Feature visible for this connection only') ?>">
                              <svg class="width-13-px" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-unlock" viewBox="0 0 16 16">
                                <path d="M11 1a2 2 0 0 0-2 2v4a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h5V3a3 3 0 0 1 6 0v4a.5.5 0 0 1-1 0V3a2 2 0 0 0-2-2zM3 8a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1H3z"/>
                              </svg>
                            </sub>
                          </th>
                        <?php } ?>
                        <th class="text-center">
                          <?php echo _('Port') ?>
                        </th>
                        <th class="text-left">
                          <?php echo _('Coordinate') ?>
                        </th>
                        <th class="text-center">
                          <?php echo _('Geo') ?>
                        </th>
                        <th class="text-center">
                          <?php echo _('Online') ?>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($peerRemoteConnections as $i => $peerRemoteConnection) { ?>
                        <tr>
                          <td class="text-left no-wrap"><?php echo date('Y-m-d H:s:i', $peerRemoteConnection->timeAdded) ?></td>
                          <?php if (Access::address(array_merge([$peerInfo->address], ADMIN_REMOTE_ADDRESS_WHITELIST))) { ?>
                            <td class="text-left">
                              <?php echo $peerRemoteConnection->remote ?>
                            </td>
                          <?php } ?>
                          <td class="text-center"><?php echo $peerRemoteConnection->connectionPort ?></td>
                          <td class="text-left"><?php echo $peerRemoteConnection->route ?></td>
                          <td class="text-center">
                            <span class="cursor-default" title="<?php echo $peerRemoteConnection->geoCityName ?> <?php echo $peerRemoteConnection->geoCountryName ?>">
                              <?php echo $peerRemoteConnection->geoCountryIsoCode ?>
                            <span>
                          </td>
                          <td class="text-center">
                            <span class="font-size-22 cursor-default <?php echo $i == 0 && $peerRemoteConnection->timeOnline > time() - WEBSITE_PEER_REMOTE_TIME_ONLINE_TIMEOUT ? 'text-color-green' : 'text-color-red' ?>">
                              &bull;
                            </span>
                          </td>
                        </tr>
                      <?php } ?>
                    </tbody>
                    <?php if ($i >= WEBSITE_PEER_REMOTE_PAGINATION_LIMIT) { ?>
                    <tfoot>
                      <tr>
                        <td colspan="<?php echo Access::address(array_merge([$peerInfo->address], ADMIN_REMOTE_ADDRESS_WHITELIST)) ? 6 : 5 ?>" class="text-left">
                          <?php if ($requestPage > 1) { ?>
                            <a href="<?php echo WEBSITE_URL ?>/peer.php?peerId=<?php echo $requestPeerId ?>&sort=<?php echo $requestSort ?>&page=<?php echo $requestPage - 1 ?>"><?php echo _('&larr;') ?></a>
                          <?php } ?>
                          <?php echo sprintf(_('page %s'), $requestPage) ?>
                          <a href="<?php echo WEBSITE_URL ?>/peer.php?peerId=<?php echo $requestPeerId ?>&sort=<?php echo $requestSort ?>&page=<?php echo $requestPage + 1 ?>"><?php echo _('&rarr;') ?></a>
                        </td>
                      </tr>
                    </tfoot>
                  <?php } ?>
                  <?php } else { ?>
                    <tfoot>
                      <tr>
                        <td class="text-center"><?php echo _('Connections not found') ?></td>
                      </tr>
                    </tfoot>
                  <?php } ?>
                </table>
              </div>
            </div>
            <div class="column width-50 width-tablet-100 width-mobile-100">
              <div class="padding-4">
                <h2><?php echo _('Peer info') ?></h2>
                <div class="row padding-0">
                  <div class="column width-100">
                    <table class="bordered width-100">
                      <thead>
                        <tr>
                          <th  class="text-left" colspan="2"><?php echo _('General') ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td class="text-left"><?php echo _('Address') ?></td>
                          <td class="text-right"><?php echo $peerInfo->address ?></td>
                        </tr>
                        <tr>
                          <td class="text-left"><?php echo _('Public key') ?></td>
                          <td class="text-right"><?php echo $peerInfo->key ?></td>
                        </tr>
                        <tr>
                          <td class="text-left"><?php echo _('Time added') ?></td>
                          <td class="text-right"><?php echo date('Y-m-d H:s:i', $peerInfo->timeAdded) ?></td>
                        </tr>
                        <tr>
                          <td class="text-left"><?php echo _('Last connection') ?></td>
                          <td class="text-right"><?php echo date('Y-m-d H:s:i', $peerInfo->timeOnline) ?></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="row padding-0">
                  <div class="column width-50">
                    <table class="bordered width-100 margin-y-8">
                      <thead>
                        <tr>
                          <th  class="text-left" colspan="2"><?php echo _('Session') ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td class="text-left"><?php echo _('Uptime avg, hours') ?></td>
                          <td class="text-right"><?php echo round($peerInfo->uptimeAvg / 60 / 60, 2) ?></td>
                        </tr>
                        <tr>
                          <td class="text-left"><?php echo _('Remote') ?></td>
                          <td class="text-right"><?php echo number_format($peerInfo->remoteTotal) ?></td>
                        </tr>
                        <tr>
                          <td class="text-left"><?php echo _('Session') ?></td>
                          <td class="text-right"><?php echo number_format($peerInfo->sessionTotal) ?></td>
                        </tr>
                        <tr>
                          <td class="text-left"><?php echo _('Coordinate') ?></td>
                          <td class="text-right"><?php echo number_format($peerInfo->coordinateTotal) ?></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div class="column width-50">
                    <div class="margin-left-16">
                      <table class="bordered width-100 margin-y-8">
                        <thead>
                          <tr>
                            <th  class="text-left" colspan="2"><?php echo _('Traffic, Mb') ?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td class="text-left"><?php echo _('Sent') ?></td>
                            <td class="text-right"><?php echo number_format($peerInfo->sentSum / 1000000, 3) ?></td>
                          </tr>
                          <tr>
                            <td class="text-left"><?php echo _('Received') ?></td>
                            <td class="text-right"><?php echo number_format($peerInfo->receivedSum / 1000000, 3) ?></td>
                          </tr>
                          <tr>
                            <td class="text-left"><?php echo _('Sum') ?></td>
                            <td class="text-right"><?php echo number_format(($peerInfo->sentSum + $peerInfo->receivedSum) / 1000000, 3) ?></td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                <div class="row padding-0">
                  <div class="column width-50">
                    <table class="bordered width-100">
                      <thead>
                        <tr>
                          <th  class="text-left" colspan="3"><?php echo _('Port') ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if ($peerPortStatuses) { ?>
                          <?php foreach ($peerPortStatuses as $peerPortStatus) { ?>
                            <tr>
                              <td class="text-left"><?php echo $peerPortStatus->port ?></td>
                              <td class="text-left"><?php echo date('Y-m-d H:s:i', $peerPortStatus->timeAdded) ?></td>
                              <td class="text-center padding-0">
                              <?php if ($peerPortStatus->status) { ?>
                                <span class="font-size-22 cursor-default text-color-green" title="<?php echo _('open') ?>">
                                  &bull;
                                </span>
                              </td>
                              <?php } else { ?>
                                <span class="font-size-22 cursor-default text-color-red" title="<?php echo _('closed') ?>">
                                  &bull;
                                </span>
                              <?php } ?>
                            </tr>
                          <?php } ?>
                        <?php } else { ?>
                          <tr>
                            <td colspan="3"><?php echo _('no records found') ?></td>
                          </tr>
                        <?php } ?>
                      </tbody>
                      <tfoot>
                        <tr>
                          <td class="text-left padding-x-0 padding-y-8" colspan="3">
                            <?php if ($responsePort->status) { ?>
                              <span class="padding-x-4 line-height-26 text-color-green">
                                <?php echo $responsePort->message ?>
                              </span>
                            <?php } else { ?>
                              <span class="padding-x-4 line-height-26 text-color-red">
                                <?php echo $responsePort->message ?>
                              </span>
                            <?php } ?>
                            <form name="port" method="post" action="<?php echo WEBSITE_URL ?>/peer.php?peerId=<?php echo $requestPeerId ?>">
                              <input class="text-center" type="text" name="port" value="<?php echo $requestPort ?>" maxlength="5" size="5" placeholder="<?php echo _('port') ?>" />
                              <button type="submit"><?php echo _('check') ?></button>
                            </form>
                          </td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
                <h2>
                  <?php echo _('Traffic') ?>
                  <span class="float-right">
                    <?php echo sprintf(_('%s - %s'), date('Y.m.d', strtotime(sprintf('%s-%s-%s 00:00', date('Y', $requestTime), date('n', $requestTime), 1), $requestTime)),
                                                     date('Y.m.d', strtotime('+1 month', strtotime(sprintf('%s-%s-%s 00:00', date('Y', $requestTime), date('n', $requestTime), 1), $requestTime)))) ?>
                  </span>
                </h2>
                <div class="yggverse_graph_calendar__month">
                  <?php foreach ($calendar->getNodes() as $day => $node) { ?>
                    <div class="day">
                      <div class="number <?php echo $day > date('j') ? 'disabled' : false ?>">
                        <?php echo $day ?>
                      </div>
                      <?php if ($day <= date('j')) { ?>
                        <?php foreach ($node as $i => $layers) { ?>
                          <div class="layer layer<?php echo $i ?>">
                            <div class="label">
                              <?php foreach ($layers as $layer) { ?>
                                <div class="<?php echo $layer->class ?>"><?php echo $layer->label ?></div>
                              <?php } ?>
                            </div>
                            <?php foreach ($layers as $layer) { ?>
                              <div title="<?php echo $layer->label ?>"
                                  class="value <?php echo $layer->class ?>"
                                  style="width:<?php echo $layer->width ?>%;height:<?php echo $layer->height ?>%;left:<?php echo $layer->offset ?>%"></div>
                            <?php } ?>
                          </div>
                        <?php } ?>
                      <?php } ?>
                    </div>
                  <?php } ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php } else { ?>
        <div class="container">
          <div class="row">
            <div class="column width-100">
              <div class="padding-4">
                <h1><?php echo sprintf(_('Peer #%s not found'), $requestPeerId) ?></h1>
              </div>
            </div>
          </div>
        </div>
      <?php } ?>
    </main>
    <footer>
      <div class="container">
        <div class="row">
          <div class="column width-50 width-tablet-100 width-mobile-100">
            <a href="https://github.com/YGGverse/YGGstate"><?php echo _('GitHub') ?></a>
          </div>
          <div class="column width-50 width-tablet-100 width-mobile-100 text-right">
            <?php echo sprintf(_('server time: %s / %s'), time(), date('c')) ?>
            <br />
            <?php echo sprintf(_('database since %s contains %s peers'),
                               date('M, Y', $memory->getByMethodCallback($db, 'getPeerFirstByTimeAdded')->timeAdded),
                               number_format($memory->getByMethodCallback($db, 'getPeersTotal'))) ?>
          </div>
        </div>
      </div>
    </footer>
  </body>
</html>