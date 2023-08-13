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
$requestTheme    = isset($_GET['theme']) && in_array(['default'], $_GET['theme']) ? $_GET['theme'] : 'default';
$requestTime     = time(); // @TODO !empty($_GET['time']) ? (int) $_GET['time'] : time();
$requestSort     = isset($_GET['sort']) && in_array($_GET['sort'], ['timeOnline', 'uptimeAvg', 'sentSum', 'receivedSum', 'address']) ? $_GET['sort'] : 'timeOnline';
$requestOrder    = isset($_GET['order']) && in_array($_GET['order'], ['ASC', 'DESC']) ? $_GET['order'] : 'DESC';
$requestPage     = isset($_GET['page']) && $_GET['page'] > 1 ? (int) $_GET['page'] : 1;
$requestCalendar = isset($_GET['calendar']) && in_array($_GET['calendar'], ['peers', 'traffic']) ? $_GET['calendar'] : 'peers';

// app begin
$calendar = new Yggverse\Graph\Calendar\Month($requestTime);

foreach ($calendar->getNodes() as $day => $node) {

  switch ($requestCalendar) {

    case 'peers':

      $timeFrom = strtotime(sprintf('%s-%s-%s 00:00', date('Y', $requestTime), date('n', $requestTime), $day));
      $timeTo   = strtotime('+1 day', strtotime(sprintf('%s-%s-%s 00:00', date('Y', $requestTime), date('n', $requestTime), $day)));
      $timeThis = strtotime('+1 day', strtotime(sprintf('%s-%s-%s 00:00', date('Y'), date('n'), $day)));

      $dbPeerTotalByTimeUpdated = $memory->getByMethodCallback(
        $db, 'findPeerTotalByTimeUpdated', [$timeFrom, $timeTo], ($timeTo <= $timeThis ? 2592000 : MEMCACHED_TIMEOUT) + time()
      );

      $dbPeerTotalByTimeAdded = $memory->getByMethodCallback(
        $db, 'findPeerTotalByTimeAdded', [$timeFrom, $timeTo], ($timeTo <= $timeThis ? 2592000 : MEMCACHED_TIMEOUT) + time()
      );

      // Add daily stats
      $calendar->addNode($day, $dbPeerTotalByTimeUpdated, sprintf(_('online %s'), $dbPeerTotalByTimeUpdated), 'green', 0);
      $calendar->addNode($day, $dbPeerTotalByTimeAdded, sprintf(_('new %s'), $dbPeerTotalByTimeAdded), 'blue', 0);

      // Add hourly stats
      for ($hour = 0; $hour < 24; $hour++) {

        $timeFrom = strtotime(sprintf('%s-%s-%s %s:00', date('Y', $requestTime), date('n', $requestTime), $day, $hour));
        $timeTo   = strtotime(sprintf('%s-%s-%s %s:00', date('Y', $requestTime), date('n', $requestTime), $day, $hour + 1));
        $timeThis = strtotime(sprintf('%s-%s-%s %s:00', date('Y'), date('n'), $day, $hour + 1));

        $dbPeerTotalByTimeUpdated = $memory->getByMethodCallback(
          $db, 'findPeerTotalByTimeUpdated', [$timeFrom, $timeTo], ($timeTo <= $timeThis ? 2592000 : MEMCACHED_TIMEOUT) + time()
        );

        $dbPeerTotalByTimeAdded = $memory->getByMethodCallback(
          $db, 'findPeerTotalByTimeAdded', [$timeFrom, $timeTo], ($timeTo <= $timeThis ? 2592000 : MEMCACHED_TIMEOUT) + time()
        );

        $calendar->addNode($day, $dbPeerTotalByTimeUpdated, sprintf(_('%s:00-%s:00 online %s'), $hour, $hour + 1, $dbPeerTotalByTimeUpdated), 'green', 1);
        $calendar->addNode($day, $dbPeerTotalByTimeAdded, sprintf(_('%s:00-%s:00 new %s'), $hour, $hour + 1, $dbPeerTotalByTimeAdded), 'blue', 1);
      }

    break;
    case 'traffic':

      $timeFrom = strtotime(sprintf('%s-%s-%s 00:00', date('Y', $requestTime), date('n', $requestTime), $day));
      $timeTo   = strtotime('+1 day', strtotime(sprintf('%s-%s-%s 00:00', date('Y', $requestTime), date('n', $requestTime), $day)));
      $timeThis = strtotime('+1 day', strtotime(sprintf('%s-%s-%s 00:00', date('Y'), date('n'), $day)));

      $dbPeerSessionSentSumByTimeUpdated = $memory->getByMethodCallback(
        $db, 'findPeerSessionSentSumByTimeUpdated', [$timeFrom, $timeTo], ($timeTo <= $timeThis ? 2592000 : MEMCACHED_TIMEOUT) + time()
      );

      $dbPeerSessionReceivedSumByTimeUpdated = $memory->getByMethodCallback(
        $db, 'findPeerSessionReceivedSumByTimeUpdated', [$timeFrom, $timeTo], ($timeTo <= $timeThis ? 2592000 : MEMCACHED_TIMEOUT) + time()
      );

      // Add daily stats
      $calendar->addNode($day, $dbPeerSessionSentSumByTimeUpdated, sprintf(_('&uarr; %s'), number_format($dbPeerSessionSentSumByTimeUpdated / 1000000, 3)), 'red', 0);
      $calendar->addNode($day, $dbPeerSessionReceivedSumByTimeUpdated, sprintf(_('&darr; %s'), number_format($dbPeerSessionReceivedSumByTimeUpdated / 1000000, 3)), 'green', 0);

      // Add hourly stats
      for ($hour = 0; $hour < 24; $hour++) {

        $timeFrom = strtotime(sprintf('%s-%s-%s %s:00', date('Y', $requestTime), date('n', $requestTime), $day, $hour));
        $timeTo   = strtotime(sprintf('%s-%s-%s %s:00', date('Y', $requestTime), date('n', $requestTime), $day, $hour + 1));
        $timeThis = strtotime(sprintf('%s-%s-%s %s:00', date('Y'), date('n'), $day, $hour + 1));

        $dbPeerSessionSentSumByTimeUpdated = $memory->getByMethodCallback(
          $db, 'findPeerSessionSentSumByTimeUpdated', [$timeFrom, $timeTo], ($timeTo <= $timeThis ? 2592000 : MEMCACHED_TIMEOUT) + time()
        );

        $dbPeerSessionReceivedSumByTimeUpdated = $memory->getByMethodCallback(
          $db, 'findPeerSessionReceivedSumByTimeUpdated', [$timeFrom, $timeTo], ($timeTo <= $timeThis ? 2592000 : MEMCACHED_TIMEOUT) + time()
        );

        $calendar->addNode($day, $dbPeerSessionSentSumByTimeUpdated, sprintf(_('%s:00-%s:00 &uarr; %s'), $hour, $hour + 1, number_format($dbPeerSessionSentSumByTimeUpdated / 1000000, 3)), 'red', 1);
        $calendar->addNode($day, $dbPeerSessionReceivedSumByTimeUpdated, sprintf(_('%s:00-%s:00 &darr; %s'), $hour, $hour + 1, number_format($dbPeerSessionReceivedSumByTimeUpdated / 1000000, 3)), 'green', 1);
      }

    break;
  }
}

$peers = $memory->getByMethodCallback(
  $db,
  'findPeers',
  [
    ($requestPage - 1) * WEBSITE_PEER_REMOTE_PAGINATION_LIMIT,
    $requestPage + WEBSITE_PEER_REMOTE_PAGINATION_LIMIT,
    $requestSort,
    $requestOrder,
  ]
);

?>

<!DOCTYPE html>
<html lang="en-US">
  <head>
    <link rel="stylesheet" type="text/css" href="<?php echo WEBSITE_URL ?>/assets/theme/<?php echo $requestTheme ?>/css/common.css?<?php echo time() ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo WEBSITE_URL ?>/assets/theme/<?php echo $requestTheme ?>/css/framework.css?<?php echo time() ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo WEBSITE_URL ?>/assets/theme/<?php echo $requestTheme ?>/css/yggverse/graph/calendar/month.css?<?php echo time() ?>" />
    <title>
      <?php echo sprintf(_('%s - Yggdrasil network explorer since %s'), WEBSITE_NAME, date('Y', $memory->getByMethodCallback($db, 'getPeerFirstByTimeAdded')->timeAdded)) ?>
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
      <div class="container">
        <div class="row">
          <div class="column width-100">
            <div class="padding-4">
              <h1><?php echo _('Dashboard') ?></h1>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="column width-50 width-tablet-100 width-mobile-100">
            <div class="padding-4">
              <h2><?php echo _('Peers') ?></h2>
              <table>
                <?php if ($peers) { ?>
                  <thead>
                    <tr>
                      <th class="text-left">
                        <a href="<?php echo WEBSITE_URL ?>/index.php?sort=address&order=<?php echo $requestOrder == 'DESC' ? 'ASC' : 'DESC' ?>">
                          <?php echo _('Address') ?>
                        </a>
                      </th>
                      <th class="text-center">
                        <a href="<?php echo WEBSITE_URL ?>/index.php?sort=uptimeAvg&order=<?php echo $requestOrder == 'DESC' ? 'ASC' : 'DESC' ?>">
                            <?php echo _('Uptime,h') ?>
                        </a>
                      </th>
                      <th class="text-center">
                        <a href="<?php echo WEBSITE_URL ?>/index.php?sort=sentSum&order=<?php echo $requestOrder == 'DESC' ? 'ASC' : 'DESC' ?>">
                            <?php echo _('Sent,Mb') ?>
                        </a>
                      </th>
                      <th class="text-center">
                        <a href="<?php echo WEBSITE_URL ?>/index.php?sort=receivedSum&order=<?php echo $requestOrder == 'DESC' ? 'ASC' : 'DESC' ?>">
                          <?php echo _('Received,Mb') ?>
                        </a>
                      </th>
                      <th class="text-center">
                        <a href="<?php echo WEBSITE_URL ?>/index.php?sort=timeOnline&order=<?php echo $requestOrder == 'DESC' ? 'ASC' : 'DESC' ?>">
                          <?php echo _('Online') ?>
                        </a>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($peers as $peer) { ?>
                      <tr>
                        <td class="text-left">
                          <a href="<?php echo WEBSITE_URL ?>/peer.php?peerId=<?php echo $peer->peerId ?>">
                            <?php echo $peer->address ?>
                          </a>
                        </td>
                        <td class="text-center"><?php echo $peer->uptimeAvg ? round($peer->uptimeAvg / 60 / 60, 2) : 0 ?></td>
                        <td class="text-center"><?php echo $peer->sentSum ? number_format($peer->sentSum / 1000000, 3) : 0 ?></td>
                        <td class="text-center"><?php echo $peer->receivedSum ? number_format($peer->receivedSum / 1000000, 3) : 0 ?></td>
                        <td class="text-center">
                          <span title="<?php echo date('Y-m-d H:s:i', $peer->timeOnline) ?>"
                                class="font-size-22 cursor-default <?php echo $peer->timeOnline + WEBSITE_PEER_REMOTE_TIME_ONLINE_TIMEOUT > time() ? 'text-color-green' : 'text-color-red' ?>">
                                &bull;
                          </span>
                        </td>
                      </tr>
                    <?php } ?>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="5" class="text-left">
                        <?php if ($requestPage > 1) { ?>
                          <a href="<?php echo WEBSITE_URL ?>/index.php?sort=<?php echo $requestSort ?>&page=<?php echo $requestPage - 1 ?>"><?php echo _('&larr;') ?></a>
                        <?php } ?>
                        <?php echo sprintf(_('page %s'), $requestPage) ?>
                        <a href="<?php echo WEBSITE_URL ?>/index.php?sort=<?php echo $requestSort ?>&page=<?php echo $requestPage + 1 ?>"><?php echo _('&rarr;') ?></a>
                      </td>
                    </tr>
                  </tfoot>
                <?php } else { ?>
                  <tfoot>
                    <tr>
                      <td class="text-center"><?php echo _('Peers not found') ?></td>
                    </tr>
                  </tfoot>
                <?php } ?>
              </table>
            </div>
          </div>
          <div class="column width-50 width-tablet-100 width-mobile-100">
            <div class="padding-4">
              <h2><?php echo _('Network totals') ?></h2>
              <div class="row padding-0">
                <div class="column width-50">
                  <table class="bordered width-100 margin-bottom-8">
                    <thead>
                      <tr>
                        <th class="text-left" colspan="2"><?php echo _('Peers') ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td class="text-left"><?php echo _('Online') ?></td>
                        <td class="text-right"><?php echo $memory->getByMethodCallback($db, 'findPeerTotalByTimeUpdated', [time() - WEBSITE_PEER_REMOTE_TIME_ONLINE_TIMEOUT,
                                                                                                                           strtotime('+1 month', strtotime(sprintf('%s-%s-%s 00:00', date('Y'), date('n'), 1)))]) ?></td>
                      </tr>
                      <tr>
                        <td class="text-left"><?php echo _('New') ?></td>
                        <td class="text-right"><?php echo $memory->getByMethodCallback($db, 'findPeerTotalByTimeAdded', [strtotime(sprintf('%s-%s-%s 00:00', date('Y'), date('n'), 1)),
                                                                                                                         strtotime('+1 month', strtotime(sprintf('%s-%s-%s 00:00', date('Y'), date('n'), 1)))]) ?></td>
                      </tr>
                      <tr>
                        <td class="text-left"><?php echo _('Active') ?></td>
                        <td class="text-right"><?php echo $memory->getByMethodCallback($db, 'findPeerTotalByTimeUpdated', [strtotime(sprintf('%s-%s-%s 00:00', date('Y'), date('n'), 1)),
                                                                                                                           strtotime('+1 month', strtotime(sprintf('%s-%s-%s 00:00', date('Y'), date('n'), 1)))]) ?></td>
                      </tr>
                      <tr>
                        <td class="text-left"><?php echo _('Total') ?></td>
                        <td class="text-right"><?php echo $memory->getByMethodCallback($db, 'getPeersTotal') ?></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="column width-50">
                  <div class="margin-left-16">
                    <table class="bordered width-100 margin-bottom-8">
                      <thead>
                        <tr>
                          <th class="text-left" colspan="2"><?php echo _('Traffic, Mb') ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td class="text-left"><?php echo _('Sent') ?></td>
                          <td class="text-right"><?php echo number_format($memory->getByMethodCallback($db, 'getPeerSessionSentSum') / 1000000, 3) ?></td>
                        </tr>
                        <tr>
                          <td class="text-left"><?php echo _('Received') ?></td>
                          <td class="text-right"><?php echo number_format($memory->getByMethodCallback($db, 'getPeerSessionReceivedSum') / 1000000, 3) ?></td>
                        </tr>
                        <tr>
                          <td class="text-left"><?php echo _('Sum') ?></td>
                          <td class="text-right"><?php echo number_format(($memory->getByMethodCallback($db, 'getPeerSessionSentSum') +
                                                                           $memory->getByMethodCallback($db, 'getPeerSessionReceivedSum')) / 1000000, 3) ?></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              <h2>
                <?php if ($requestCalendar == 'peers') { ?>
                  <?php echo _('Peers') ?> | <a href="<?php echo WEBSITE_URL ?>/index.php?calendar=traffic"><?php echo _('Traffic') ?></a>
                <?php } else { ?>
                  <a href="<?php echo WEBSITE_URL ?>/index.php?calendar=peers"><?php echo _('Peers') ?></a> | <?php echo _('Traffic') ?>
                <?php } ?>
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
                               $memory->getByMethodCallback($db, 'getPeersTotal')) ?>
          </div>
        </div>
      </div>
    </footer>
  </body>
</html>