# ForkBB rev 1 Pre-Alpha Readme

## About

ForkBB is an open source forum application. The project is based on [FluxBB_by_Visman](https://github.com/MioVisman/FluxBB_by_Visman)

## Note

### Do not use in production.
Many functions of the forum are not implemented.
No: private messages, voting, subscriptions, rss, plugins/extensions system.

## Requirements

* PHP 7.3+
* A database such as MySQL 5.5.3 or later (_Drivers for other databases are not realized now_)

## Install

* Document Root == **public** folder (recommended):
  1. Rename public/**.dist.htaccess** to public/**.htaccess**,
  2. Rename public/**index.dist.php** to public/**index.php**;
* Document Root != **public** folder:
  1. Rename **.dist.htaccess** to **.htaccess**,
  2. Rename **index.dist.php** to **index.php**.

## Links

* Development: https://github.com/forkbb/forkbb

## License

This project is under MIT license. Please see the [license file](LICENSE) for details.
