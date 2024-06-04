项目基于GatewayWorker实现的，GatewayWorker是基于php-cli开发的，所以运行该项目需要一些PHP扩展
pcntl、posix、event扩展

检测当前PHP环境是否满足运行标准

curl -Ss https://www.workerman.net/check | php

启动方式，修改env配置文件中的配置，并放通响应端口在项目跟目录执行

#运行

php start.php start

#守护进程

php start.php start -d

#结束程序

php start.php stop

