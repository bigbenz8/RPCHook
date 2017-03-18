<?php
/**
 * Created by PhpStorm.
 * User: zkf
 * Date: 17-3-15
 * Time: 下午1:05
 */

//返回微秒
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec).''.rand(1000,9999);
}

//连接数据库
function ssdb($ip, $port){
    $ssdb = '' ;
    try{
        $ssdb = new SimpleSSDB($ip, $port );
    }catch(SSDBException $e){
        return false ;
    }
    return $ssdb ;
}