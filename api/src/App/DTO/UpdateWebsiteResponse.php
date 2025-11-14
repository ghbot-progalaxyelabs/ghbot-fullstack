<?php

namespace App\DTO;

class UpdateWebsiteResponse
{
    public function __construct(
        public readonly string $id,
        public readonly string $updatedAt,
    ) {}
}
