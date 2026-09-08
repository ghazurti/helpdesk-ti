<?php

namespace App\Policies;

use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasAnyRole(['admin', 'staf_it']);
    }

    public function view(User $u, $m): bool
    {
        return $u->hasAnyRole(['admin', 'staf_it']);
    }

    public function create(User $u): bool
    {
        return $u->hasAnyRole(['admin', 'staf_it']);
    }

    public function update(User $u, $m): bool
    {
        return $u->hasAnyRole(['admin', 'staf_it']);
    }

    public function delete(User $u, $m): bool
    {
        return $u->hasRole('admin');
    }
}
