<?php

namespace App\Contracts;

use App\DTO\GoogleSigninRequest;
use App\DTO\GoogleSigninResponse;

interface IAuthRoute
{
    /**
     * Handle Google Sign-in authentication
     *
     * @param GoogleSigninRequest $request
     * @return GoogleSigninResponse
     */
    public static function handle(GoogleSigninRequest $request): GoogleSigninResponse;
}
