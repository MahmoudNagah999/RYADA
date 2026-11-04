<?php

namespace App\Http\Requests\Player;

use App\Domain\Player\Enums\Gender;
use App\Http\Rules\EgyptianNationalId;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class PlayerFormRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'national_id' => ['nullable', 'string', new EgyptianNationalId],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
        ];
    }

    protected function playerName(): string
    {
        return $this->string('name')->toString();
    }

    protected function nationalId(): ?string
    {
        return $this->filled('national_id') ? $this->string('national_id')->toString() : null;
    }

    protected function birthDate(): ?CarbonImmutable
    {
        if (! $this->filled('birth_date')) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $this->string('birth_date')->toString())->startOfDay();
    }

    protected function gender(): ?Gender
    {
        return $this->filled('gender') ? Gender::from($this->string('gender')->toString()) : null;
    }

    protected function phone(): ?string
    {
        return $this->filled('phone') ? $this->string('phone')->toString() : null;
    }
}
