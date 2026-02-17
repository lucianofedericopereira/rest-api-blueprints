<?php

declare(strict_types=1);

namespace Database\Factories\Domain\User\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'email'             => fake()->unique()->safeEmail(),
            'password'          => 'password',  // hashed automatically via $casts
            'role'              => 'viewer',
            'email_verified_at' => now(),
            'remember_token'    => Str::random(10),
        ];
    }
}
