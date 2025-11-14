<?php

use App\Routes\HomeRoute;
use App\Routes\GithubWebhookRoute;
use App\Routes\GetWebsiteRoute;
use App\Routes\UpdateWebsiteRoute;
use App\Routes\AuthRoute;
use App\Routes\MockAuthRoute;

return [
    'GET' => [
        '/' => HomeRoute::class,
        '/websites/:id' => GetWebsiteRoute::class,
    ],
    'POST' => [
        '/auth/google-signin' => AuthRoute::class,
        '/auth/mock-signin' => MockAuthRoute::class,  // Mock auth for E2E testing
        '/websites' => \App\Routes\WebsitesRoute::class,
        '/webhook/github' => GithubWebhookRoute::class,
    ],
    'PUT' => [
        '/websites/:id' => UpdateWebsiteRoute::class,
    ]
];
