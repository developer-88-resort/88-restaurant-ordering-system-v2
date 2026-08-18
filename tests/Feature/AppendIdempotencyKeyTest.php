<?php

namespace Tests\Feature;

use App\Models\AppendIdempotencyKey;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * request_uuid is a native CHAR(36) column — this guard is what stops a
 * too-long value from ever reaching it. Without it, a caller error would
 * either 500 (strict SQL mode) or, worse, silently truncate and collide
 * two unrelated requests down to the same key (relaxed mode) — which is
 * exactly the bug that motivated splitting `key` into (request_uuid,
 * line_index) in the first place.
 */
class AppendIdempotencyKeyTest extends TestCase
{
    use RefreshDatabase;

    private function orderAndItem(): array
    {
        $order = Order::create([
            'order_type' => 'dine_in',
            'order_number' => 'TEST-'.uniqid(),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => '0.00',
        ]);

        $item = $order->items()->create([
            'item_name' => 'Rice',
            'unit_price' => '50.00',
            'quantity' => 1,
            'subtotal' => '50.00',
            'line_type' => 'fixed',
        ]);

        return [$order, $item];
    }

    public function test_remember_rejects_a_request_uuid_longer_than_36_characters(): void
    {
        [$order, $item] = $this->orderAndItem();

        try {
            AppendIdempotencyKey::remember(str_repeat('a', 40), 0, $order, $item);
            $this->fail('Expected an InvalidArgumentException for an oversized request_uuid.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds 36 characters', $e->getMessage());
        }

        $this->assertSame(0, AppendIdempotencyKey::count(), 'Nothing must be written when the guard trips.');
    }

    public function test_remember_and_resolve_round_trip_on_a_bare_36_character_uuid(): void
    {
        [$order, $item] = $this->orderAndItem();
        $uuid = (string) \Illuminate\Support\Str::uuid();

        AppendIdempotencyKey::remember($uuid, 0, $order, $item);

        $this->assertSame(1, AppendIdempotencyKey::count());
        $replayed = AppendIdempotencyKey::resolve($uuid, 0, $order);
        $this->assertSame($item->id, $replayed?->id);
    }

    public function test_the_same_uuid_with_different_line_indexes_are_distinct_keys(): void
    {
        [$order, $item] = $this->orderAndItem();
        $uuid = (string) \Illuminate\Support\Str::uuid();

        AppendIdempotencyKey::remember($uuid, 0, $order, $item);
        AppendIdempotencyKey::remember($uuid, 1, $order, $item);
        AppendIdempotencyKey::remember($uuid, 2, $order, $item);

        $this->assertSame(3, AppendIdempotencyKey::where('request_uuid', $uuid)->count());
    }
}
