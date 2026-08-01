<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Polymorphic supporting-documentation attachments (weighing photos/
     * videos on order items or add-ons, card-terminal slip photos on
     * payment entries). Files live on the private local disk and are only
     * served through an authenticated, role-checked route — never publicly.
     */
    public function up(): void
    {
        Schema::create('media_evidence', function (Blueprint $table) {
            $table->id();
            $table->morphs('evidenceable');
            $table->string('media_type');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_evidence');
    }
};
