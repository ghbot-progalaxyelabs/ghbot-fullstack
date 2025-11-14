<?php

use App\Routes\HomeRoute;
use App\Routes\GithubWebhookRoute;

return [
    'GET' => [
        '/' => HomeRoute::class,
    ],
    'POST' => [
        '/websites' => \App\Routes\WebsitesRoute::class,
        '/webhook/github' => GithubWebhookRoute::class,
    ]
];
