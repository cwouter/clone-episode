<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('episode_uuid');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('episode_uuid')->references('uuid')->on('episodes')->onDelete('cascade');
            $table->index('episode_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
