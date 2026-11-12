<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;

/**
 * @mixin DatabaseNotification
 *
 * @property string $id
 * @property array<string, mixed> $data
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data;
        $code = is_string($data['code'] ?? null) ? $data['code'] : '';

        /** @var array<string, mixed> $params */
        $params = is_array($data['params'] ?? null) ? $data['params'] : [];

        $key = 'notifications.'.$code;

        return [
            'id' => $this->id,
            'code' => $code,
            'message' => $code !== '' && Lang::has($key)
                ? __($key, $this->messageParams($params))
                : $code,
            'params' => $params,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Money is stored as integer cents; the sentence needs a formatted amount.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function messageParams(array $params): array
    {
        if (! isset($params['outstanding_cents'], $params['currency'])) {
            return $params;
        }

        $amount = number_format(((int) $params['outstanding_cents']) / 100, 2, '.', '');

        return [...$params, 'amount' => $amount.' '.(string) $params['currency']];
    }
}
