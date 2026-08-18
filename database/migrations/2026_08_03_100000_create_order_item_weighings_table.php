<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The weighing record behind a weighed order line.
 *
 * The counter scale now supplies BOTH figures — the weight and the amount
 * — and staff key in what the display shows. So the money on the line is
 * no longer something the system decided; it is something it was TOLD.
 * That makes this table the evidence: what was keyed, what the reference
 * rate implied, and how far apart the two were.
 *
 * A row here is IMMUTABLE except for its void fields. Correcting a
 * weighing writes a new row at revision+1 pointing back at the one it
 * supersedes, so a bill that changed after the customer saw it can always
 * be replayed. That is the whole reason this is a separate table rather
 * than more columns on order_items: order_items holds the CURRENT state,
 * this holds the HISTORY.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_weighings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();

            // Keyed from the scale display. net_grams is already net — the
            // hardware's TARE button did that subtraction, not us.
            $table->unsignedInteger('net_grams');
            $table->decimal('amount_charged', 10, 2);

            // Resolved server-side: today's daily market price if one is
            // set, else the item's standing rate. Never accepted from the
            // client, or the daily price page would be decorative.
            $table->decimal('reference_price_per_kilo', 10, 2);

            // What the reference rate implies. Kept only so the two can be
            // compared and the difference explained; it is NOT what is
            // billed.
            $table->decimal('computed_amount', 10, 2);
            $table->decimal('variance_amount', 10, 2)->default(0);
            $table->decimal('variance_percent', 6, 2)->default(0);
            $table->text('variance_reason')->nullable();

            $table->unsignedInteger('pieces')->default(1);
            $table->foreignId('cooking_style_id')->nullable()->constrained('cooking_styles')->nullOnDelete();
            $table->text('cooking_note')->nullable();

            // 'in_person' is a human reading the display. 'scale_feed' is
            // reserved for Phase 3, when the scale posts directly.
            $table->enum('entry_mode', ['in_person', 'scale_feed'])->default('in_person');

            $table->foreignId('weighed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('weighed_at')->nullable();

            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('supersedes_id')->nullable()
                ->constrained('order_item_weighings')->nullOnDelete();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();

            $table->timestamps();

            // One row per revision per line — the database, not the
            // application, is what makes a lost-update impossible when two
            // corrections race.
            $table->unique(['order_item_id', 'revision']);
            $table->index('weighed_at');
            $table->index('variance_amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_weighings');
    }
};
