<?php
/**
 * 发送及数据包处理过程
 *
 * User: zkf
 * Date: 17-3-14
 * Time: 下午1:44
 */
/**
 * swoole_http_cli 数据结构
 * /**
 * $cli 数据结构
 * array(
    [errCode] => 0
    [sock] => 15
    [host] => 127.0.0.1
    [port] => 80
    [headers] => Array
    (
    [server] => nginx/1.10.1
    [date] => Wed, 15 Mar 2017 10:42:05 GMT
    [content-type] => text/html; charset=UTF-8
    [transfer-encoding] => chunked
    [connection] => keep-alive
    [x-powered-by] => PHP/7.1.0
    [content-encoding] => gzip
    )
    [type] => 1025
    [requestHeaders] => Array
    (
    [Host] => test.local
    [User-Agent] => Chrome/49.0.2587.3
    [Accept] => text/html,application/xhtml+xml,application/xml
    [Accept-Encoding] => gzip
    )

    [setting] => Array
    (
    [timeout] => 10
    )

    [requestBody] =>
    [body] =>xxx
    [statusCode] => 200
    )
 */

$response->header("Content-Type", "text/html; charset=utf-8");
//从用户Post过来的数据包中分析目标地址及 数据
$parse_url = parse_url($req_post['url'] ) ;
$host = $parse_url['host'] ;
$req_path = $parse_url['path'] ;
$url_query = isset($parse_url['query']) ?  $parse_url['query'] : '' ;
$req_path = $url_query ? $req_path.'?'.$url_query : $req_path ;

//域名及IP映射数据
$domains = $hook_conf['hook_domain'] ;

//发起远程请求的超时时长 s
$req_timeout = $hook_conf['request_send_timeout'] ;

//根据Post包中的url 找到对应的IP 及哈希表
$server_ip = '' ;
$queue_name = '' ;
foreach ($domains as $k => $v) {
    if($k == $host) {
        $server_ip = $v['ip'] ;
        $queue_name = $v['queue_name'] ;
    }
}

//业务中post数据
$post_data = $req_post['post_data'] ;

//初始化Http_client
$cli = new swoole_http_client($server_ip, 80) ;
$cli->setHeaders([
    'Host' => $host ,
    "User-Agent" => $serv_conf['version'],
    'Accept' => 'text/html,application/xhtml+xml,application/xml',
    'Accept-Encoding' => 'gzip',
]);

//加料
$req_tm = microtime_float() ;

//重发次数,默认设置为1 ,在发送的回调中收到失败的情况下 直接进入重发管道
$req_post['_rtimes'] = 1 ;

//增加一个发送时间的标示,用于ssdb的SortedSet可以存放存在重复内容的数据包
$req_post['_rsendtm'] = $req_tm ;

//请求的id
$flag = 'id_'.$req_tm ;

//连接数据库
$ssdb =  ssdb($hook_conf['db']['ip'], $hook_conf['db']['port']) ;

//使用哈希表暂存请求数据包 在回调中删除, 重新发送无序
$ssdb -> hset($queue_name, $flag, json_encode($req_post) ) ;

//传弟给cli对象的参数 , 等下回调中要用到
$cli -> set(
    [
        'timeout'       => $req_timeout,
        'queue_name'    => $queue_name,
        'flag'          => $flag,
        'hook_config'   => $hook_conf,
    ]
) ;
//发送异步post请求
$cli->post($req_path, $post_data, function ($cli) {
    /**
     * 接收异步回调数据, 如果收到http状态码200 表示成功处理 , 并将数据从待发送的hashMap中删除
     * 若远程响应超时 或返回状态码不是200 ,则写入重发集合sorted set中
     */
    $hash_map_name = $cli -> setting['queue_name'] ;

    //数据ID
    $req_flag = $cli -> setting['flag'] ;

    $hook_conf = $cli -> setting['hook_config'] ;
    $ssdb =  ssdb($hook_conf['db']['ip'], $hook_conf['db']['port']) ;

    //成功返回
    if($cli -> errCode == 0 && $cli -> statusCode == 200 ){
        //
    }
    //远程返回错误, 将结果写入重发sortedSet中
    else{
        //取出post数据包
        $req_package = $ssdb -> hget($hash_map_name, $req_flag ) ;

        //重发sortedSet的名称
        $resend_sorted_set_name = $hook_conf['resend_set'] ;

        //编码
        $req_package = base64_encode($req_package ) ;

        //sorted set 的权重
        $set_val = time() + $hook_conf['hook_retry_delay_sec'][0] ;
        $ssdb ->zset($resend_sorted_set_name, $req_package, $set_val ) ;

    }

    //回调完成,删除暂存数据
    $ssdb -> hdel($hash_map_name, $req_flag ) ;

});

//请求返回值
$response -> end('ok') ;

