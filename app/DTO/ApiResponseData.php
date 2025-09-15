<?php

declare(strict_types=1);

namespace App\DTO;

class ApiResponseData
{
    public function __construct(
        public string $message,
        public ?array $data = null,
        public int $statusCode = 200,
        public array $meta = []
    ) {}

    public function toArray(): array
    {
        $result = [
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        if (! empty($this->meta)) {
            $result['meta'] = $this->meta;
        }

        return $result;
    }
}
