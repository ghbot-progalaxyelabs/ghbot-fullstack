<?php

use App\Routes\HomeRoute;
use App\Routes\GithubWebhookRoute;
use App\Routes\GetWebsiteRoute;
use App\Routes\UpdateWebsiteRoute;
use App\Routes\AuthRoute;

return [
    'GET' => [
        '/' => HomeRoute::class,
        '/websites/:id' => GetWebsiteRoute::class,
    ],
    'POST' => [
        '/auth/google-signin' => AuthRoute::class,
        '/websites' => \App\Routes\WebsitesRoute::class,
        '/webhook/github' => GithubWebhookRoute::class,
    ],
    'PUT' => [
        '/websites/:id' => UpdateWebsiteRoute::class,
    ]
];
