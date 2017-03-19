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

//curl获取数据
function curl_post($url, $post_data, $ua) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    // post数据
    curl_setopt($ch, CURLOPT_POST, 1);
    // post的变量
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output ;
}

