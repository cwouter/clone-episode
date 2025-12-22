<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('item_uuid');
            $table->string('type', 100);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('item_uuid')->references('uuid')->on('items')->onDelete('cascade');
            $table->index('item_uuid');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
