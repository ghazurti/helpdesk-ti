<?php

namespace App\Policies;

use App\Models\User;

class AssetCategoryPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasRole('admin');
    }

    public function view(User $u, $m): bool
    {
        return $u->hasRole('admin');
    }

    public function create(User $u): bool
    {
        return $u->hasRole('admin');
    }

    public function update(User $u, $m): bool
    {
        return $u->hasRole('admin');
    }

    public function delete(User $u, $m): bool
    {
        return $u->hasRole('admin');
    }
}
