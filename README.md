项目基于GatewayWorker实现的，GatewayWorker是基于php-cli开发的，所以运行该项目需要一些PHP扩展
pcntl、posix、event扩展

检测当前PHP环境是否满足运行标准
curl -Ss https://www.workerman.net/check | php
