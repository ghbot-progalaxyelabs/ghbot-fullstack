<?php

namespace App\Contracts;

use App\DTO\GetWebsiteRequest;
use App\DTO\GetWebsiteResponse;

interface IGetWebsiteRoute
{
    public function execute(GetWebsiteRequest $request): GetWebsiteResponse;
}
