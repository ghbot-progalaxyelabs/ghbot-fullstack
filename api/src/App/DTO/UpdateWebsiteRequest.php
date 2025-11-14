<?php

namespace App\DTO;

class UpdateWebsiteRequest
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly ?object $content = null,
        public readonly ?object $settings = null,
        public readonly ?string $userId = null,
    ) {}
}
