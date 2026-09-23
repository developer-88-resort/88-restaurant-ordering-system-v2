<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Pin;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PinRulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function unusablePins(): array
    {
        return [
            'same digit' => ['0000'],
            'same digit, six' => ['777777'],
            'run up' => ['1234'],
            'run down' => ['4321'],
            'run through zero' => ['7890'],
            'six-digit run' => ['123456'],
            'six-digit run down' => ['654321'],
            'repeated pair' => ['1212'],
            'repeated triple' => ['123123'],
            'doubled digits' => ['1122'],
            'doubled digits, six' => ['112233'],
            'keypad column' => ['2580'],
            'common' => ['1004'],
            'too short' => ['482'],
            'too long' => ['4829173'],
            'not digits' => ['48a9'],
        ];
    }

    #[DataProvider('unusablePins')]
    public function test_an_easy_or_malformed_pin_is_refused(string $pin): void
    {
        $staff = User::factory()->withoutPin()->create(['role' => UserRole::Staff]);

        $this->actingAs($staff)
            ->post(route('pin.setup.store'), ['pin' => $pin, 'pin_confirmation' => $pin])
            ->assertSessionHasErrors('pin');

        $this->assertNull($staff->refresh()->pin_hash);
    }

    public function test_an_ordinary_pin_is_accepted(): void
    {
        foreach (['4829', '73519', '205817'] as $pin) {
            $this->assertNull(Pin::weakness($pin), $pin);
        }
    }

    public function test_a_pin_someone_else_holds_is_refused_without_saying_whose_it_is(): void
    {
        User::factory()->withPin('4829')->create(['name' => 'Ana Staff', 'role' => UserRole::Staff]);
        $newcomer = User::factory()->withoutPin()->create(['role' => UserRole::Staff]);

        $this->actingAs($newcomer)
            ->post(route('pin.setup.store'), ['pin' => '4829', 'pin_confirmation' => '4829'])
            ->assertSessionHasErrors(['pin' => 'This PIN can\'t be used. Please choose a different one.']);

        $this->assertStringNotContainsString('Ana', (string) session('errors')->first('pin'));
        $this->assertNull($newcomer->refresh()->pin_hash);
    }

    /**
     * Uniqueness is a rule about PINs people chose for themselves, enforced in
     * ValidPin — not a database constraint, since starting PINs repeat.
     */
    public function test_the_database_holds_two_accounts_on_one_starting_pin(): void
    {
        User::factory()->withPin('1234', temporary: true)->create(['role' => UserRole::Staff]);
        User::factory()->withPin('1234', temporary: true)->create(['role' => UserRole::Staff]);

        $this->assertSame(2, User::where('pin_lookup', Pin::lookup('1234'))->count());
    }

    public function test_the_pin_is_stored_only_as_a_hash_and_a_keyed_fingerprint(): void
    {
        $staff = User::factory()->withoutPin()->create(['role' => UserRole::Staff]);
        $staff->setPin('4829');

        $raw = User::query()->whereKey($staff->id)->toBase()->first();
        $this->assertNotSame('4829', $raw->pin_hash);
        $this->assertTrue(Hash::check('4829', $raw->pin_hash));
        $this->assertSame(Pin::lookup('4829'), $raw->pin_lookup);
        $this->assertNotSame(hash('sha256', '4829'), $raw->pin_lookup, 'A plain hash of 4 digits is trivially reversed; the fingerprint is keyed.');
        $this->assertStringNotContainsString('4829', json_encode($staff->toArray()));
    }

    public function test_setting_a_pin_never_puts_it_in_the_audit_log(): void
    {
        $staff = User::factory()->withoutPin()->create(['role' => UserRole::Staff]);

        $this->actingAs($staff)->post(route('pin.setup.store'), ['pin' => '4829', 'pin_confirmation' => '4829']);

        $staff->refresh();
        foreach (Activity::all() as $log) {
            $logged = json_encode($log->properties);
            $this->assertStringNotContainsString($staff->pin_hash, $logged);
            $this->assertStringNotContainsString($staff->pin_lookup, $logged);
        }
    }

    public function test_changing_your_pin_needs_the_current_one(): void
    {
        $staff = User::factory()->withPin('4829')->create(['role' => UserRole::Staff]);

        $this->actingAs($staff)
            ->put(route('profile.pin.update'), ['current_pin' => '0001', 'pin' => '7351', 'pin_confirmation' => '7351'])
            ->assertSessionHasErrors(['current_pin' => 'Your current PIN is not correct.']);
        $this->assertTrue($staff->refresh()->checkPin('4829'));

        $this->actingAs($staff)
            ->put(route('profile.pin.update'), ['current_pin' => '4829', 'pin' => '7351', 'pin_confirmation' => '7351'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($staff->refresh()->checkPin('7351'));
        $this->assertTrue(Activity::where('event', 'pin_changed')->where('subject_id', $staff->id)->exists());
    }

    public function test_changing_to_someone_elses_pin_is_refused(): void
    {
        User::factory()->withPin('5831')->create(['role' => UserRole::Staff]);
        $staff = User::factory()->withPin('4829')->create(['role' => UserRole::Staff]);

        $this->actingAs($staff)
            ->put(route('profile.pin.update'), ['current_pin' => '4829', 'pin' => '5831', 'pin_confirmation' => '5831'])
            ->assertSessionHasErrors('pin');

        $this->assertTrue($staff->refresh()->checkPin('4829'));
    }
}
