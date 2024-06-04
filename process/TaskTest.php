<?php


namespace process;
use Workerman\Connection\TcpConnection;
use Workerman\Crontab\Crontab;
use Workerman\Timer;

class TaskTest{

    public function onWorkerStart(){
        date_default_timezone_set('PRC');

//        Timer::add(10, function(){
//                print_r("定时器正在执行");
//        });

        new Crontab('1 * * * * *', function(){
            echo date('Y-m-d H:i:s')."\n";
        });


    }
}