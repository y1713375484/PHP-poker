<?php
namespace app\index\controller;
use support\Request;

class Index{


    public function index(Request $request){

        $id=$request->get("id");
        $room=$request->get("room");
        $websocket_url=getenv("websocket_url");
        return   view("index/index",["id"=>$id,"room"=>$room,"websocket_url"=>$websocket_url]);

    }




}