<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    /**
     * Restrict every query to the authenticated user's company.
     *
     * Scope is skipped entirely when there is no authenticated user (console
     * commands, seeders, queued jobs without an actor) so those contexts can
     * still operate across all companies.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $user->company_id);
    }
}
