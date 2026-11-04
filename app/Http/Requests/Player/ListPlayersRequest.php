<?php

namespace App\Http\Requests\Player;

use App\Domain\Player\DTOs\ListPlayersData;
use App\Domain\Player\Enums\Gender;
use App\Domain\Player\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPlayersRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Player::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'age_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:age_min'],
            'sort' => ['nullable', 'string', Rule::in([
                'name', '-name', 'birth_date', '-birth_date', 'created_at', '-created_at',
            ])],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toData(): ListPlayersData
    {
        $sort = $this->string('sort', 'name')->toString();

        return new ListPlayersData(
            search: $this->filled('search') ? trim($this->string('search')->toString()) : null,
            gender: $this->filled('gender') ? Gender::from($this->string('gender')->toString()) : null,
            ageMin: $this->filled('age_min') ? $this->integer('age_min') : null,
            ageMax: $this->filled('age_max') ? $this->integer('age_max') : null,
            sortColumn: ltrim($sort, '-'),
            sortDirection: str_starts_with($sort, '-') ? 'desc' : 'asc',
            perPage: min($this->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE),
        );
    }
}
