<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('block_uuid');
            $table->string('media_type', 100);
            $table->string('s3_key', 500);
            $table->string('s3_bucket', 255)->default('media-files');
            $table->string('url', 1000);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('block_uuid')->references('uuid')->on('blocks')->onDelete('cascade');
            $table->index('block_uuid');
            $table->index('media_type');
            $table->index('s3_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
