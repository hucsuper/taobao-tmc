## 概述
[淘宝消息服务 TMC](https://open.taobao.com/doc.htm?docId=101663&docType=1) 的 PHP 版本，是基于 laravel+workerman 框架实现。

## 特性
- 基于 laravel 框架
- 使用 workerman 的 websocket client 实现
- 可配置化
- 支持多app_key应用

## 使用要求

- PHP 7.2+

- workerman 4.1+

- laravel 5.5+

  

配置参数说明：

| 配置项          | 类型          | 说明                     | 默认值         |
|:-------------|:------------|:-----------------------|:------------|
| uri          | string      | 淘宝 ws 链接               | ws://mc.api.taobao.com |
| app_key      | string      | 应用 app_key             | 30          |
| app_secret   | string      | 应用 app_secret          | 30          |
| group   | string      | 分组名      | default     |
| handler      | string      | 消息处理类                  |             |

## 使用

- 启动连接

  ```
  php artisan tmc:client start {连接名称}

#### 创建一个 handle 类，并实现 MessageHandlerInterface

```php
<?php

namespace Hucsuper\TaobaoTmc\Handler;

use Hucsuper\TaobaoTmc\Message\Message;

class CrmMessageHandler implements MessageHandlerInterface
{

    public function handle(Message $message): void
    {
        // 处理逻辑
    }
}
```
## 参考

https://github.com/JeaNile/hyperf-taobao-tmc/tree/master
