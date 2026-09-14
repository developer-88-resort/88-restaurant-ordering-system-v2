<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('title');
            $table->string('short_description', 300)->nullable();
            $table->text('description')->nullable();
            $table->string('type', 30);
            $table->string('image_path')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_disabled')->default(false);

            $table->string('applicable_scope', 20)->default('all');

            $table->string('discount_calculation_mode', 20)->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->decimal('discount_min_order_amount', 10, 2)->nullable();
            $table->decimal('discount_max_discount_amount', 10, 2)->nullable();

            $table->boolean('is_featured')->default(false);
            $table->string('banner_headline')->nullable();
            $table->string('banner_subheadline')->nullable();
            $table->string('banner_cta_label', 100)->nullable();
            $table->string('banner_cta_url')->nullable();
            $table->unsignedInteger('display_priority')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['type']);
            $table->index(['is_published', 'is_disabled', 'starts_at', 'ends_at'], 'promotions_status_window_index');
            $table->index(['is_featured', 'display_priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
