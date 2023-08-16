# YGGstate
Yggdrasil Network Explorer

#### Overview

![Dashboard](https://github.com/YGGverse/YGGstate/blob/main/media/dashboard-page.png?raw=true)

https://github.com/YGGverse/YGGstate/tree/main/media

#### Online instances

* [http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yggstate/](http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yggstate/)
* [http://94.140.114.241/yggstate/](http://94.140.114.241/yggstate/)

#### Requirements

```
php8^
php-pdo
php-mysql
php-memcached
memcached
sphinxsearch
```
#### Database model

![Database model](https://github.com/YGGverse/YGGstate/blob/main/media/db-prototype.png?raw=true)

#### Installation

* `git clone https://github.com/YGGverse/YGGstate.git`
* `cd YGGstate`
* `composer install`

### Setup
* Server configuration `/example/environment`
* The web root dir is `/src/public`
* Deploy the database using [MySQL Workbench](https://www.mysql.com/products/workbench) project presented in the `/database` folder
* Install [Sphinx Search Server](https://sphinxsearch.com)
* Install [GeoLite2 DB](https://www.maxmind.com) to `/src/storage` or provide alternative path in configuration file
* Configuration examples presented at `/config` folder
* Set up the `/src/crontab` by following [example](https://github.com/YGGverse/YGGstate/blob/main/%20example/environment%20/crontab)

#### Contribute

Please make new branch for each PR

```
git checkout main
git checkout -b my-pr-branch-name
```

#### Donate to contributors

* @d47081: [BTC](https://www.blockchain.com/explorer/addresses/btc/bc1qngdf2kwty6djjqpk0ynkpq9wmlrmtm7e0c534y) | [LTC](https://live.blockcypher.com/ltc/address/LUSiqzKsfB1vBLvpu515DZktG9ioKqLyj7) | [XMR](835gSR1Uvka19gnWPkU2pyRozZugRZSPHDuFL6YajaAqjEtMwSPr4jafM8idRuBWo7AWD3pwFQSYRMRW9XezqrK4BEXBgXE) | [ZEPH](ZEPHsADHXqnhfWhXrRcXnyBQMucE3NM7Ng5ZVB99XwA38PTnbjLKpCwcQVgoie8EJuWozKgBiTmDFW4iY7fNEgSEWyAy4dotqtX) | [DOGE](https://dogechain.info/address/D5Sez493ibLqTpyB3xwQUspZvJ1cxEdRNQ) | Support our server by order [Linux VPS](https://www.yourserver.se/portal/aff.php?aff=610)

#### License
* Engine sources [MIT License](https://github.com/YGGverse/YGGstate/blob/main/LICENSE)

#### Feedback

Feel free to [share](https://github.com/YGGverse/YGGstate/issues) your ideas and bug reports!

#### Community

* [Mastodon](https://mastodon.social/@YGGverse)
* [[matrix]](https://matrix.to/#/#YGGstate:matrix.org)

#### See also

* [YGGo - YGGo! Distributed Web Search Engine ](https://github.com/YGGverse/YGGo)
* [YGGwave ~ The Radio Catalog](https://github.com/YGGverse/YGGwave)
* [PHP library to build JS-less graphs](https://github.com/YGGverse/graph-php)