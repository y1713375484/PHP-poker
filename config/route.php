<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Route;


Route::group("/api",function (){
   Route::post("/joinRoom",[\app\index\controller\Api::class,"joinRoom"])->name("joinRoom");
    Route::post("/close",[\app\index\controller\Api::class,"close"])->name("close");
    Route::post("/cancel",[\app\index\controller\Api::class,"cancel"])->name("cancel");
    Route::post("/sent_poker",[\app\index\controller\Api::class,"sent_poker"])->name("sent_poker");
    Route::post("/not_poker",[\app\index\controller\Api::class,"not_poker"])->name("not_poker");

});





