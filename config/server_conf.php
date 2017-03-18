<?php
/**
 * 服务端的配置
 * User: zkf
 * Date: 17-3-14
 * Time: 下午1:47
 */
return array(
    //RPCHook版本
    'version'           => 'RPCHook-0.1.1',

    //服务监听的IP
    'listen_host'       => '127.0.0.1' ,

    //Server相关的详细设置
    //'reactor_num'       =>  2,
    'worker_num'        =>  50,
    'max_request'       =>  500,
    'max_conn'          =>  900,
    'task_worker_num'   =>  5,
    'task_max_request'  =>  500,
    'task_tmpdir'       =>  '/tmp',
    //'dispatch_mode'     =>  3,
    'log_file'          =>  '/tmp/swoole.log',
    //'heartbeat_check_interval'  =>  60,
    //'heartbeat_idle_time'       =>  600,
    //'open_cpu_affinity'         =>  1,
    //'open_tcp_nodelay'          =>  1,
    //'user'              =>  'root',
    //'group'             =>  'root',

    //服务监听的端口
    'listen_port'       => '9600' ,

    //RPCHook服务监听的请求地址
    'server_addr'       => '/producer.php',

    //后台运行时的PID  , 在swoole1.9以上版本中 支持热重启
    //'pid_file' => '/var/run/swoole.pid',

    //是否后台运行模式
    //'daemonize' => true,

    //是否解析POST数据 , 一般建议开启
    'http_parse_post' => true ,

    //http服务根目录限制 (http服务需要require的文件的根目录, 此处设置错误在收到请求的时候会报找不到文件 )
    'chroot' => '/var/www/swoole-test.local/',

) ;