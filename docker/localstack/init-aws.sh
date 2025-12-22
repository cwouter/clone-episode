#!/bin/bash

# Wait for LocalStack to be ready
echo "Waiting for LocalStack to be ready..."
sleep 5

# Create S3 bucket
awslocal s3 mb s3://media-files

# Set bucket policy to allow public read (optional, for testing)
awslocal s3api put-bucket-acl --bucket media-files --acl public-read

echo "LocalStack S3 bucket 'media-files' created successfully"
