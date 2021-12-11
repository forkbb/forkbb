# ForkBB rev.43 Alpha Readme

## About

ForkBB is a free and open source forum software. The project is based on [FluxBB_by_Visman](https://github.com/MioVisman/FluxBB_by_Visman)

## Note

### Please DO NOT use this revision in production, it is for test only.
Many functions of the forum are not implemented.
No: plugins/extensions system, ...

## Requirements

* PHP 7.3+
* PHP extensions: pdo, intl, json, mbstring, fileinfo
* PHP extensions (desirable): gd or imagick (for upload avatars and other images)
* A database such as MySQL 5.5.3+, PostgreSQL 10+(?) (_Drivers for other databases are not realized now_)

## Install

### For Apache:

* Document Root == **public** folder (recommended):
  1. Rename public/**.dist.htaccess** to public/**.htaccess**,
  2. Rename public/**index.dist.php** to public/**index.php**;
* Document Root != **public** folder:
  1. Rename **.dist.htaccess** to **.htaccess**,
  2. Rename **index.dist.php** to **index.php**.

### For NGINX:

* [Example](https://github.com/forkbb/forkbb/blob/master/nginx.dist.conf) nginx configuration.
* Note: Root must point to the [**public/**](https://github.com/forkbb/forkbb/tree/master/public) directory.
* Note: The **index.dist.php** file does not need to be renamed.

## Links

* Development: https://github.com/forkbb/forkbb

## License

This project is under MIT license. Please see the [license file](LICENSE) for details.
