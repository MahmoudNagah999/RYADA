<?php

namespace App\Http\Resources;

use App\Domain\Notification\Enums\NotificationCode;
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

        $key = $this->translationKey($code, $params);

        return [
            'id' => $this->id,
            'code' => $code,
            'message' => $key !== null && Lang::has($key)
                ? __($key, $this->messageParams($params))
                : $code,
            'params' => $params,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * The code picks its own key, so a singular variant stays next to the Arabic and English
     * lines instead of being decided in the resource.
     *
     * @param  array<string, mixed>  $params
     */
    private function translationKey(string $code, array $params): ?string
    {
        if ($code === '') {
            return null;
        }

        $known = NotificationCode::tryFrom($code);

        return $known?->translationKey($params) ?? 'notifications.'.$code;
    }

    /**
     * Money is stored as integer cents and reminders store a calendar date as `ends_on` and a
     * count as `days_left`; the sentences speak of an amount, a `date` and a number of `days`.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function messageParams(array $params): array
    {
        $replacements = $params;

        if (isset($params['outstanding_cents'], $params['currency'])) {
            $amount = number_format(((int) $params['outstanding_cents']) / 100, 2, '.', '');
            $replacements['amount'] = $amount.' '.(string) $params['currency'];
        }

        if (isset($params['ends_on']) && is_string($params['ends_on'])) {
            $replacements['date'] = $params['ends_on'];
        }

        if (isset($params['days_left'])) {
            $replacements['days'] = (string) (int) $params['days_left'];
        }

        return $replacements;
    }
}
