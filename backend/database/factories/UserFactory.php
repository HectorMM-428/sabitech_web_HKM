<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    /** El rol no es asignable en masa: se fija después de crear el modelo. */
    public function role(UserRole $role): static
    {
        return $this->afterMaking(function (User $user) use ($role) {
            $user->role = $role;
        });
    }

    public function staff(): static
    {
        return $this->role(UserRole::Staff);
    }

    public function admin(): static
    {
        return $this->role(UserRole::Admin);
    }
}
