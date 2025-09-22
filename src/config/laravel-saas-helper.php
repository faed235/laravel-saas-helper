<?php


return [
    //apifox 相关参数
    'apifox'=>[
        'apifox_project_id'=> env('APIFOX_PROJECT_ID'),
        'apifox_version'=> env('APIFOX_VERSION'),
        'apifox_token'=> env('APIFOX_TOKEN'),
    ],

    //系统通知
    'sys_notice'=>[
        //企业微信
        'wechat'=>[
            'enable'=>env('WECHAT_ENABLE',false),
            'debug'=>env('WECHAT_DEBUG'),
            'info'=>env('WECHAT_INFO'),
            'error'=>env('WECHAT_ERROR'),
            'emergency'=>env('WECHAT_EMERGENCY'),
        ],
    ],

    'log'=>[
        'http_channel'=>'http',
        'extra_headers_to_log'=>[],
    ]
];