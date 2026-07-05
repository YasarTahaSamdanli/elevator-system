<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Applies the API list conventions (SOLUTION_ARCHITECTURE.md §12) to an
 * Eloquent query: `filter[field]=value`, `filter[col_from]`/`filter[col_to]`
 * date ranges, `sort=-field,other`, `search=` and `page`/`per_page`
 * pagination. Every field must be explicitly whitelisted by the controller;
 * unknown filter or sort fields are rejected with a 422 so client typos
 * fail loudly instead of silently returning unfiltered data.
 */
class ListQuery
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    /** @var array<string, Closure|null> filter field => custom applier (null = column equality) */
    private array $filterable = [];

    /** @var list<string> columns exposed as filter[col_from] / filter[col_to] */
    private array $dateRanges = [];

    /** @var list<string> */
    private array $searchable = [];

    /** @var list<string> */
    private array $sortable = [];

    private string $defaultSort = '-created_at';

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function __construct(
        private readonly Builder $query,
        private readonly Request $request,
    ) {}

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /**
     * Whitelist filterable fields. Plain entries filter on the column with
     * equality (arrays become whereIn); key => Closure entries run the
     * closure with ($query, $value) for relation lookups and the like.
     *
     * @param  array<int|string, string|Closure>  $filters
     */
    public function filterable(array $filters): self
    {
        foreach ($filters as $field => $applier) {
            if ($applier instanceof Closure) {
                $this->filterable[$field] = $applier;
            } else {
                $this->filterable[$applier] = null;
            }
        }

        return $this;
    }

    /**
     * Expose inclusive day-range filters filter[{col}_from] / filter[{col}_to].
     */
    public function dateRange(string ...$columns): self
    {
        $this->dateRanges = array_merge($this->dateRanges, array_values($columns));

        return $this;
    }

    /**
     * Columns matched (case-insensitively, partial) by the `search` parameter.
     *
     * @param  list<string>  $columns
     */
    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function sortable(array $columns, string $defaultSort = '-created_at'): self
    {
        $this->sortable = $columns;
        $this->defaultSort = $defaultSort;

        return $this;
    }

    /**
     * @return LengthAwarePaginator<int, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function paginate(): LengthAwarePaginator
    {
        $this->applyFilters();
        $this->applySearch();
        $this->applySort();

        /** @var LengthAwarePaginator<int, covariant \Illuminate\Database\Eloquent\Model> $paginator */
        $paginator = $this->query
            ->paginate(perPage: $this->perPage())
            ->appends($this->request->query());

        return $paginator;
    }

    private function applyFilters(): void
    {
        $filters = $this->request->query('filter', []);

        if (! is_array($filters)) {
            throw ValidationException::withMessages([
                'filter' => ['The filter parameter must be an array, e.g. filter[status]=active.'],
            ]);
        }

        foreach ($filters as $field => $value) {
            if ($this->applyDateRangeFilter($field, $value)) {
                continue;
            }

            if (! array_key_exists($field, $this->filterable)) {
                throw ValidationException::withMessages([
                    "filter.$field" => ["Filtering by '$field' is not supported."],
                ]);
            }

            $applier = $this->filterable[$field];

            if ($applier instanceof Closure) {
                $applier($this->query, $value);

                continue;
            }

            $column = $this->query->qualifyColumn($field);

            if (is_array($value)) {
                $this->query->whereIn($column, $value);
            } else {
                $this->query->where($column, self::normalizeScalar($value));
            }
        }
    }

    private function applyDateRangeFilter(string $field, mixed $value): bool
    {
        foreach ($this->dateRanges as $column) {
            $operator = match ($field) {
                "{$column}_from" => '>=',
                "{$column}_to" => '<=',
                default => null,
            };

            if ($operator === null) {
                continue;
            }

            if (! is_string($value) || strtotime($value) === false) {
                throw ValidationException::withMessages([
                    "filter.$field" => ["The '$field' filter must be a valid date."],
                ]);
            }

            $this->query->whereDate($this->query->qualifyColumn($column), $operator, $value);

            return true;
        }

        return false;
    }

    private function applySearch(): void
    {
        $term = $this->request->query('search');

        if (! is_string($term) || trim($term) === '' || $this->searchable === []) {
            return;
        }

        $needle = '%'.mb_strtolower(trim($term)).'%';

        $this->query->where(function (Builder $query) use ($needle): void {
            foreach ($this->searchable as $column) {
                $query->orWhereRaw(
                    'LOWER('.$query->qualifyColumn($column).') LIKE ?',
                    [$needle],
                );
            }
        });
    }

    private function applySort(): void
    {
        $sort = $this->request->query('sort', $this->defaultSort);

        if (! is_string($sort) || trim($sort) === '') {
            $sort = $this->defaultSort;
        }

        foreach (explode(',', $sort) as $segment) {
            $segment = trim($segment);
            $direction = str_starts_with($segment, '-') ? 'desc' : 'asc';
            $column = ltrim($segment, '-');

            if (! in_array($column, $this->sortable, true)) {
                throw ValidationException::withMessages([
                    'sort' => ["Sorting by '$column' is not supported."],
                ]);
            }

            $this->query->orderBy($this->query->qualifyColumn($column), $direction);
        }
    }

    private function perPage(): int
    {
        $perPage = $this->request->query('per_page', (string) self::DEFAULT_PER_PAGE);

        if (! is_string($perPage) || filter_var($perPage, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages([
                'per_page' => ['The per_page parameter must be an integer.'],
            ]);
        }

        $perPage = (int) $perPage;

        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw ValidationException::withMessages([
                'per_page' => ['The per_page parameter must be between 1 and '.self::MAX_PER_PAGE.'.'],
            ]);
        }

        return $perPage;
    }

    private static function normalizeScalar(mixed $value): mixed
    {
        return match ($value) {
            'true' => true,
            'false' => false,
            default => $value,
        };
    }
}
