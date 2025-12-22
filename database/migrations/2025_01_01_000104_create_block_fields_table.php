<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_fields', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('block_uuid');
            $table->string('field_name');
            $table->text('field_value')->nullable();
            $table->string('field_type', 50);
            $table->timestamps();

            $table->foreign('block_uuid')->references('uuid')->on('blocks')->onDelete('cascade');
            $table->index('block_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_fields');
    }
};
