<?php

// Load dependencies
require_once (__DIR__ . '/../config/app.php');
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

$requestTheme    = !empty($_GET['theme']) && in_array(['default'], $_GET['theme']) ? $_GET['theme'] : 'default';
$requestTime     = !empty($_GET['time']) ? (int) $requestTime : time();
$requestSort     = !empty($_GET['sort']) && in_array($_GET['sort'], ['peerConnection.timeAdded']) ? $_GET['sort'] : 'peerConnection.timeAdded';
$requestOrder    = !empty($_GET['order']) && in_array($_GET['order'], ['ASC', 'DESC']) ? $_GET['order'] : 'DESC';
$requestPage     = !empty($_GET['page']) && $_GET['page'] > 1 ? (int) $_GET['page'] : 1;
$requestCalendar = !empty($_GET['calendar']) && in_array($_GET['calendar'], ['traffic']) ? $_GET['calendar'] : 'traffic';

// App controller begin
$calendar = new Yggverse\Graph\Calendar\Month($requestTime);

foreach ($calendar->getNodes() as $day => $node) {

  switch ($requestCalendar) {

    case 'traffic':

      $timeFrom = strtotime(sprintf('%s-%s-%s 00:00', date('Y', $requestTime), date('n', $requestTime), $day));
      $timeTo   = strtotime('+1 day', strtotime(sprintf('%s-%s-%s 00:00', date('Y', $requestTime), date('n', $requestTime), $day)));

      $dbPeerSessionSentSumByTimeUpdated = $memory->getByMethodCallback(
        $db, 'findPeerSessionSentSumByTimeUpdated', [$timeFrom, $timeTo, $requestPeerId], ($timeTo <= $requestTime ? 2592000 : MEMCACHED_TIMEOUT) + time()
      );

      $dbPeerSessionReceivedSumByTimeUpdated = $memory->getByMethodCallback(
        $db, 'findPeerSessionReceivedSumByTimeUpdated', [$timeFrom, $timeTo, $requestPeerId], ($timeTo <= $requestTime ? 2592000 : MEMCACHED_TIMEOUT) + time()
      );

      // Add daily stats
      $calendar->addNode($day, $dbPeerSessionSentSumByTimeUpdated, sprintf(_('&uarr; %s'), number_format($dbPeerSessionSentSumByTimeUpdated / 1000000, 3)), 'red', 0);
      $calendar->addNode($day, $dbPeerSessionReceivedSumByTimeUpdated, sprintf(_('&darr; %s'), number_format($dbPeerSessionReceivedSumByTimeUpdated / 1000000, 3)), 'green', 0);

      // Add hourly stats
      for ($hour = 0; $hour < 24; $hour++) {

        $timeFrom = strtotime(sprintf('%s-%s-%s %s:00', date('Y', $requestTime), date('n', $requestTime), $day, $hour));
        $timeTo   = strtotime(sprintf('%s-%s-%s %s:00', date('Y', $requestTime), date('n', $requestTime), $day, $hour + 1));

        $dbPeerSessionSentSumByTimeUpdated = $memory->getByMethodCallback(
          $db, 'findPeerSessionSentSumByTimeUpdated', [$timeFrom, $timeTo, $requestPeerId], ($timeTo <= $requestTime ? 2592000 : MEMCACHED_TIMEOUT) + time()
        );

        $dbPeerSessionReceivedSumByTimeUpdated = $memory->getByMethodCallback(
          $db, 'findPeerSessionReceivedSumByTimeUpdated', [$timeFrom, $timeTo, $requestPeerId], ($timeTo <= $requestTime ? 2592000 : MEMCACHED_TIMEOUT) + time()
        );

        $calendar->addNode($day, $dbPeerSessionSentSumByTimeUpdated, sprintf(_('%s:00-%s:00 &uarr; %s'), $hour, $hour + 1, number_format($dbPeerSessionSentSumByTimeUpdated / 1000000, 3)), 'red', 1);
        $calendar->addNode($day, $dbPeerSessionReceivedSumByTimeUpdated, sprintf(_('%s:00-%s:00 &darr; %s'), $hour, $hour + 1, number_format($dbPeerSessionReceivedSumByTimeUpdated / 1000000, 3)), 'green', 1);
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

?>

<!DOCTYPE html>
<html lang="en-US">
  <head>
    <link rel="stylesheet" type="text/css" href="<?php echo WEBSITE_URL ?>/assets/theme/<?php echo $requestTheme ?>/css/common.css?<?php echo time() ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo WEBSITE_URL ?>/assets/theme/<?php echo $requestTheme ?>/css/framework.css?<?php echo time() ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo WEBSITE_URL ?>/assets/theme/<?php echo $requestTheme ?>/css/yggverse/graph/calendar/month.css?<?php echo time() ?>" />
    <title>
      <?php echo sprintf(_('Peer %s - %s'), $peerInfo->address, WEBSITE_NAME) ?>
    </title>
    <meta name="description" content="<?php echo _('Yggdrasil network analytics: peers, ip, traffic, timing, geo-location') ?>" />
    <meta name="keywords" content="yggdrasil, yggstate, yggverse, analytics, explorer, open-source, open-data, js-less" />
    <meta charset="UTF-8" />
  </head>
  <body>
    <header>
      <div class="container">
        <div class="row">
          <a class="logo" href="<?php echo WEBSITE_URL ?>"><?php echo str_replace('YGG', '<span>YGG</span>', WEBSITE_NAME) ?></a>
          <form name="search" method="get" action="<?php echo WEBSITE_URL ?>/search.php">
            <input type="text" name="query" value="" placeholder="<?php echo _('address, ip, port, keyword...') ?>" />
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
                <h1><?php echo sprintf(_('Peer %s'), $peerInfo->address) ?></h1>
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
                          <a href="?peerId=<?php echo $requestPeerId ?>&sort=peerConnection.timeAdded&order=<?php echo $requestOrder == 'DESC' ? 'ASC' : 'DESC' ?>">
                              <?php echo _('Time') ?>
                          </a>
                        </th>
                        <th class="text-left">
                          <?php echo _('Remote') ?>
                        </th>
                        <th class="text-center">
                          <?php echo _('Port') ?>
                        </th>
                        <th class="text-left">
                          <?php echo _('Coordinate') ?>
                        </th>
                        <th class="text-center">
                          <?php echo _('Online') ?>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($peerRemoteConnections as $i => $peerRemoteConnection) { ?>
                        <tr>
                          <td class="text-left"><?php echo date('Y-m-d H:s:i', $peerRemoteConnection->timeAdded) ?></td>
                          <td class="text-left"><?php echo $peerRemoteConnection->remote ?></td>
                          <td class="text-center"><?php echo $peerRemoteConnection->connectionPort ?></td>
                          <td class="text-left"><?php echo $peerRemoteConnection->route ?></td>
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
                        <td colspan="5" class="text-left">
                          <?php if ($requestPage > 1) { ?>
                            <a href="?peerId=<?php echo $requestPeerId ?>&sort=<?php echo $requestSort ?>&page=<?php echo $requestPage - 1 ?>"><?php echo _('&larr;') ?></a>
                          <?php } ?>
                          <?php echo sprintf(_('page %s'), $requestPage) ?>
                          <a href="?peerId=<?php echo $requestPeerId ?>&sort=<?php echo $requestSort ?>&page=<?php echo $requestPage + 1 ?>"><?php echo _('&rarr;') ?></a>
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
                          <td class="text-right"><?php echo $peerInfo->remoteTotal ?></td>
                        </tr>
                        <tr>
                          <td class="text-left"><?php echo _('Session') ?></td>
                          <td class="text-right"><?php echo $peerInfo->sessionTotal ?></td>
                        </tr>
                        <tr>
                          <td class="text-left"><?php echo _('Coordinate') ?></td>
                          <td class="text-right"><?php echo $peerInfo->coordinateTotal ?></td>
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
                <h2>
                  <?php echo _('Traffic') ?>
                  <div class="float-right">
                    <?php echo sprintf(_('%s - %s'), date('Y.m.d', strtotime(sprintf('%s-%s-%s 00:00', date('Y', $requestTime), date('n', $requestTime), 1), $requestTime)),
                                                     date('Y.m.d', strtotime('+1 month', strtotime(sprintf('%s-%s-%s 00:00', date('Y', $requestTime), date('n', $requestTime), 1), $requestTime)))) ?>
                  </div>
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
            <?php echo sprintf(_('database since %s contains %s peers'), date('M, Y', $memory->getByMethodCallback($db, 'getPeerFirstByTimeAdded')->timeAdded), $memory->getByMethodCallback($db, 'getPeersTotal')) ?>
          </div>
        </div>
      </div>
    </footer>
  </body>
</html>