<?php

namespace App\DTO;

class GetWebsiteResponse
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly object $content,
        public readonly object $settings,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}
}
