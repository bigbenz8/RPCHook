<?php
/**
 * Created by PhpStorm.
 * User: zkf
 * Date: 17-3-14
 * Time: 下午1:47
 */
return array(
    //发送失败之后的重试时间间隔 ,单位 秒
    'hook_retry_delay_sec'    => array(
        //5, 30, 300, 3600, 86400,
        15, 20,
    ),

    //指定内网机器的域名和IP地址
    'hook_domain'   => array(
            'test.local'           => array(
                'ip'            =>  '127.0.0.1',
                'queue_name'    =>  'hm_test',
            ),
    ),

    //发送http请求的超时时间(s)
    'request_send_timeout'      => 10,

    //db配置
    'db'    => array(
        'ip'    => '127.0.0.1' ,
        'port'  => 8888,
    ),

    //重试发送的并发进程数
    'retry_process_num'     => 50 ,

    //重发集合表的名称 (服务开始后就不能更改了)
    'resend_set'        => 'resend' ,

    //慢响应记录
    'slow_log'      => 1,
    //响应超过这个值 将被记录到日志 单位(微秒)
    'slow_log_tm'   => 500000,
    //慢响应记录的周期 m(按月生成), d(按天生在). 日志放在 sortedSet中 命名类似 slow_log_q_2017-02-11 ,或 slow_log_q_2017-02
    'slow_log_cron' => 'm',

    //是否记录所有的请求
    'log_req'       => 0,
    //日志放在 sortedSet中 命名类似 slow_log_q_2017-02-11 ,或 slow_log_q_2017-02
    'log_req_cron'  => 'd',

) ;