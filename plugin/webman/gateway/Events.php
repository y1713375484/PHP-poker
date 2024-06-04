<?php

namespace plugin\webman\gateway;

use GatewayWorker\Lib\Gateway;
use support\Redis;
use Workerman\Timer;

class Events
{

    /**
     * 进程触发时
     * @param $worker
     */
    public static function onWorkerStart($worker){



    }

    /**
     * 连接触发
     * @param $client_id
     */
    public static function onConnect($client_id){

        /**
         * 初始化操作
         * 当与客户端取得连接后发送 client_id
         */
        Gateway::sendToClient($client_id, json_encode(array(
            'type'      => 'init',
            'client_id' => $client_id
        )));


    }



    /**
     * 接收到消息时
     * @param $client_id
     * @param $message
     */
    public static function onMessage($client_id, $message){

        $message=json_decode($message,true);
        switch ($message['type']){
            case "now_sent_poker":
                   //print_r("当前玩家id为：".$message['id'].PHP_EOL);

                    $time_id= Timer::add(getenv("time"), function($message){

                        //print_r("定时器中当前玩家id为：".$message['id'].PHP_EOL);
                        $roundRecording=Redis::hGet($message['room'],"roundRecording");
                        $prevPoker=json_decode(Redis::hGet($message['room'],"prevPoker"),true);//取出上次的出牌
                        $gamer_number=json_decode(Redis::hGet($message['room'],"gamer_number"),true);

                        if (empty($prevPoker)){
                            return ;
                        }


                       /*
                         * 回合结束的判断就是总人数减去2个人
                         * 掐头去尾
                        */
                        if ($roundRecording==$gamer_number-2){
                            //回合结束
                            $victory_id=Redis::hGet($message['room'],"prevGamerId");//取出上回合胜利的玩家id
                            Redis::hSet($message['room'],"roundRecording",0);//重置回合记录
                            $poker=json_decode(Redis::hGet($message['room'],"poker"),true);//取出剩余牌
                            $gamer_poker_list=json_decode(Redis::hGet($message['room'],$victory_id),true);//上回合胜利玩家的手牌
                            if (!empty($poker)){

                                $key=array_key_last($poker);//取出剩余牌的最后一位键
                                $value=$poker[$key];//获取值
                                array_pop($poker);//弹出最后一个元素
                                Redis::hSet($message['room'],"poker",json_encode($poker));//将剩余的牌放回redis

                                $gamer_poker_list[$key]=$value;//将新牌添加到玩家手牌数组中
                                Redis::hSet($message['room'],$victory_id,json_encode($gamer_poker_list));//放回redis

                                $array["poker"]=[$key=>$value];
                                $array["type"]="send_poker";

                                Gateway::sendToUid($victory_id,json_encode($array));//发送一张牌给本回合胜利玩家
                            }



                            Redis::hSet($message['room'],"prevPoker","");//把上次出牌内容重置，意味着新的一回合




                            $array=[
                                "type"=>"now_sent_poker",
                            ];
                            Gateway::sendToUid($victory_id,json_encode($array));//回合胜利者出牌

                            Gateway::sendToUid($message['id'],json_encode(['type'=>'skip']));

                            Gateway::sendToGroup($message['room'],json_encode(["type"=>"now_gamer","now_gamer_id"=>$victory_id]));//告诉房间玩家该谁出牌了



                        }else{

                            Redis::hSet($message['room'],"roundRecording",$roundRecording+1);



                            /*给下一个出牌的玩家发送指令*/
                            $gamerArray=json_decode(Redis::hGet($message['room'],"gamer"),true);//玩家列表
                            if ($message['id']==end($gamerArray)){
                                //如果当前玩家是玩家列表最后一位，那么下一个出牌的就是玩家列表的第一位
                                $nextgamerId=$gamerArray[0];//下一个玩家的id，索引值为1
                                $time=getenv("time");

                            }else{
                                $gamerIndex=array_search($message['id'],$gamerArray);//在玩家列表中查询当前的玩家索引值

                                if (!empty($prevPoker)){
                                    //不是第一次出牌
                                    $nextgamerId=$gamerArray[$gamerIndex+1];//下一个玩家就为当前玩家索引+1
                                    $time=getenv("time");
                                }else{
                                    //是第一次出牌
                                    $nextgamerId=$gamerArray[$gamerIndex];
                                    $time=null;
                                }


                            }

                            $array=[
                                "type"=>"now_sent_poker",
                                "time"=>$time,
                            ];
                            Gateway::sendToUid($nextgamerId,json_encode($array));

                            Gateway::sendToUid($message['id'],json_encode(['type'=>'skip']));

                            Gateway::sendToGroup($message['room'],json_encode(["type"=>"now_gamer","now_gamer_id"=>$nextgamerId]));//告诉房间玩家该谁出牌了
                        }

                            //print_r("定时器中玩家id：".$message['id'].PHP_EOL);



                        $time_id_list=json_decode(Redis::hGet($message['room'],"time_id"),true);
                        $time_id=array_shift($time_id_list);
                        Timer::del($time_id);
                        Redis::hSet($message['room'],"time_id",json_encode($time_id_list));


                    },array($message),false);


                    if (!Redis::hExists($message['room'],"time_id")){
                        //如果是第一次
                        $time_id_list[0]=$time_id;
                        Redis::hSet($message['room'],"time_id",json_encode($time_id_list));

                    }else{
                        $time_id_list=json_decode(Redis::hGet($message['room'],"time_id"),true);
                        array_push($time_id_list,$time_id);
                        Redis::hSet($message['room'],"time_id",json_encode($time_id_list));

                    }







            return;

            case "action":
                /*执行出牌或不出牌后即可销毁当前定时器，防止跳过出牌操作*/
                $time_id_list=json_decode(Redis::hGet($message['room'],"time_id"),true);
                $time_id=array_shift($time_id_list);
                //print_r("删除的定时器id为：". $time_id.PHP_EOL);
                Timer::del($time_id);

                Redis::hSet($message['room'],"time_id",json_encode($time_id_list));
            return;



        }



    }


    /**
     * 与客户端断开连接时
     * @param $client_id
     */
    public static function onClose($client_id){




    }

}
