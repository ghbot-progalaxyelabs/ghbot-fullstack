<?php

namespace App\DTO;

class GetWebsiteRequest
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $userId = null,
    ) {}
}
