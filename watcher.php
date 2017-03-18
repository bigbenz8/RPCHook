<?php
/**
 * 监控服务,用于监控需要重发的集合
 * 此脚本暂时不支持多点布署 ,一般情况下发送失败需要重发的概率很小
 *
 * User: zkf
 * Date: 17-3-16
 * Time: 下午1:05
 */

//钩子配置
$hook_conf = require_once ('config/hook_conf.php') ;

//重发集合的名称
$resend_zset_name = $hook_conf['resend_set'] ;

//重发进程中暂存数据的队列名
$zset_progress_name = 'inprogress' ;

//Producer中所有发送数据包的暂存表
$hash_map_name_arr = array();

//http发送超时时间
$req_timeout = $hook_conf['request_send_timeout'] ;

require_once ('common/SSDB.php') ;
require_once ('common/func.php') ;
$ssdb =  ssdb($hook_conf['db']['ip'], $hook_conf['db']['port']) ;

/**
 * 先干点清理工作 ,把 $hash_map_name_arr 及 $zset_progress_name 中的所有数据
 * 都移到重发队列去
 */
//1. 操作inprogress集合
while(true) {
    if($ssdb -> zsize($zset_progress_name) > 0) {
        $move_size = 500 ;
        $move_arr = $ssdb -> zrange($zset_progress_name, 0 , $move_size) ;
        $ssdb -> multi_zset($resend_zset_name, $move_arr) ;
        $ssdb -> zpop_front($zset_progress_name, $move_size) ;
    }else{
        break ;
    }
}

//2. 操作发送暂存hashmap
foreach ($hook_conf['hook_domain'] as $_map) {
    $_hashmap_name  = $_map['queue_name'] ;
    $all_data = $ssdb -> hgetall($_hashmap_name) ;
    foreach ($all_data as $k => $v) {
        $ssdb -> zset($resend_zset_name, $v, time() ) ;
    }
    //array_push($hash_map_name_arr, $_map['queue_name']) ;
}

while(true) {
    if(!$ssdb)   {
        $ssdb =  ssdb($hook_conf['db']['ip'], $hook_conf['db']['port']) ;
    }
    //处理重发事务
    else{
        $now = time();

        //取出一坨需要重发的数据包
        $rsend_data = $ssdb -> zscan($resend_zset_name, '', '', $now, $hook_conf['retry_process_num'] ) ;
        if(!empty($rsend_data)) {
            foreach ($rsend_data as $k => $v) {

                //远程原始的post数据包
                $req_post = (array)json_decode(base64_decode($k) ) ;

                //业务post的数据
                $post_data = (array)json_decode($req_post['post_data'] ) ;

                //拼装数据,取出host 和ip
                $parse_url = parse_url($req_post['url'] ) ;
                $host = $parse_url['host'] ;
                $req_path = $parse_url['path'] ;
                $url_query = isset($parse_url['query']) ?  $parse_url['query'] : '' ;
                $req_path = $url_query ? $req_path.'?'.$url_query : $req_path ;
                $domains = $hook_conf['hook_domain'] ;
                $server_ip = '' ;
                foreach ($domains as $_k => $_v) {
                    if($_k == $host) {
                        $server_ip = $_v['ip'] ;
                    }
                }

                //这些值将在创建子进程时 传递给子进程
                $GLOBALS['server_ip'] = $server_ip ;
                $GLOBALS['host'] = $host ;
                $GLOBALS['req_timeout'] = $req_timeout ;
                $GLOBALS['resend_zset_name'] = $resend_zset_name ;
                $GLOBALS['hook_conf'] = $hook_conf ;
                $GLOBALS['k'] = $k ;
                $GLOBALS['req_path'] = $req_path ;
                $GLOBALS['post_data'] = $post_data ;

                //创建并发异步http客户端发送请求
                $process = new swoole_process('http_work_cli_async' , false);
                $pid = $process->start();
                //$workers[$pid] = $process;

                //移动到 inprogress的sortedSet中暂存,待回调删除
                $ssdb -> zset($zset_progress_name , $k , $v) ;
            }

            //删除已发送
            $ssdb->multi_zdel($resend_zset_name, array_keys($rsend_data) );
        }
    }

    //暂停500毫秒
    usleep(5*100*1000) ;
}

//工作子进程
function http_work_cli_async($worker ){
    $GLOBALS['worker'] = $worker ;
    $cli = new swoole_http_client($GLOBALS['server_ip'], 80) ;
    $cli->setHeaders([
        'Host' => $GLOBALS['host'] ,
        "User-Agent" => 'RPCHook-Cli',
        'Accept' => 'text/html,application/xhtml+xml,application/xml',
        'Accept-Encoding' => 'gzip',
    ]);
    $cli -> set(
        [
            'timeout'               => $GLOBALS['req_timeout'],
            'resend_queue_name'     => $GLOBALS['resend_zset_name'],
            'hook_config'           => $GLOBALS['hook_conf'],
            'key'                   => $GLOBALS['k'],
        ]
    ) ;

    //发送异步post请求
    $cli -> post($GLOBALS['req_path'], $GLOBALS['post_data'], function ($cli ){
        $worker = $GLOBALS['worker'] ;
        /**
         *接收异步回调数据, 如果收到http状态码200 表示成功处理
         *若远程响应超时 或返回状态码不是200 ,则再次进入重发队列 直到到达重发上限 则进入失败队列
         */
        $hash_map_name = $cli -> setting['resend_queue_name'] ;
        $hook_conf = $cli -> setting['hook_config'] ;
        $ssdb =  ssdb($hook_conf['db']['ip'], $hook_conf['db']['port']) ;

        //成功回调, 删除发送进程表中的记录
        $ssdb -> zdel('inprogress', $cli -> setting['key'] ) ;

        //成功返回
        if($cli -> errCode == 0 && $cli -> statusCode == 200 ){
            //重发成功
        }
        //远程返回错误, 将结果写入重发sortedSet中
        else{
            $req_data = (array)json_decode(base64_decode($cli -> setting['key']) ) ;
            $retry_time = $req_data['_rtimes'] ;
            $resend_set = $hook_conf['hook_retry_delay_sec'] ;
            if($retry_time >= count($resend_set)){
                //已超出重发设置的次数, 进入发送失败的队列
                $failed_sortedSet_name = 'fail_sortedSet' ;
                $req_data['_rsendtm'] = microtime_float() ;
                $req_data = base64_encode(json_encode($req_data) ) ;
                $ssdb -> zset($failed_sortedSet_name, $req_data, time() ) ;
            }
            //重新进入重发的队列
            else{
                $req_data['_rtimes'] = $req_data['_rtimes'] + 1;

                //重发时间
                $resend_tm = time()+ $resend_set[$retry_time] ;
                $req_data['_rsendtm'] = microtime_float();
                $req_data = base64_encode(json_encode($req_data) ) ;
                $ssdb -> zset($hash_map_name, $req_data, $resend_tm ) ;

            }

        }

        //bb进程退出
        $worker->exit(0) ;
    });
}

