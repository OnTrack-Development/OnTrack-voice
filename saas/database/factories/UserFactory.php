<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        $hashed = static::$password ??= Hash::make('Password123!');

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => $hashed,
            'password_hash' => $hashed,
            'status' => 'active',
            'auth_version' => 0,
            'remember_token' => Str::random(10),
        ];
    }
}
