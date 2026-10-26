<?php


return [
    'secret'      => env('JWT_SECRET'),
    //Asymmetric key
    'public_key'  => env('JWT_PUBLIC_KEY'),
    'private_key' => env('JWT_PRIVATE_KEY'),
    'password'    => env('JWT_PASSWORD'),
    //JWT time to live（单位：秒）
    'ttl'         => env('JWT_TTL', 7200),
    //Refresh time to live（单位：分钟）
    'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),
    //JWT hashing algorithm
    'algo'        => env('JWT_ALGO', 'HS256'),
    //token获取方式，数组靠前值优先（前后端分离只用 header）
    'token_mode'    => ['header'],
    //黑名单后有效期
    'blacklist_grace_period' => env('BLACKLIST_GRACE_PERIOD', 10),
    // 黑名单存储驱动，TP8 使用 Cache::delete，必须使用 Tp6 驱动
    'blacklist_storage' => thans\jwt\provider\storage\Tp6::class,
];
