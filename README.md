LinkCache - 一个灵活高效的PHP缓存工具库
-----------------
LinkCache 是一个PHP编写的灵活高效的缓存工具库，提供多种缓存驱动支持，包括Memcache、Memcached、Redis、SSDB、文件缓存等。通过LinkCache可以使不同缓存驱动拥有统一的操作体验，同时又可发挥不同缓存驱动各自的优势。LinkCache支持缓存object和array，同时为防止产生惊群现象做了优化。

composer 安装
------------
LinkCache 可以通过 composer 安装，使用以下命令从 composer 下载安装 LinkCache:

``` bash
$ composer require dongnan/linkcache
```
