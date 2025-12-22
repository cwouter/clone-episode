<?php

return [
    'chunk_size' => env('CLONING_CHUNK_SIZE', 1000),
    'transaction_ttl' => env('CLONING_TRANSACTION_TTL', 3600),
    's3_bucket' => env('S3_BUCKET', 'media-files'),
];
