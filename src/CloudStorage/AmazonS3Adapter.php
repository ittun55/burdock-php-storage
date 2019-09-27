<?php
namespace Burdock\CloudStorage;

use Aws\S3\S3Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AmazonS3Adapter implements StorageAdapterInterface
{
    const AWS_S3_KEY    = 'AWS_S3_KEY';
    const AWS_S3_SECRET = 'AWS_S3_SECRET';
    const AWS_S3_REGION = 'AWS_S3_REGION';
    const AWS_S3_BUCKET = 'AWS_S3_BUCKET';

    protected $client;

    /**
     * S3 バケット
     * 誤操作でこのバケット以外のファイルを触らないため、初期値として設定する
     *
     * @var string
     */
    protected $bucket;
    protected $logger;

    public function __construct(array $config, ?LoggerInterface $logger=null)
    {
        $this->client  = new S3Client([
            'credentials' => [
                'key'     => $config[self::AWS_S3_KEY],
                'secret'  => $config[self::AWS_S3_SECRET],
            ],
            'region'      => $config[self::AWS_S3_REGION],
            'version'     => 'latest',
        ]);
        $this->logger     = is_null($logger) ? new NullLogger() : $logger;
        $this->bucket     = $config[self::AWS_S3_BUCKET];
    }

    public function getFullPath(string $path): string
    {
        // TODO: Implement getFullPath() method.
        throw new NotImplementedException();
    }

    public function getList(string $path, int $depth): array
    {
        // TODO: Implement getList() method.
        throw new NotImplementedException();
    }

    public function download(string $src, string $dst): string
    {
        // TODO: Implement getFile() method.
        throw new NotImplementedException();
    }

    public function upload(string $src, string $dst): bool
    {
        // TODO: Implement saveFile() method.
        throw new NotImplementedException();
    }

    public function delete(string $remote): bool
    {
        // TODO: Implement delete() method.
        throw new NotImplementedException();
    }

    public function createFolder(string $path): bool
    {
        // TODO: Implement createFolder() method.
        throw new NotImplementedException();
    }
}