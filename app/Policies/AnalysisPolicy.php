<?php

namespace App\Policies;

use App\Models\Analysis;
use App\Models\User;

class AnalysisPolicy
{
    public function view(User $user, Analysis $analysis): bool
    {
        return $user->id === $analysis->user_id || $user->hasRole('Admin');
    }

    public function update(User $user, Analysis $analysis): bool
    {
        return $user->id === $analysis->user_id || $user->hasRole('Admin');
    }

    public function delete(User $user, Analysis $analysis): bool
    {
        return $user->id === $analysis->user_id || $user->hasRole('Admin');
    }
}
