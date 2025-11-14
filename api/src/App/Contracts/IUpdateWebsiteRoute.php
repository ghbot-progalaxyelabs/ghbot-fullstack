<?php

namespace App\Contracts;

use App\DTO\UpdateWebsiteRequest;
use App\DTO\UpdateWebsiteResponse;

interface IUpdateWebsiteRoute
{
    public function execute(UpdateWebsiteRequest $request): UpdateWebsiteResponse;
}
