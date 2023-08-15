<?php

require_once (__DIR__ . '/../config/app.php');
require_once (__DIR__ . '/../library/mysql.php');
require_once(__DIR__ . '/../library/sphinxql.php');
require_once __DIR__ . '/../../vendor/autoload.php';

// Connect Sphinx search server
try {

  $sphinx = new SphinxQL(SPHINX_HOST, SPHINX_PORT);

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// Connect database
try {

  $db = new MySQL(DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD);

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// Connect memory
try {

  $memory = new Yggverse\Cache\Memory(MEMCACHED_HOST, MEMCACHED_PORT, MEMCACHED_NAMESPACE, MEMCACHED_TIMEOUT + time());

} catch(Exception $e) {

  var_dump($e);

  exit;
}

// Filter request data
$requestQuery = !empty($_GET['query']) ? trim(html_entity_decode(urldecode($_GET['query']))) : '*';
$requestTheme = !empty($_GET['theme']) && in_array(['default'], $_GET['theme']) ? $_GET['theme'] : 'default';
$requestPage  = !empty($_GET['page']) && $_GET['page'] > 1 ? (int) $_GET['page'] : 1;

// Search request
$total   = $sphinx->searchPeersTotal($requestQuery);
$results = $sphinx->searchPeers($requestQuery,
                                $requestPage * WEBSITE_PEER_REMOTE_PAGINATION_LIMIT - WEBSITE_PEER_REMOTE_PAGINATION_LIMIT,
                                WEBSITE_PEER_REMOTE_PAGINATION_LIMIT,
                                $total);

?>

<!DOCTYPE html>
<html lang="en-US">
  <head>
    <link rel="stylesheet" type="text/css" href="<?php echo WEBSITE_URL ?>/assets/theme/<?php echo $requestTheme ?>/css/common.css?<?php echo time() ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo WEBSITE_URL ?>/assets/theme/<?php echo $requestTheme ?>/css/framework.css?<?php echo time() ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo WEBSITE_URL ?>/assets/theme/<?php echo $requestTheme ?>/css/yggverse/graph/calendar/month.css?<?php echo time() ?>" />
    <title>
      <?php echo sprintf(_('%s - Search - %s'), htmlentities($requestQuery), WEBSITE_NAME) ?>
    </title>
    <meta name="description" content="<?php echo _('Yggdrasil network search: peer info, ip, traffic, timing, routing, geo-location') ?>" />
    <meta name="keywords" content="yggdrasil, yggstate, yggverse, analytics, explorer, search engine, crawler, ip info, geo location, node city, node country, traffic stats, ports, node coordinates, connection time, routes, open-source, js-less" />
    <meta name="author" content="YGGverse" />
    <meta charset="UTF-8" />
  </head>
  <body>
    <header>
      <div class="container">
        <div class="row">
          <a class="logo" href="<?php echo WEBSITE_URL ?>"><?php echo str_replace('YGG', '<span>YGG</span>', WEBSITE_NAME) ?></a>
          <form name="search" method="get" action="<?php echo WEBSITE_URL ?>/search.php">
            <input type="text" name="query" value="<?php echo htmlentities($requestQuery) ?>" placeholder="<?php echo _('address, ip, geo, port, keyword...') ?>" />
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
              <h1><?php echo sprintf(_('Search matches for %s'), htmlentities($requestQuery)) ?></h1>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="column width-100">
            <table>
              <?php if ($total) { ?>
                <thead>
                  <tr>
                    <th class="text-left"><?php echo _('Address') ?></th>
                    <th class="text-center"><?php echo _('Key') ?></th>
                    <th class="text-center"><?php echo _('Coordinate port') ?></th>
                    <th class="text-center"><?php echo _('Coordinate route') ?></th>
                    <th class="text-center"><?php echo _('Remote scheme') ?></th>
                    <th class="text-center"><?php echo _('Remote host') ?></th>
                    <th class="text-center"><?php echo _('Remote port') ?></th>
                    <th class="text-center"><?php echo _('Country') ?></th>
                    <th class="text-center"><?php echo _('City') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($results as $result) { ?>
                    <tr>
                      <td class="text-left">
                        <a href="<?php echo WEBSITE_URL ?>/peer.php?peerId=<?php echo $result->id ?>">
                          <?php echo $result->peeraddress ?>
                        </a>
                      </td>
                      <td class="text-center">
                        <?php if (false === strpos($result->peerkey, $requestQuery)) { ?>
                          <span title="<?php echo $result->peerkey ?>" class="font-size-22 cursor-default text-color-red">
                            &bull;
                          </span>
                        <?php } else { ?>
                          <span title="<?php echo $result->peerkey ?>" class="font-size-22 cursor-default text-color-green">
                            &bull;
                          </span>
                        <?php } ?>
                      </td>
                      <td class="text-center">
                        <?php if (false === strpos($result->peercoordinateport, $requestQuery)) { ?>
                            <span title="<?php echo $result->peercoordinateport ?>" class="font-size-22 cursor-default text-color-red">
                              &bull;
                            </span>
                          <?php } else { ?>
                            <span title="<?php echo $result->peercoordinateport ?>" class="font-size-22 cursor-default text-color-green">
                              &bull;
                            </span>
                        <?php } ?>
                      </td>
                      <td class="text-center">
                        <?php if (false === strpos($result->peercoordinateroute, $requestQuery)) { ?>
                            <span title="<?php echo $result->peercoordinateroute ?>" class="font-size-22 cursor-default text-color-red">
                              &bull;
                            </span>
                          <?php } else { ?>
                            <span title="<?php echo $result->peercoordinateroute ?>" class="font-size-22 cursor-default text-color-green">
                              &bull;
                            </span>
                        <?php } ?>
                      </td>
                      <td class="text-center">
                        <?php if (false === strpos(
                          $result->peerremotescheme,
                          Yggverse\Parser\Url::is($requestQuery) ? Yggverse\Parser\Url::parse($requestQuery)->host->scheme : $requestQuery)) { ?>
                            <span title="<?php echo $result->peerremotescheme ?>" class="font-size-22 cursor-default text-color-red">
                              &bull;
                            </span>
                          <?php } else { ?>
                            <span title="<?php echo $result->peerremotescheme ?>" class="font-size-22 cursor-default text-color-green">
                              &bull;
                            </span>
                        <?php } ?>
                      </td>
                      <td class="text-center">
                        <?php if (false === strpos(
                          $result->peerremotehost,
                          Yggverse\Parser\Url::is($requestQuery) ? Yggverse\Parser\Url::parse($requestQuery)->host->name : $requestQuery)) { ?>
                            <span title="<?php echo $result->peerremotehost ?>" class="font-size-22 cursor-default text-color-red">
                              &bull;
                            </span>
                          <?php } else { ?>
                            <span title="<?php echo $result->peerremotehost ?>" class="font-size-22 cursor-default text-color-green">
                              &bull;
                            </span>
                        <?php } ?>
                      </td>
                      <td class="text-center">
                        <?php if (false === strpos(
                          $result->peerremoteport,
                          Yggverse\Parser\Url::is($requestQuery) ? (int) Yggverse\Parser\Url::parse($requestQuery)->host->port : $requestQuery)) { ?>
                            <span title="<?php echo $result->peerremoteport ?>" class="font-size-22 cursor-default text-color-red">
                              &bull;
                            </span>
                          <?php } else { ?>
                            <span title="<?php echo $result->peerremoteport ?>" class="font-size-22 cursor-default text-color-green">
                              &bull;
                            </span>
                        <?php } ?>
                      </td>
                      <td class="text-center">
                        <?php if (false === stripos($result->geocountryname, $requestQuery) &&
                                  false === stripos($result->geocountryisocode, $requestQuery)) { ?>
                            <span title="<?php echo $result->geocountryname ?> <?php echo $result->geocountryisocode ?>" class="font-size-22 cursor-default text-color-red">
                              &bull;
                            </span>
                          <?php } else { ?>
                            <span title="<?php echo $result->geocountryname ?> <?php echo $result->geocountryisocode ?>" class="font-size-22 cursor-default text-color-green">
                              &bull;
                            </span>
                        <?php } ?>
                      </td>
                      <td class="text-center">
                        <?php if (false === stripos($result->geocityname, $requestQuery)) { ?>
                            <span title="<?php echo $result->geocityname ?>" class="font-size-22 cursor-default text-color-red">
                              &bull;
                            </span>
                          <?php } else { ?>
                            <span title="<?php echo $result->geocityname ?>" class="font-size-22 cursor-default text-color-green">
                              &bull;
                            </span>
                        <?php } ?>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
                <?php if ($total >= WEBSITE_PEER_REMOTE_PAGINATION_LIMIT) { ?>
                  <tfoot>
                    <tr>
                      <td colspan="9" class="text-left">
                        <?php if ($requestPage > 1) { ?>
                          <a href="search.php?query=<?php echo urlencode($requestQuery) ?>&page=<?php echo $requestPage - 1 ?>"><?php echo _('&larr;') ?></a>
                        <?php } ?>
                        <?php echo sprintf(_('page %s'), $requestPage) ?>
                        <a href="search.php?query=<?php echo urlencode($requestQuery) ?>&page=<?php echo $requestPage + 1 ?>"><?php echo _('&rarr;') ?></a>
                      </td>
                    </tr>
                  </tfoot>
                <?php } ?>
              <?php } else { ?>
                <tfoot>
                  <tr>
                    <td class="text-center"><?php echo _('Results not found') ?></td>
                  </tr>
                </tfoot>
              <?php } ?>
            </table>
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