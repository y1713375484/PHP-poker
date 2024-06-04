<?php


namespace app\index\controller;
use GatewayWorker\Lib\Gateway;
use support\Redis;
use support\Request;
use Workerman\Lib\Timer;

class Api{


    /**
     * @param Request $request
     * 玩家加入房间
     */
    public function joinRoom(Request $request){
        $id=$request->post("id");

        $room=$request->post("room");
        $client_id=$request->post("client_id");
        $name=$request->post("name");//玩家昵称
        $number=getenv("gamer_number");//房间玩家人数 建议3-5人之内 人多可能影响游戏体验




        if (Gateway::isUidOnline($id)==0){
            //判断用户id是否被登录(0为不在线)


            if (Gateway::getClientCountByGroup($room)<$number){//一个房间人数不能大于3个人

//                if (!Redis::hExists($room,"state")){
//                    Redis::hSet($room,"state","0");//房间游戏状态为 0 未开始
//                }

                Gateway::joinGroup($client_id,$room);
                Gateway::bindUid($client_id,$id);//用户id绑定$client_id

                if (Redis::hExists($room,"gamerName")==0){
                    //如果没有就创建
                    Redis::hSet($room,"gamerName",json_encode([$id=>$name]));
                }else{
                    //有就取出，添加新值
                    $gamerName=json_decode(Redis::hGet($room,"gamerName"),true);
                    $gamerName[$id]=$name;
                    Redis::hSet($room,"gamerName",json_encode($gamerName));
                }

                if (Gateway::getClientCountByGroup($room)<$number){
                    $state=["type"=>"state","code"=>2];//房间人数还不足规定人数
                    Gateway::sendToGroup($room,json_encode($state));
                }else{
                    //$state=["type"=>"state","code"=>1,"number"=>"$number"];//人数够了可以开始了
                    $ArrayGroupids=array_keys(Gateway::getUidListByGroup($room));//当前房间用户id数组,只要键值
                    $ClientIdList=array_keys(Gateway::getClientIdListByGroup($room));
                    Game::init_poker($ArrayGroupids,$ClientIdList,$number,$room);//初始化牌局，将分好的牌装入redis
                    $gamerName=json_decode(Redis::hGet($room,"gamerName"),true);
                        $names=[
                            "type"=>"gamerName",
                            "names"=>$gamerName
                        ];
                    Gateway::sendToGroup($room,json_encode($names));//宣布房间内玩家昵称



                    /*发牌操作*/
                    /*循环给房间内玩家发牌*/
                    for ($i=0;$i<count($ArrayGroupids);$i++){
                        $array["poker"]=json_decode(Redis::Hget($room,$ArrayGroupids[$i]),true);
                        $array["type"]="send_poker";
                        $array["id"]=$ArrayGroupids[$i];
                        Gateway::sendToUid($ArrayGroupids[$i],json_encode($array));
                    }

                    $gamerArray=json_decode(Redis::hGet($room,"gamer"),true);//玩家列表

                    //给当前回合出牌玩家单独发送
                    $arrays=[
                        "type"=>"now_sent_poker",
                    ];
                    Gateway::sendToUid($gamerArray[0],json_encode($arrays));//游戏开始时出牌的是第一个玩家

                    Gateway::sendToGroup($room,json_encode(["type"=>"now_gamer","now_gamer_id"=>$gamerArray[0]]));//告诉房间玩家该谁出牌了


                }

            }else{

                $state=["type"=>"state","code"=>-1];//当前房间无法开启游戏
                Gateway::sendToClient($client_id,json_encode($state));
            }

        }else{
            $state=["type"=>"state","code"=>-2];//当前id以再现无法重新登录
            Gateway::sendToClient($client_id,json_encode($state));
        }



    }





    /**
     * 玩家主动断开连接
     * @param Request $request
     */
    public function close(Request $request){

        $room=$request->post("room");//房间号
        $client_id=$request->post("client_id");//连接id
        $id=$request->post("id");//用户id
        $rooms=Gateway::getAllGroupIdList();//获取服务器所有房间数


        $gamerList=json_decode(Redis::hGet($room,"gamer"),true);//获取玩家列表
        $ClientIdList=json_decode(Redis::hGet($room,"ClientIdList"),true);//获取房间内所有连接id
        if (Redis::exists($room)){
            //确保当前房间号键存在


                //$state = Redis::hGet($room, "state");//房间游戏状态

                    if (in_array($id,$gamerList)&&in_array($client_id,$ClientIdList)){
                        /*
                         *判断玩家是不是正在游戏中的玩家断开的连接
                         * 利用redis和$ClientIdList进行双重鉴权，确保断开连接是真正的游戏中玩家进行的操作
                         * 如果只用reids那么就会存在玩家冒用uid对游戏进行干扰，
                         * 如果只用ClientId那么真正的玩家断开连接ClientIdList数组中就已经没有断开连接玩家的ClientId了
                         */

                            if (in_array("$room", $rooms)) {//判断是否有当前房间，防止重复操作


                                $array = [
                                    "type" => "disband"//解散房间,游戏无法继续
                                ];
                                Gateway::sendToGroup($room, json_encode($array));//消息群发

                                foreach ($ClientIdList as $value){
                                    Gateway::closeClient($value);//断开当前房间内所有连接
                                }

                            }

                            Gateway::ungroup($room);//取消分组
                            Redis::del($room);//删除redis中房间信息


                    }

        }


    }

    /**
     * 取消准备
     * @param Request $request
     */
    public function cancel(Request $request){
        $room=$request->post("room");//房间号
        $id=$request->post("id");//用户id
        $client_id=$request->post("client_id");
        Gateway::leaveGroup($client_id,$room);
        Gateway::unbindUid($client_id,$id);//解除绑定

    }



    /**
     * 出牌
     * @param Request $request
     * @return \support\Response
     */
    public function sent_poker(Request $request){




        $room=$request->post("room");//房间号
        $id=$request->post("chk_player_id");//本次出牌的玩家id
        $chk_poker=$request->post("chk_poker");//玩家所出的牌



        $pokerArray=json_decode(Redis::hGet($room,$id),true);//先取出本次出牌玩家的牌，然后通过玩家所出的牌找出对应的牌的值

        $color_index_list=[];//当前玩家所出的牌的花色与值的对应数组

        for ($i=0;$i<count($chk_poker);$i++){
            $color_index_list[$chk_poker[$i]]=$pokerArray[$chk_poker[$i]];//牌的花色点数与牌值相对应
        }


        $prevPoker=json_decode(Redis::hGet($room,"prevPoker"),true);//取出上次的出牌






        //捕获方法体内的异常
        try {


                //根据出牌数量决定出牌类型
            if (count($chk_poker)==1){
                //出单牌

                Game::solo_poker($prevPoker, $color_index_list,$id);

            }else if (count($chk_poker)==2){
                //出对子

                Game::Double_poker($prevPoker, $color_index_list,$id);

            }else if (count($chk_poker)>=3){
                //3张以上的牌
                Game::Straight($prevPoker, $color_index_list,$id);

            }

        }catch (\Exception $exception){

            return  json(["code"=>-1,"message"=>$exception->getMessage()]);

        }





        /*满足出牌要求后清除上次的牌，把本次的牌放入*/
        $prevPoker= $color_index_list;
        asort($prevPoker);//根据数组中的值进行升序排序，会保留数组键



        Redis::hSet($room,"prevPoker",json_encode($prevPoker));
        Redis::hSet($room,"prevGamerId",$id);//上一个成功出牌的玩家id
        Redis::hSet($room,"roundRecording",0);//初始化回合记录
        //更新玩家当前手牌
        foreach ($chk_poker as $value){
            unset($pokerArray[$value]);//删除已经出过的牌
        }


        /*玩家无手牌了,游戏结束*/
        if (empty($pokerArray)){

            $game_over=[
                "type"=>"game_over",
                "victory_id"=>$id
            ];
            Gateway::sendToGroup($room,json_encode($game_over));//通知房间玩家游戏结束
            Gateway::ungroup($room);//房间解散
            Redis::del($room);//删除房间游戏信息

            return;

        }


        Redis::hSet($room,$id,json_encode($pokerArray));//重新设置玩家手牌



        $array=[
            "type"=>"sent_poker",
            "id"=>$id,
            "prevPoker"=>$prevPoker
        ];


        Gateway::sendToGroup($room,json_encode($array));//将玩家所出的牌发送给客户端





        /*给下一个出牌的玩家发送指令*/
        $gamerArray=json_decode(Redis::hGet($room,"gamer"),true);//玩家列表
        if ($id==end($gamerArray)){
            //如果当前玩家是玩家列表最后一位，那么下一个出牌的就是玩家列表的第一位
            $nextgamerId=$gamerArray[0];//下一个玩家的id，索引值为1
            $time=getenv("time");
        }else{
            $gamerIndex=array_search($id,$gamerArray);//在玩家列表中查询当前的玩家索引值

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
            "time"=>$time
        ];
        Gateway::sendToUid($nextgamerId,json_encode($array));

        Gateway::sendToGroup($room,json_encode(["type"=>"now_gamer","now_gamer_id"=>$nextgamerId]));//告诉房间玩家该谁出牌了



        $message=[
            "code"=>1,
        ];
        return json_encode($message);



    }


    /**
     * 不出牌
     * @param Request $request
     */
    public function not_poker(Request $request){
        
        $room=$request->post("room");
        $id=$request->post("id");

        $roundRecording=Redis::hGet($room,"roundRecording");
        $prevPoker=json_decode(Redis::hGet($room,"prevPoker"),true);//取出上次的出牌
        $gamer_number=json_decode(Redis::hGet($room,"gamer_number"),true);


        if (empty($prevPoker)){
            $message=[
                "code"=>-1,
                "message"=>"不能不出牌啊喂"
            ];

            return json_encode($message);

        }


        /*
         * 回合结束的判断就是总人数减去2个人
         * 掐头去尾
        */
        if ($roundRecording==$gamer_number-2){
            //回合结束
            $victory_id=Redis::hGet($room,"prevGamerId");//取出上回合胜利的玩家id
            Redis::hSet($room,"roundRecording",0);//重置回合记录
            $poker=json_decode(Redis::hGet($room,"poker"),true);//取出剩余牌
            $gamer_poker_list=json_decode(Redis::hGet($room,$victory_id),true);//上回合胜利玩家的手牌

            if (!empty($poker)){

                $key=array_key_last($poker);//取出剩余牌的最后一位键
                $value=$poker[$key];//获取值
                array_pop($poker);//弹出最后一个元素
                Redis::hSet($room,"poker",json_encode($poker));//将剩余的牌放回redis

                $gamer_poker_list[$key]=$value;//将新牌添加到玩家手牌数组中
                Redis::hSet($room,$victory_id,json_encode($gamer_poker_list));//放回redis

                $array["poker"]=[$key=>$value];
                $array["type"]="send_poker";

                Gateway::sendToUid($victory_id,json_encode($array));//发送一张牌给本回合胜利玩家
            }



            Redis::hSet($room,"prevPoker","");//把上次出牌内容重置，意味着新的一回合




            $array=[
                "type"=>"now_sent_poker",
            ];
            Gateway::sendToUid($victory_id,json_encode($array));//回合胜利者出牌

            Gateway::sendToGroup($room,json_encode(["type"=>"now_gamer","now_gamer_id"=>$victory_id]));//告诉房间玩家该谁出牌了



        }else{

            Redis::hSet($room,"roundRecording",$roundRecording+1);



            /*给下一个出牌的玩家发送指令*/
            $gamerArray=json_decode(Redis::hGet($room,"gamer"),true);//玩家列表
            if ($id==end($gamerArray)){
                //如果当前玩家是玩家列表最后一位，那么下一个出牌的就是玩家列表的第一位
                $nextgamerId=$gamerArray[0];//下一个玩家的id，索引值为1

            }else{
                $gamerIndex=array_search($id,$gamerArray);//在玩家列表中查询当前的玩家索引值

                if (!empty($prevPoker)){
                    //不是第一次出牌
                    $nextgamerId=$gamerArray[$gamerIndex+1];//下一个玩家就为当前玩家索引+1
                }else{
                    //是第一次出牌
                    $nextgamerId=$gamerArray[$gamerIndex];
                }


            }

            $array=[
                "type"=>"now_sent_poker",
                "time"=>getenv("time")
            ];
            Gateway::sendToUid($nextgamerId,json_encode($array));

            Gateway::sendToGroup($room,json_encode(["type"=>"now_gamer","now_gamer_id"=>$nextgamerId]));//告诉房间玩家该谁出牌了
        }

        $message=[
            "code"=>1,
        ];

        return json_encode($message);



    }







}