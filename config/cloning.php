<?php

return [
    'chunk_size' => env('CLONING_CHUNK_SIZE', 1000),
    'chunk_size_parts' => env('CLONING_CHUNK_SIZE_PARTS', env('CLONING_CHUNK_SIZE', 1000)),
    'chunk_size_items' => env('CLONING_CHUNK_SIZE_ITEMS', env('CLONING_CHUNK_SIZE', 1000)),
    'chunk_size_blocks' => env('CLONING_CHUNK_SIZE_BLOCKS', env('CLONING_CHUNK_SIZE', 1000)),
    'transaction_ttl' => env('CLONING_TRANSACTION_TTL', 3600),
    's3_bucket' => env('S3_BUCKET', 'media-files'),
];
