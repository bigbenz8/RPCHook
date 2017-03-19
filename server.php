<?php
/**
 * RPCHook的服务端, 前端请使用Nginx或其它代理做转发. 支持多机布署
 * RPCHook保证数据不丢失, 但不保证存在重复发送的情况
 * RPCHook接收到的数据,第一次为实时转发 但不保证转发请求到达的有序性
 * RPCHook转发数据后,收到远程返回的Http状态码为200时 即认为成功送达
 * 在第一次请求失败后,RPCHook会根据hoook_conf.php的设置做重复发送,最后一次扔不成功的 记录到发送失败的有序集合中
 *
 * RPCHook只接收请求过来的如下格式的数据包:
 * $_POST = array('url'=>xx, 'type'=>'async', 'post_data'=>xx) ;
 *
 * User: zkf
 * Date: 17-3-12
 * Time: 下午12:05
 */

$root_dir = dirname(__FILE__) ;
$GLOBALS['root_dir'] = $root_dir ;

//服务默认配置, 如果config/server_conf.php中没有配置 则采用此处的配置, 有配置则覆盖
$serv_conf = array(
    'listen_host'       => '127.0.0.1',
    'listen_port'       => 9600,
    'server_addr'       => '/producer.php',
) ;

//SSDB数据库封装的函数
require_once $root_dir.'/common/SSDB.php' ;

//常用函数库
require_once $root_dir.'/common/func.php' ;

//载入服务配置文件e
$serv_conf_file = $root_dir.'/config/server_conf.php' ;
if(file_exists($serv_conf_file) ) {
    $serv_conf = require_once ($serv_conf_file );
}
$GLOBALS['serv_conf'] = $serv_conf ;

//初始化服务
$http = new swoole_http_server($serv_conf['listen_host'], $serv_conf['listen_port']) ;
$http->set($serv_conf );
$GLOBALS['http_server'] = $http ;

//请求分发, 默认监听 /producer.php , 可自行在配置文件config/server_conf.php 中修改
$http -> on('request', function($request, $response){
    $http = $GLOBALS['http_server'] ;
    $serv_conf = $GLOBALS['serv_conf'] ;

    //钩子配置文件
    $hook_conf_file = '/config/hook_conf.php' ;
    $hook_conf = require ($hook_conf_file) ;

    //客户端请求过来的地址
    $req_uri = $request -> server['path_info'] ;

    //请求过来的post值
    $req_post = $request -> post ;
    $GLOBALS['req_post'] = $req_post ;

    switch ($req_uri) {
        case $serv_conf['server_addr'] :
            require '/worker/producer.php';
            break ;
        default:
            $response->header("Content-Type", "text/html; charset=utf-8") ;
            $response -> end('error') ;
    }

}) ;

//task_worker开始
function task_start($http_serv, $tid, $worker_id, $data) {
    echo 'task_start . tid is : '.$tid  ;

    $http_serv -> finish("rtn data") ;
    //使用return结束task
    return 0;
}

//task_worker结束
function task_finish($http_serv, $tid, $data) {
    echo 'task_finish' ;

}

//此处的task_worker用来作为数据库连接池使用
$http -> on('task', 'task_start');
$http -> on('finish', 'task_finish') ;

//启动服务
$http -> start();
