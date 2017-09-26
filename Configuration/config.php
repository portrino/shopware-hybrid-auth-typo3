<?php

return [
    'providers' => [
        'Typo3' => [
            'enabled' => true,
            'keys' => ['id' => '', 'secret' => ''],
            'urls' => ['apibase' => '', 'authorize' => '', 'token' => '', 'userprofile' => ''],
            'fields' => 'uid,name,first_name,last_name,email,address,zip,city,country,company'
        ],
    ]
];
