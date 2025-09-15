<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    protected ?array $additionalData = null;

    /**
     * Create a new resource instance.
     */
    public function __construct(string $message, ?array $additionalData = null)
    {
        parent::__construct($message);
        $this->additionalData = $additionalData;
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $response = [
            'message' => $this->resource,
        ];

        if ($this->additionalData) {
            $response = array_merge($response, $this->additionalData);
        }

        return $response;
    }
}
