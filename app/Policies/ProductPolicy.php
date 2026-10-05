<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function view(User $user, Product $product): bool
    {
        return $user->isAdmin() || (($user->isInstructor() || $user->isStudent()) && $user->class_id === $product->class_id);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin() || ($user->isInstructor() && $user->class_id === $product->class_id) || ($user->isStudent() && $user->id === $product->created_by);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
