# LinkCache - 一个灵活高效的PHP缓存工具库

LinkCache 是一个PHP编写的灵活高效的缓存工具库，提供多种缓存驱动支持，包括Memcache、Memcached、Redis、SSDB、文件缓存、APC、YAC等。通过LinkCache可以使不同缓存驱动实现操作统一，同时又可发挥不同缓存驱动各自的优势。LinkCache支持缓存 `object` 和 `array`，同时为防止产生惊群现象做了优化。

# 环境要求

- PHP >= 5.4
- 使用 Memcache 缓存需要安装[Memcache扩展](http://pecl.php.net/package/memcache)
- 使用 Memcached 缓存需要安装[Memcached扩展](http://pecl.php.net/package/memcached)
- 使用 Redis 缓存需要安装[Redis扩展](http://pecl.php.net/package/redis)
- 使用 APC 缓存需要安装[APC扩展](http://pecl.php.net/package/APC)
- 使用 YAC 缓存需要安装[YAC扩展](http://pecl.php.net/package/yac)

# 安装

## composer 安装
LinkCache 可以通过 `composer` 安装，使用以下命令从 `composer` 下载安装 LinkCache

```bash
$ composer require dongnan/linkcache
```

## 手动下载安装
### 下载地址
- 在 `Git@OSC` 下载 http://git.oschina.net/dongnan/LinkCache/tags
- 在 `GitHub` 下载 https://github.com/dongnan/LinkCache/releases

### 安装方法
在你的入口文件中引入
```php
<?php 
	//引入 LinkCache 的自动加载文件
	include("path_to_linkcache/autoload.php");
```

# 如何使用

(提示:如果以下锚点定位的链接错误，导致页面找不到等问题，请访问 https://github.com/dongnan/LinkCache 查看文档)

- [config](#config) - 配置信息
- [instance](#instance) - 缓存实例化
- [getDriver](#getdriver) - 获取缓存驱动实例
- [set](#set) - 将参数中的 `value`设置为 `key` 的值
- [setnx](#setnx) - 当缓存中不存在 `key` 时，将参数中的 `value` 设置为 `key` 的值
- [setDE](#setde) - 将参数中的 `value`设置为 `key` 的值，`key` 将自动延迟过期
- [get](#get) - 获取 `key` 对应的值
- [getDE](#getde) - 获取 `key` 对应的值，与 `setDE` 配合使用
- [del](#del) - 删除 `key`
- [has](#has) - 判断 `key` 是否存在
- [hasDE](#hasde) - 判断延迟过期的 `key` 理论上是否存在
- [ttl](#ttl) - 获取 `key` 的生存时间(单位:s)
- [ttlDE](#ttlde) - 获取延迟过期的 `key` 理论上的生存时间(单位:s)
- [expire](#expire) - 设置一个 `key` 的生存时间(单位:s)
- [expireDE](#expirede) - 以延迟过期的方式设置一个 `key` 的生存时间(单位:s)
- [expireAt](#expireat) - 用UNIX时间戳设置一个 `key` 的过期时间
- [expireAtDE](#expireatde) - 以延迟过期的方式用UNIX时间戳设置一个 `key` 的过期时间
- [persist](#persist) - 删除一个 `key` 的生存时间，使其永不过期
- [lock](#lock) - 对 `key` 设置锁标记（此锁并不对 `key` 做修改限制,仅为 `key` 的锁标记）
- [isLock](#islock) - 判断 `key` 是否有锁标记
- [unLock](#unlock) - 移除 `key` 的锁标记
- [incr](#incr) - 设置 `key` 的值按整数递增
- [incrByFloat](#incrbyfloat) - 设置 `key` 的值按浮点数递增
- [decr](#decr) - 设置 `key` 的值按整数递减
- [mSet](#mset) - 批量设置多个 `key` 对应的值
- [mSetNX](#msetnx) - 当缓存中不存在 `key` 时，批量设置多个 `key` 对应的值
- [mGet](#mget) - 获取所有给定 `key` 的值
- [mHas](#mhas) - 批量判断 `key` 是否存在
- [mDel](#mdel) - 批量删除 `key`

## config

配置信息

```php
<?php 
	//设置缓存配置, 使用 array_merge 的方式合并到默认配置中
	\linkcache\Cache::setConfig($config);
	
	//获取配置信息
	$config = \linkcache\Cache::getConfig();
	//获取指定缓存驱动的配置信息
	$config = \linkcache\Cache::getConfig('redis');

	//默认配置信息
	[
		//默认使用的缓存驱动
		'default' => 'files',
        //当前缓存驱动失效时，使用的备份驱动
        'fallback' => 'files',
        'memcache' => [
            'servers' => [
                ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 1, 'persistent' => true, 'timeout' => 1, 'retry_interval' => 15, 'status' => true],
            ],
            'compress' => ['threshold' => 2000, 'min_saving' => 0.2],
        ],
        'memcached' => [
            'servers' => [
                ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 1],
            ],
            //参考 Memcached::setOptions
            'options' => [],
        ],
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'database' => '',
            'timeout' => ''
        ],
        'ssdb' => [
            'host' => '127.0.0.1',
            'port' => 8888,
            'password' => '',
            'timeoutms' => ''
        ],
	]
```

## instance

实例化缓存对象

```php
<?php
	//直接new一个
	$cache = new \linkcache\Cache();
	//通过getInstance获取
	$cache = \linkcache\Cache::getInstance();

	//根据驱动类型实例化,支持的驱动:redis,memcache,memcached,ssdb,files,apc,yac
	$cache = new \linkcache\Cache('files');
	//或者
	$cache = \linkcache\Cache::getInstance('files');

	//传入配置参数实例化
	$cache = new \linkcache\Cache('redis', ['host' => '127.0.0.1', 'port' => 6379]);
```

## getDriver

获取缓存驱动实例

所有缓存驱动都必须实现 `linkcache\interfaces\driver\Base` 接口的方法，获取到缓存驱动实例后，可直接使用缓存驱动的方法，包括缓存驱动中没有定义但缓存驱动扩展对象中已定义的方法。

## set

将参数中的 `value` 设置为 `key` 的值

#### 参数
- key - 字符串
- value	- 除了 `resource` 类型的所有的值
- time - (可选参数) `key` 的生存时间，单位是秒(s)

#### 返回值
Boolean 如果设置成功，返回 `true`; 如果设置失败，返回 `false`

#### 例子

```php
<?php
	//设置不过期的缓存
	$status = $cache->set($key, $value);
	//设置有过期时间的缓存
	$status = $cache->set($key, $value, $time);
```

## setnx

当缓存中不存在 `key` 时，将参数中的 `value` 设置为 `key` 的值

#### 参数
- key - 字符串
- value	- 除了 `resource` 类型的所有的值
- time - (可选参数) `key` 的生存时间，单位是秒(s)

#### 返回值
Boolean - 如果设置成功，返回 `true`; 如果设置失败，返回 `false`

#### 例子

```php
<?php
	//设置不过期的缓存
	$status = $cache->setnx($key, $value);
	//设置有过期时间的缓存
	$status = $cache->setnx($key, $value, $time);
```

## setDE

将参数中的 `value`设置为 `key` 的值，`key` 将自动延迟过期

#### 参数
- key - 字符串
- value	- 除了 `resource` 类型的所有的值
- time - `key` 的生存时间，单位是秒(s)
- delayTime - (可选参数) 延迟过期时间，如果未设置，则使用配置中的设置

#### 返回值
Boolean - 如果设置成功，返回 `true`; 如果设置失败，返回 `false`

#### 例子

```php
<?php
	//设置不过期的缓存
	$status = $cache->setDE($key, $value);
	//设置有过期时间的缓存
	$status = $cache->setDE($key, $value, $time);
	//设置有过期时间的缓存并指定延迟过期时间
	$status = $cache->setDE($key, $value, $time, $delayTime);
```

## get

获取 `key` 对应的值

#### 参数
- key - 字符串

#### 返回值
Mixed - `key` 对应的值; 如果获取失败或 `key` 不存在，返回 `false`

#### 例子

```php
<?php
	//获取key对应的值
	$value = $cache->get($key);
```

## getDE

获取 `key` 对应的值，与 `setDE` 配合使用

#### 参数
- key - 字符串
- isExpired - `key` 理论上是否已经过期，这是一个引用变量，不需要初始化

#### 返回值
Mixed - `key` 对应的值; 如果获取失败或 `key` 不存在，返回 `false`

#### 例子

```php
<?php
	//获取key对应的值
	$value = $cache->getDE($key, $isExpired);
	//如果已经过期，处理其他逻辑
	if ($isExpired) {
		//TODO
	}
```

## del

删除 `key`

#### 参数
- key - 字符串

#### 返回值
Boolean - 如果删除成功，返回 `true`; 如果删除失败，返回 `false`。**注意：** 当 `key` 不存在时，也会返回 `true`

#### 例子

```php
<?php
	//删除key
	$status = $cache->del($key);
```

## has

判断 `key` 是否存在

#### 参数
- key - 字符串

#### 返回值
Boolean - 如果 `key` 存在，返回 `true`；如果 `key` 不存在，返回 `false`

#### 例子

```php
<?php
	//判断key是否存在
	$status = $cache->has($key);
```

## hasDE

判断延迟过期的 `key` 理论上是否存在。设置自动延期的 `key`，过期时间实际会被修改，用方法 `has` 判断的话，可能 `key` 仍然存在，但是理论上已经过期了

#### 参数
- key - 字符串

#### 返回值
Boolean - 如果 `key` 存在，返回 `true`；如果 `key` 不存在，返回 `false`

#### 例子

```php
<?php
	//判断key是否存在
	$status = $cache->hasDE($key);
```

## ttl

获取 `key` 的生存时间(单位:s)

#### 参数
- key - 字符串

#### 返回值
Mixed - 生存剩余时间(单位:秒) `-1` 表示永不过期,`-2` 表示 `key` 不存在,失败返回 `false`

#### 例子

```php
<?php
	//获取key的生存时间
	$ttl = $cache->ttl($key);
```

## ttlDE

获取延迟过期的 `key` 理论上的生存时间(单位:s)。

#### 参数
- key - 字符串

#### 返回值
Mixed - 生存剩余时间(单位:秒) `-1` 表示永不过期,`-2` 表示 `key` 不存在,失败返回 `false`

#### 例子

```php
<?php
	//获取延迟过期的 key 理论上的生存时间(单位:s)
	$ttl = $cache->ttlDE($key);
```

## expire

设置一个 `key` 的生存时间(单位:s)

#### 参数
- key - 字符串
- time - 整数，`key` 的生存时间(单位:s)

#### 返回值
Boolean - 如果设置成功，返回 `true`; 如果设置失败或 `key` 不存在，返回 `false`

#### 例子

```php
<?php
	//设置一个key的生存时间
	$status = $cache->expire($key, $time);
```

## expireDE

以延迟过期的方式设置一个 `key` 的生存时间(单位:s)

#### 参数
- key - 字符串
- time - 整数，`key` 的生存时间(单位:s)
- delayTime - (可选参数) 延迟过期时间，如果未设置，则使用配置中的设置

#### 返回值
Boolean - 如果设置成功，返回 `true`; 如果设置失败或 `key` 不存在，返回 `false`

#### 例子

```php
<?php
	//以延迟过期的方式设置一个 key 的生存时间(单位:s)
	$status = $cache->expireDE($key, $time);
	//自定义延迟过期时间
	$status = $cache->expireDE($key, $time, $delayTime);
```

## expireAt

用UNIX时间戳设置一个 `key` 的过期时间

#### 参数
- key - 字符串
- time - UNIX时间戳(单位:s)

#### 返回值
Boolean - 如果设置成功，返回 `true`; 如果设置失败或 `key` 不存在，返回 `false`

#### 例子

```php
<?php
	//用UNIX时间戳设置一个key的过期时间
	$status = $cache->expireAt($key, $time);
```

## expireAtDE

以延迟过期的方式用UNIX时间戳设置一个 `key` 的过期时间

#### 参数
- key - 字符串
- time - UNIX时间戳(单位:s)
- delayTime - (可选参数) 延迟过期时间，如果未设置，则使用配置中的设置

#### 返回值
Boolean - 如果设置成功，返回 `true`; 如果设置失败或 `key` 不存在，返回 `false`

#### 例子

```php
<?php
	//用UNIX时间戳设置一个key的过期时间
	$status = $cache->expireAtDE($key, $time);
	//自定义延迟过期时间
	$status = $cache->expireAtDE($key, $time, $delayTime);
```

## persist

删除一个 `key` 的生存时间，使其永不过期

#### 参数
- key - 字符串

#### 返回值
Boolean - 如果设置成功，返回 `true`; 如果设置失败或 `key` 不存在，返回 `false`

#### 例子

```php
<?php
	//删除一个key的生存时间，使其永不过期
	$status = $cache->persist($key);
```

## lock

对 `key` 设置锁标记（此锁并不对 `key` 做修改限制,仅为 `key` 的锁标记）

#### 参数
- key - 字符串
- time - 整数，`key锁标记` 的生存时间(单位:s)

#### 返回值
Boolean - 如果设置成功，返回 `true`; 如果 `key` 已存在或设置失败，返回 `false`

#### 例子

```php
<?php
	//对key设置锁标记
	$status = $cache->lock($key, $time);
```

## isLock

判断 `key` 是否有锁标记

#### 参数
- key - 字符串

#### 返回值
Boolean - 如果有锁标记，返回 `true`; 如果没有锁标记或判断失败，返回 `false`

#### 例子

```php
<?php
	//判断key是否有锁标记
	$status = $cache->isLock($key);
```

## unLock

移除 `key` 的锁标记

#### 参数
- key - 字符串

#### 返回值
Boolean - 如果移除成功，返回 `true`; 如果失败，返回 `false`

#### 例子

```php
<?php
	//移除key的锁标记
	$status = $cache->unLock($key);
```

## incr

设置 `key` 的值按整数递增

#### 参数
- key - 字符串
- step - (可选参数) 整数，递增步长，默认值为 `1`，可以为负值

#### 返回值
Mixed - 递增后的值，失败返回 `false`，如果 `key` 不存在，则按 `step` 设置新值

#### 例子

```php
<?php
	//设置key的值按整数递增
	$value = $cache->incr($key, $step);
```

## incrByFloat

设置 `key` 的值按浮点数递增

#### 参数
- key - 字符串
- float - 浮点数，递增步长，可以为负值

#### 返回值
Mixed - 递增后的值，失败返回 `false`，如果 `key` 不存在，则按 `float` 设置新值

#### 例子

```php
<?php
	//设置key的值按浮点数递增
	$value = $cache->incrByFloat($key, $float);
```

## decr

设置 `key` 的值按整数递减

#### 参数
- key - 字符串
- step - (可选参数) 整数，递减步长，默认值为 `1`，可以为负值

#### 返回值
Mixed - 递减后的值，失败返回 `false`，如果 `key` 不存在，则按 `-step` 设置新值

#### 例子

```php
<?php
	//设置key的值按整数递减
	$value = $cache->decr($key, $step);
```

## mSet

批量设置多个 `key` 对应的值

#### 参数
- sets - `key` 和 `value` 组成的键值对数组

#### 返回值
Boolean - 如果设置成功，返回 `true`; 如果设置失败，返回 `false`

#### 例子

```php
<?php
	$sets = [
		'key1' => 'value1',
		'key2' => 'value2',
		'key3' => 'value3',
	];
	//批量设置多个key对应的值
	$status = $cache->mSet($sets);
```

## mSetNX

当缓存中不存在 `key` 时，批量设置多个 `key` 对应的值

#### 参数
- sets - `key` 和 `value` 组成的键值对数组

#### 返回值
Boolean - 如果设置成功，返回 `true`; 如果设置失败，返回 `false`

#### 例子

```php
<?php
	$sets = [
		'key1' => 'value1',
		'key2' => 'value2',
		'key3' => 'value3',
	];
	//当缓存中不存在key时，批量设置多个key对应的值
	$status = $cache->mSetNX($sets);
```

## mGet

获取所有给定 `key` 的值

#### 参数
- keys - 多个 `key` 组成的数组 

#### 返回值
array - 参数 `keys` 中的所有 `key` 与对应的 `value` 组成的数组，如果 `key` 不存在或是获取失败，对应的 `value` 值为 `false`

#### 例子

```php
<?php
	$keys = ['key1', 'key2', 'key3'];
	//获取所有给定key的值
	$status = $cache->mGet($keys);
```

## mHas

批量判断 `key` 是否存在

#### 参数
- keys - 多个 `key` 组成的数组 

#### 返回值
array - 返回存在的 `key` 的数组，如果判断失败返回 `false`

#### 例子

```php
<?php
	$keys = ['key1', 'key2', 'key3'];
	//批量判断key是否存在
	$hasKeys = $cache->mHas($keys);
```

## mDel

批量删除 `key`

#### 参数
- keys - 多个 `key` 组成的数组 

#### 返回值
Boolean - 如果删除成功，返回 `true`; 如果删除失败，返回 `false`

#### 例子

```php
<?php
	$keys = ['key1', 'key2', 'key3'];
	//批量删除key
	$status = $cache->mDel($keys);
```

# 如何防止惊群

## 什么是惊群
首先，我们使用缓存的主要目的就是为了高并发情况下的高可用，换句话说，在使用了缓存的高并发的系统下，如果缓存突然都消失了，会发生什么？首先数据库的压力必然骤增，接着负载迅速升高，响应时间变慢，进程/线程由于响应时间变慢导致集压并剧增，这就是惊群。惊群的后果就是系统不可用甚至宕机。当然这是一个非常极端的例子，但是能很好的解释惊群现象。真实环境中，可能会有个一些页面是访问非常频繁的，如果这些页面的缓存在同一时间或相近的时间突然过期了，在高并发的情况下也会产生惊群现象。

## 如何避免惊群
知道了什么是惊群，就不难防止惊群了。首先，避免缓存在同一时间过期；其次，如果缓存过期，不要让所有并发的进程/线程都去触发更新同一缓存数据。

### 例子

`LinkCache` 为此专门做了优化，使用 `LinkCache` 提供的一些特殊方法，可以有效的防止惊群现象。以下是简单的示例(伪代码)：

```php
<?php
	/* LinkCache专为防止惊群现象设计的方法包括：
	 * setDE,getDE,hasDE,ttlDE,expireDE,expireAtDE,lock,isLock,unLock
	 * 比较常用的是:setDE,getDE,lock
	 */
	
	//设置缓存
	$cache->setDE($key,$value,$time);

	//获取缓存
	$value = $cache->getDE($key,$isExpired);

	//情况1，$value存在，$isExpired=false，不需要更新缓存
	if($value !== false){
		//your code
	}

	//情况2，$value存在，$isExpired=true，需要更新缓存
	if($value !== false){
		//your code
	}
	//更新缓存
	if($isExpired === true){
		//对key加锁标记，如果成功，则处理更新缓存操作 (锁标记默认过期时间是60s，也可自定义过期时间)
		if($cache->lock($key)){
			//更新缓存
		}
	}
	
	//情况3，$value不存在，需要更新缓存
	if($value === false){
		//对key加锁标记，如果成功，则处理更新缓存操作 (锁标记默认过期时间是60s，也可自定义过期时间)
		if($cache->lock($key)){
			//更新缓存
		}
		//如果失败，说明已经有进程/线程对key加了锁标记，正在更新缓存
		else{
			//如果并发大，建议此处做特殊处理，不要去更新缓存
			
			//特殊处理的代码
		}
	}
```

# 默认情况说明

- [Cache](#cache) - `\linkcache\Cache`
- [drivers](#drivers)
	- [files](#files) - `\linkcache\drivers\Files`
	- [memcache](#memcache) - `\linkcache\drivers\Memcache`
	- [memcached](#memcached) - `\linkcache\drivers\Memcached`
	- [redis](#redis) - `\linkcache\drivers\Redis`
	- [ssdb](#ssdb) - `\linkcache\drivers\Ssdb`
	- [apc](#apc) - `\linkcache\drivers\Apc`
	- [yac](#yac) - `\linkcache\drivers\Yac`

## cache

- 默认使用的缓存驱动： `files`
- 默认使用的备用驱动： `files`
- `memcache`、`redis`、`ssdb` 等缓存驱动的默认配置均参考官方默认配置
- 自定义配置支持非驱动类型的key，但配置信息中需要有 `driver_type` 属性，否则会抛异常，示例如下：
```php
<?php
	use \linkcache\Cache;
	$config = [
		'redis_m_db_1' => [
			'driver_type' => 'redis',
			'host' => '127.0.0.1',
			'port' => 6380,
			'database' => 1
		],
		'redis_m_db_2' => [
			'driver_type' => 'redis',
			'host' => '127.0.0.1',
			'port' => 6380,
			'database' => 2
		],
		'redis_s_db' => [
			'driver_type' => 'redis',
			'host' => '127.0.0.1',
			'port' => 6381
		]
	];
	Cache::setConfig($config);
	//根据自定义配置实例化
	$redisM1 = new Cache('redis_m_db_1');
	$redisM2 = new Cache('redis_m_db_2');
	$redisS0 = new Cache('redis_s_db');
```

## drivers

- 所有驱动默认使用备用缓存
- 备用缓存优先使用实例化时构造函数传入的配置 `config` 中的 `fallback` 定义，如果没有定义，则使用 `\linkcache\Cache::$config` 中的 `fallback` 定义

### files

- 默认保存路径：优先使用上传文件临时目录，未定义则使用系统临时目录，并在目录下创建linkcache目录，作为 `files` 驱动的默认保存路径，代码如下：
  `(ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir()) . '/linkcache'`

### memcache

- 当 `memcache` 连接断开后，最大重连次数为3次，重新建立连接后，连接重试次数清零

### memcached

- 当 `memcached` 连接断开后，最大重连次数为3次，重新建立连接后，连接重试次数清零

### redis

- 当 `redis` 连接断开后，最大重连次数为3次，重新建立连接后，连接重试次数清零

### ssdb

- 当 `ssdb` 连接断开后，最大重连次数为3次，重新建立连接后，连接重试次数清零

### apc

- 无

### yac

- 可自定义缓存 `key` 前缀，默认无 `key` 前缀

# 开发

如果你觉得LinkCache还不错，但又不支持你想用的缓存驱动，不妨尝试在LinkCache新增该缓存驱动的支持。

## 1.增加新的缓存驱动

目前有两种方式可以方便的开发新的缓存驱动支持

- 继承 `linkcache\abstracts\DriverSimple` 抽象类
	1. 在 `src/linkcache/drivers` 目录下创建新的缓存驱动类 `Example.php`
		```php
		<?php
			/**
			 * linkcache - 一个灵活高效的PHP缓存工具库
			 *
			 * @author      Dong Nan <hidongnan@gmail.com>
			 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
			 * @link        http://git.oschina.net/dongnan/LinkCache
			 * @license     BSD (http://opensource.org/licenses/BSD-3-Clause)
			 */

			namespace linkcache\drivers;
			use linkcache\abstracts\DriverSimple;

			class Example extends DriverSimple {
			
			    /**
			     * 构造函数
			     * @param array $config 配置
			     */
			    public function __construct($config = []) {
			        $this->init($config);
					//TODO 完善这个方法
			    }
			
			    /**
			     * 检查驱动是否可用
			     * @return boolean      是否可用
			     */
			    public function checkDriver() {
			        //TODO 实现这个方法
			    }
			
			    /**
			     * 设置键值
			     * @param string $key
			     * @param string $value
			     * @return boolean
			     */
			    protected function setOne($key, $value) {
					//TODO 实现这个方法
					
			    }
			
			    /**
			     * 获取键值
			     * @param string $key
			     * @return mixed
			     */
			    protected function getOne($key) {
					//TODO 实现这个方法
			    }
			
			    /**
			     * 删除键值
			     * @param string $key
			     * @return boolean
			     */
			    protected function delOne($key) {
					//TODO 实现这个方法
			    }
			
			}

		```
	2. 实现 `linkcache\abstracts\DriverSimple` 抽象类中的抽象方法

- 实现接口 `linkcache\interfaces\driver\Base` (Base接口是必须的，你也可以实现更多的接口:Incr,Lock,Multi)
	1. 在 `src/linkcache/drivers` 目录下创建新的缓存驱动类 `Example.php`
		```php
		<?php
			/**
			 * linkcache - 一个灵活高效的PHP缓存工具库
			 *
			 * @author      Dong Nan <hidongnan@gmail.com>
			 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
			 * @link        http://git.oschina.net/dongnan/LinkCache
			 * @license     BSD (http://opensource.org/licenses/BSD-3-Clause)
			 */
			
			namespace linkcache\drivers;
			use linkcache\interfaces\driver\Base;
			
			class Example implements Base {
			
			    use \linkcache\traits\CacheDriver;
			
			    /**
			     * 构造函数
			     * @param array $config 配置
			     */
			    public function __construct($config = []) {
			        $this->init($config);
					//TODO 完善这个方法
			    }
			
			    /**
			     * 检查驱动是否可用
			     * @return boolean      是否可用
			     */
			    public function checkDriver() {
			        //TODO 实现这个方法
			    }
			
			    /**
			     * 设置键值
			     * @param string $key   键名
			     * @param mixed $value  键值
			     * @param int $time     过期时间,默认为-1,<=0则设置为永不过期
			     * @return boolean      是否成功
			     */
			    public function set($key, $value, $time = -1) {
			        //TODO 实现这个方法
			    }
			
			    /**
			     * 当键名不存在时设置键值
			     * @param string $key   键名
			     * @param mixed $value  键值
			     * @param int $time     过期时间,默认为-1,<=0则设置为永不过期
			     * @return boolean      是否成功
			     */
			    public function setnx($key, $value, $time = -1) {
			        //TODO 实现这个方法
			    }
			
				/**
			     * 设置键值，将自动延迟过期;<br>
			     * 此方法用于缓存对过期要求宽松的数据;<br>
			     * 使用此方法设置缓存配合getDE方法可以有效防止惊群现象发生
			     * @param string $key    键名
			     * @param mixed $value   键值
			     * @param int $time      过期时间，<=0则设置为永不过期
			     * @param int $delayTime 延迟过期时间，如果未设置，则使用配置中的设置
			     * @return boolean       是否成功
			     */
			    public function setDE($key, $value, $time, $delayTime = null) {
					//TODO 实现这个方法
				}

			    /**
			     * 获取键值
			     * @param string $key   键名
			     * @return mixed|false  键值,失败返回false
			     */
			    public function get($key) {
			        //TODO 实现这个方法
			    }
			
				/**
			     * 获取延迟过期的键值，与setDE配合使用;<br>
			     * 此方法用于获取setDE设置的缓存数据;<br>
			     * 当isExpired为true时，说明key已经过期，需要更新;<br>
			     * 更新数据时配合isLock和lock方法，防止惊群现象发生
			     * @param string $key       键名
			     * @param boolean $isExpired 是否已经过期
			     * @return mixed|false      键值,失败返回false
			     */
			    public function getDE($key, &$isExpired = null) {
					//TODO 实现这个方法
				}

			    /**
			     * 删除键值
			     * @param string $key   键名
			     * @return boolean      是否成功
			     */
			    public function del($key) {
			        //TODO 实现这个方法
			    }
			
			    /**
			     * 是否存在键值
			     * @param string $key   键名
			     * @return boolean      是否存在
			     */
			    public function has($key) {
			        //TODO 实现这个方法
			    }
			
			    /**
			     * 判断延迟过期的键值理论上是否存在
			     * @param string $key   键名
			     * @return boolean      是否存在
			     */
			    public function hasDE($key) {
					//TODO 实现这个方法
				}

			    /**
			     * 获取生存剩余时间
			     * @param string $key   键名
			     * @return int|false    生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在,失败返回false
			     */
			    public function ttl($key) {
			        //TODO 实现这个方法
			    }
			
				/**
			     * 获取延迟过期的键值理论生存剩余时间
			     * @param string $key   键名
			     * @return int|false    生存剩余时间(单位:秒) -1表示永不过期,-2表示键值不存在,失败返回false
			     */
			    public function ttlDE($key) {
					//TODO 实现这个方法
				}

			    /**
			     * 设置过期时间
			     * @param string $key   键名
			     * @param int $time     过期时间(单位:秒)。不大于0，则设为永不过期
			     * @return boolean      是否成功
			     */
			    public function expire($key, $time) {
			        //TODO 实现这个方法
			    }

				/**
			     * 以延迟过期的方式设置过期时间
			     * @param string $key    键名
			     * @param int $time      过期时间(单位:秒)。不大于0，则设为永不过期
			     * @param int $delayTime 延迟过期时间，如果未设置，则使用配置中的设置
			     * @return boolean       是否成功
			     */
			    public function expireDE($key, $time, $delayTime = null) {
					//TODO 实现这个方法
				}
			
			    /**
			     * 移除指定键值的过期时间
			     * @param string $key   键名
			     * @return boolean      是否成功
			     */
			    public function persist($key) {
			        //TODO 实现这个方法
			    }
			
			}
		```
	2. 实现 `linkcache\interfaces\driver\Base` 接口中的方法

## 2.测试新增的驱动

为了保证代码的可靠性，不妨对新增的驱动做个测试吧。
我使用的是PHPUnit做的测试，版本：4.8.15

- 测试步骤
	1. 在 `tests` 目录新增测试类 `TestDriverExample.php` 
		```php
		<?php
			/**
			 * linkcache - 一个灵活高效的PHP缓存工具库
			 *
			 * @author      Dong Nan <hidongnan@gmail.com>
			 * @copyright   (c) Dong Nan http://idongnan.cn All rights reserved.
			 * @link        http://git.oschina.net/dongnan/LinkCache
			 * @license     BSD (http://opensource.org/licenses/BSD-3-Clause)
			 */
			
			namespace linkcacheTests;
			
			/**
			 * TestDriverExample
			 */
			class TestDriverExample extends TestDriverFiles
			{
			    protected $cacheDriver = 'example';
			}
		```
	2. 进入LinkCache项目目录，执行测试命令
		```
		phpunit --bootstrap autoload_dev.php tests/TestDriverExample
		```
		显示以下信息测试就通过啦~
		```
		PHPUnit 4.8.15 by Sebastian Bergmann and contributors.

		....................
		
		Time: 8.24 seconds, Memory: 8.75Mb

		OK (26 tests, 105 assertions)
		```

# LICENSE

使用非常灵活宽松的 [New BSD License](http://opensource.org/licenses/BSD-3-Clause) 协议



