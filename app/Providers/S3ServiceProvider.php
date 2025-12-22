<?php

namespace App\Providers;

use Aws\S3\S3Client;
use Illuminate\Support\ServiceProvider;

class S3ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(S3Client::class, function () {
            return new S3Client([
                'version' => 'latest',
                'region' => config('filesystems.disks.s3.region'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint'),
                'credentials' => [
                    'key'    => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);
        });
    }
}
