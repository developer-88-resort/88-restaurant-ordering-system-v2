<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\Pin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Feeds each generated Staff/Admin a different PIN (PINs are unique).
     */
    protected static int $pinSequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // The column defaults to true, but a freshly created model only
            // knows attributes it was given — without this, actingAs() on a
            // new user sees is_active = null and EnsureAccountIsActive signs
            // them straight back out.
            'is_active' => true,
        ];
    }

    /**
     * Staff and Admin sign in with a PIN, and anyone without one is sent to
     * set one up before anything else — so every generated Staff/Admin gets
     * their own ready-to-use PIN unless a test says otherwise.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            if ($user->usesPin() && $user->pin_hash === null) {
                $pin = static::nextPin();

                $user->forceFill([
                    'pin_hash' => Hash::make($pin),
                    'pin_lookup' => Pin::lookup($pin),
                    'pin_changed_at' => now(),
                ]);
            }
        });
    }

    /**
     * A known PIN. $temporary: set by an admin, so it must be changed at
     * the next sign-in.
     */
    public function withPin(string $pin, bool $temporary = false): static
    {
        return $this->state(fn () => [
            'pin_hash' => Hash::make($pin),
            'pin_lookup' => Pin::lookup($pin),
            'pin_changed_at' => $temporary ? null : now(),
        ]);
    }

    /**
     * An existing Staff/Admin from before PIN sign-in: email + password only.
     */
    public function withoutPin(): static
    {
        return $this->afterMaking(fn (User $user) => $user->forceFill([
            'pin_hash' => null,
            'pin_lookup' => null,
            'pin_changed_at' => null,
        ]));
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    protected static function nextPin(): string
    {
        do {
            // Wraps long before running past 6 digits; a test never makes
            // thousands of users, so a wrapped PIN never meets its twin.
            $pin = (string) (739000 + 37 * (static::$pinSequence++ % 7000));
        } while (Pin::weakness($pin) !== null);

        return $pin;
    }
}
