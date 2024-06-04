<?php


namespace app\index\controller;


use GatewayWorker\Lib\Gateway;
use support\Redis;
use think\Exception;

class Game{


    /**
     * 牌局初始化,将牌加载到redis
     * @param $ArrayGroupids
     * @param $gamer_number
     * @param $room
     */
    public static function  init_poker($ArrayGroupids,$ClientIdList,$gamer_number,$room){


        /**
         * 没有大小王,2是最大的牌,可以管任意牌
         */


        $poker=[
            "♠A"=>1,"♠2"=>2,"♠3"=>3,"♠4"=>4,"♠5"=>5,"♠6"=>6,"♠7"=>7,"♠8"=>8,"♠9"=>9,"♠10"=>10,"♠J"=>11,"♠Q"=>12,"♠K"=>13,
            "♣A"=>1,"♣2"=>2,"♣3"=>3,"♣4"=>4,"♣5"=>5,"♣6"=>6,"♣7"=>7,"♣8"=>8,"♣9"=>9,"♣10"=>10,"♣J"=>11,"♣Q"=>12,"♣K"=>13,
            "♥A"=>1,"♥2"=>2,"♥3"=>3,"♥4"=>4,"♥5"=>5,"♥6"=>6,"♥7"=>7,"♥8"=>8,"♥9"=>9,"♥10"=>10,"♥J"=>11,"♥Q"=>12,"♥K"=>13,
            "♦A"=>1,"♦2"=>2,"♦3"=>3,"♦4"=>4,"♦5"=>5,"♦6"=>6,"♦7"=>7,"♦8"=>8,"♦9"=>9,"♦10"=>10,"♦J"=>11,"♦Q"=>12,"♦K"=>13,
        ];


        /**
         * shuffle($poker);
         *这里用shuffle函数键值无法打乱，且会变成数字，
         */
        if (!empty($poker)) {
            $key = array_keys($poker);
            shuffle($key);
            foreach ($key as $value) {
                $arr2[$value] = $poker[$value];

            }
            $poker = $arr2;
        }

        $key = array_keys($poker);//提取键
        $game_poker=[];
        //每个玩家依次发牌，一次发1张，发够5张停止
        for ($i=0;$i<5;$i++){

            for($s=0;$s<$gamer_number;$s++){
                /*
                 *  $key中的值是$poker的键，下标是一一对应的
                 * 所以用array_shift($key)返回的是其$poker对应的键
                 * array_shift($poker)返回的是$poker对应的值
                */
                $game_poker[$ArrayGroupids[$s]][array_shift($key)]=array_shift($poker);
                asort($game_poker[$ArrayGroupids[$s]]);//根据数组中的值进行升序排序，会保留数组键
            }

        }

        $game_poker[$ArrayGroupids[0]][array_shift($key)]=array_shift($poker);//最后再给一号玩家也就是庄多发一张牌
        asort( $game_poker[$ArrayGroupids[0]]);//需要给1号玩家重新排序
        $game_poker["poker"]=$poker;//将剩余的牌放到尾部




        for ($v=0;$v<$gamer_number;$v++){

            //Redis::set($ArrayGroupids[$v],json_encode($game_poker[$ArrayGroupids[$v]]));//玩家的id作为key，值是玩家对应的手牌
            Redis::hset($room,$ArrayGroupids[$v],json_encode($game_poker[$ArrayGroupids[$v]]));//房间号为键,玩家号为哈希结构的键，值是玩家对应的手牌
        }
        Redis::hSet($room,"poker",json_encode($game_poker["poker"]));//剩余的牌
        Redis::hSet($room,"state","1");//房间状态 1是游戏中  0是未开始游戏
        Redis::hSet($room,"gamer",json_encode($ArrayGroupids));//保存玩家列表
        Redis::hSet($room,"ClientIdList",json_encode($ClientIdList));//房间连接列表
        Redis::hSet($room,"gamer_number",$gamer_number);




    }

    




    
    public static  function solo_poker($prevPoker, $color_index_list,$id){


        if (!empty($prevPoker)){
            //如果不是第一次出牌

            $prevPoker_index= (array_values($prevPoker))[0];//获取上一个玩家所出牌的对应值
            $index_list=(array_values($color_index_list))[0];//获取当前玩家所出牌的对应值


            if (count($prevPoker)!=count($color_index_list)){
                //确保与上一个玩家出的牌型一致
                throw new \Exception("你瞅瞅人家出了几张，你出了几张");
            }

                if ($prevPoker_index==2){
                    //上一个玩家出的2
                    throw new \Exception("人家出的2你还出个毛啊");
                }else if ($index_list!=2){
                    //如果当前玩家出的牌不是2

                    if ($prevPoker_index==13){
                        //上一个玩家出的是K那么当前玩家就必须得出A
                        if ($index_list!=1){
                            throw new \Exception("人家出的是K，你出的是什么玩意");
                        }


                    }else if ($prevPoker_index!=$index_list-1){
                        //当前玩家出的牌必须比上一个玩家大一个点才行
                        throw new \Exception("没2就歇着吧");
                    }



                }

        }

    }

    /**
     * 对子
     * @param $prevPoker 上一个玩家的牌
     * @param $color_index_list 出的牌
     * @param $id 出牌人id
     */
    public static function Double_poker($prevPoker, $color_index_list,$id){

        $index_list=array_values($color_index_list);//获取当前玩家所出牌的对应值数组



        if ($index_list[0]!=$index_list[1]){
            //得确保出的是对子才行
            throw new \Exception("你瞅瞅你出的是对子么？");
        }


        if (!empty($prevPoker)){
            //如果不是第一次出牌

            $prevPoker_index= array_values($prevPoker);//获取上一个玩家所出牌的对应值数组

            if (count($prevPoker_index)!=count($index_list)){
                //确保与上一个玩家出的牌型一致
                throw new \Exception("你瞅瞅人家出了几张，你出了几张");
            }

            //确保是对子后，只需要对比一张牌点数即可

            if ($prevPoker_index[0]==2){
                //上一个玩家出的2
                throw new \Exception("人家出的对2你还出个毛啊");
            }else if ($index_list[0]!=2){
                //如果当前玩家出的牌不是2

                if ($prevPoker_index[0]==13){
                    //上一个玩家出的是K那么当前玩家就必须得出A
                    if ($index_list[0]!=1){
                        throw new \Exception("人家出的是对K，你出的是什么玩意");
                    }


                }else if ($prevPoker_index[0]!=$index_list[0]-1){
                    //当前玩家出的牌必须比上一个玩家大一个点才行
                    throw new \Exception("没对2就歇着吧");
                }



            }

        }

    }

    /**
     * 3张或3张以上的连牌
     * @param $prevPoker 上一个玩家的牌
     * @param $color_index_list 出的牌
     * @param $id 出牌人id
     */
    public static function Straight($prevPoker, $color_index_list,$id){


        $index_list=array_values($color_index_list);//获取当前玩家所出牌的对应值数组
        sort($index_list);//升序排序




        $boom=self::boom($index_list);
        $sz=self::sz($index_list,count($index_list));


        if ($boom==-1){
            //不是炸弹
            if ($sz==-1){
                //不是顺子

                throw new \Exception("你这牌又不是顺子又不是炸弹，你出了个啥？");

            }

        }


        if (!empty($prevPoker)){
            //如果不是第一次出牌
            $prevPoker_index=array_values($prevPoker);//获取上一玩家所出牌的对应值数组
            //sort($prevPoker_index);//无需排序了，因为在存储之前的时候就已经排序过了

            //判断上一次出牌类型
            $prev_boom=self::boom($prevPoker_index);
            $prev_sz=self::sz($prevPoker_index,count($prevPoker_index));

            //上一玩家出的炸弹
            if ($prev_boom==1){

                //先判断当前玩家是否出的是炸弹且炸弹牌的数量是否大于等于上一玩家
                if ($boom==1){
                    //出的是炸 但是数量小于上一玩家
                    if (count($index_list)<count($prevPoker_index)){
                        throw new \Exception("你这炸弹威力小了点");
                    }else if ((count($index_list)==count($prevPoker_index))){
                        //数量相等那么就判断牌的值
                        if ($prevPoker_index[0]==2){
                            //判断上一玩家是不是出的2炸
                        throw new \Exception("人家出的2炸，你怕是炸不过啊");
                        }

                        //入果当前玩家出的不是2炸
                        if ($index_list[0]!=2){

                            if ($prevPoker_index[0]==1){
                                //入果当前玩家出的不是2炸，上一个玩家出的A炸，那我就无法出牌
                                throw new \Exception("没2炸就忍忍吧，A炸也不小了");

                            }else if ($prevPoker_index[0]==13){
                                //入果当前玩家出的不是2炸，上一个玩家出的k炸，那我就必须出A炸
                                if ($index_list[0]!=1){
                                    throw new \Exception("你这炸弹威力小了点");
                                }
                            }else{
                                //其余情况
                                if ($prevPoker_index[0]>$index_list[0]){

                                    throw new \Exception("亲，这边建议你换个威力大的炸弹重新试试");
                                }

                            }
                        }

                    }

                }else{
                    //当前玩家出的不是炸
                    throw new \Exception("没炸弹就歇着吧");
                }

            }


            //上一个玩家出的顺子
            if ($prev_sz==1){
                if (in_array(1,$prevPoker_index)){
                    //判断是不是包含A的特殊顺子类似于 ...J Q K A
                    if ($boom==-1){
                    //这种顺子只有炸弹才可以
                        throw new \Exception("顺子界天花板你怎么要？");
                    }
                }

                if ($sz==1){
                    //如果上一个玩家和当前都出的是顺子
                    if (count($prevPoker_index)==count($index_list)){
                        //当前玩家与上一个玩家的牌长度一样

                        if (array_sum($index_list)-array_sum($prevPoker_index)!=count($index_list)){
                            /*
                             * 例子：上一位玩家如果出3、4、5 那么当前玩家不出炸弹就必须出 4、5、6
                             * 3 4 5 和为 12     4 5 6和为 15
                             * 4 5 6 7 和为 22    5 6 7 8 和为 26
                             * 规律：如果当前玩家出的牌符合规则
                             * 那么,当前玩家所出牌的点数之和,减上一位玩家出牌的点数之和,就等于当前玩家出牌数(当前玩家和上一位玩家出牌数量相等),
                             * 反之不等于出牌数量就说明不符合规则
                             */

                            throw new \Exception("不符合顺子出牌规则哦");

                        }

                    }else{
                        //当前玩家与上一个玩家的牌长度不一样
                        throw new \Exception("你这顺子和人家的顺子长度怎么不一样呢？");
                    }



                }






            }



            //上一个玩家如果出的既不是顺子又不是炸弹，那么当前出的只能是炸弹
            if ($prev_boom==-1&&$prev_sz==-1){

                if ($boom==-1){
                    throw new \Exception("出牌不合规");
                }

            }


        }

    }


    /**
     * 判断是否是炸弹
     * @param poker_list
     */
    public static function boom($poker_list){

        $sum=0;
        for ($i=1;$i<count($poker_list);$i++){

            if ($poker_list[0]!=$poker_list[$i]){
                $sum++;
            }

        }

        if ($sum==0){
            return 1;//是炸弹
        }else{
            return -1;//不是炸弹
        }


    }


    /**
     * 判断是否是顺子
     * @param $poker_list
     * @param $list_num 数组长度
     */
    public static function sz($poker_list,$list_num){

        array_unique($poker_list);//去处重复牌

        if (count($poker_list)!=$list_num){
            //有重复牌不是顺子
            return -1;
        }


        if (in_array(2,$poker_list)){
            //2无法当顺子使用
            return -1;
        }

        /*
         * 特殊的顺子
         * 类似与 ...J Q K A 但是不能 A 2 3 4....
        */
        //判断有没有A
        if (in_array(1,$poker_list)){
            //判断有没有k
            if (in_array(13,$poker_list)){

                //因为已经排序过那么A肯定为数组第一位就是0，所以只需判断其他牌是否是连续数字，从1开始判断
                for ($i=1;$i<$list_num-1;$i++){

                    if ($poker_list[$i]+1!=$poker_list[$i+1]){
                         return -1; //不是连续数段
                    }

                }

                return 1;

            }else{
                //如果有A但是没K那说明这个顺子不符合游戏规则
                return -1;
            }

        }else{
            //不是特殊顺子那么就直接判断是不是连续数段

            for ($i=0;$i<$list_num-1;$i++){

                if ($poker_list[$i]+1!=$poker_list[$i+1]){
                    return -1; //不是连续数段
                }

            }
            return 1;
        }



    }

}