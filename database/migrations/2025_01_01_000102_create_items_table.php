<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('part_uuid');
            $table->string('name');
            $table->text('details')->nullable();
            $table->timestamps();

            $table->foreign('part_uuid')->references('uuid')->on('parts')->onDelete('cascade');
            $table->index('part_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
