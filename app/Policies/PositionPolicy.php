<?php

namespace App\Policies;

use App\Models\User;

class PositionPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'hr']);
    }

    public function update(User $user): bool
    {
        return $user->hasRole(['admin', 'hr']);
    }

    public function delete(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
