<?php
namespace Burdock\CloudStorage;

use Burdock\Utils\Str;
use Exception;
use InvalidArgumentException;
use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\DropboxFile;
use Kunnu\Dropbox\Exceptions\DropboxClientException;
use Kunnu\Dropbox\Models\FileMetadata;
use Kunnu\Dropbox\Models\FolderMetadata;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

const DS = DIRECTORY_SEPARATOR;

/**
 * Class DropboxAdapter
 *
 * https://github.com/kunalvarma05/dropbox-php-sdk/wiki/Upload-and-Download-Files
 * https://kunalvarma05.github.io/dropbox-php-sdk/master/Kunnu/Dropbox/Dropbox.html
 *
 * @package Burdock\StorageAdapter
 */
class DropboxAdapter implements StorageAdapterInterface
{
    const DBX_APP_KEY      = 'DBX_APP_KEY';
    const DBX_APP_SECRET   = 'DBX_APP_SECRET';
    const DBX_ACCESS_TOKEN = 'DBX_ACCESS_TOKEN';
    const DBX_BASE_DIR     = 'DBX_BASE_DIR';

    protected $client;

    /**
     * Dropbox 上のルートパス
     * 誤操作でこのパス以外のファイルを触らないために設定
     *
     * @var array|false|string
     */
    protected $base_dir;
    protected $logger;

    /**
     * DropboxAdapter constructor.
     * @param array $config
     * @param LoggerInterface|null $logger
     */
    public function __construct(array $config, ?LoggerInterface $logger=null)
    {
        $app = new DropboxApp(
            $config[self::DBX_APP_KEY],
            $config[self::DBX_APP_SECRET],
            $config[self::DBX_ACCESS_TOKEN]
        );
        $this->client   = new Dropbox($app);
        $this->logger   = is_null($logger) ? new NullLogger() : $logger;
        $this->base_dir = $config[self::DBX_BASE_DIR];
    }

    public function getFullPath(string $remote=null): string
    {
        if (strpos($remote, '..') !== false) {
            $msg = '$remote path should not contain to move parent directory';
            throw new InvalidArgumentException($msg);
        }
        $_path = $this->base_dir;
        if (!is_null($remote))
            $_path.= (substr($remote, 0, 1) == '/') ? $remote : '/' . $remote;
        return $_path;
    }

    /**
     * @param string $remote リストを取得するリモートパス
     * @param int $depth 再帰の初回はリモートフルパスの組み立てが必要なため
     * @param int|null $limit 再帰の階層制限 0 は制限なし
     * @return array
     */
    public function getList(string $remote, int $depth = 0, ?int $limit=0): array
    {
        $_path = ($depth === 0) ? $this->getFullPath($remote) : $remote;
        $folders = $this->client->listFolder($_path);
        $items = [];
        foreach ($folders->getItems() as $content) {
            if ($content instanceof FolderMetadata) {
                if ($limit && $limit <= $depth + 1) continue;
                $folder = $content->getPathDisplay();
                $items[] = [
                    'title'       => $content->getName(), //フォルダ名
                    'path'        => $folder,             //フルパス (フォルダ名含む)
                    'content'     => $content,
                    'children'    => $this->getList($folder, $depth + 1, $limit)
                ];
            } else {
                $items[] = [
                    'title'       => $content->getName(),          //ファイル名
                    'path'        => $content->getPathDisplay(),   //フルパス (ファイル名含む)
                    'content'     => $content,
                    'modified_at' => $content->getServerModified() //更新日時
                ];
            }
        }
        return $items;
    }

    /**
     * @param string $remote  remote path of download file
     * @param string $local   path to downloaded file (including file name)
     * @param bool $overwrite flag for overwriting
     * @return string
     * @throws DropboxClientException
     */
    public function download(string $remote, string $local, bool $overwrite=false) : string
    {
        if (!$overwrite && file_exists($local)) {
            throw new InvalidArgumentException($local . ' already exists');
        }
        $path = $this->getFullPath($remote);
        $file = $this->client->download($path);
        //$meta = $file->getMetadata();
        file_put_contents($local, $file->getContents());
        unset($file);
        return $local;
    }

    /**
     * @param string $local  path to local file to upload
     * @param string $remote remote path including file name
     * @return bool
     */
    public function upload(string $local, string $remote) : bool
    {
        $dropboxFile = new DropboxFile($local);
        $this->logger->info('start uploading from: ' . $local . ' to: ' . $this->getFullPath($remote));
        // どうやら $local をそのまま第一引数に与えても良いらしい
        $fileMeta = $this->client->upload($dropboxFile, $this->getFullPath($remote), ['autorename' => true]);
        return $fileMeta->getId() ? true : false;
    }

    /**
     * @param string $remote
     * @param int $depth
     * @return bool
     * @throws DropboxClientException
     */
    public function delete(string $remote, int $depth = 0) : bool
    {
        $_path = ($depth === 0) ? $this->getFullPath($remote) : $remote;
        $metadata = $this->client->delete($_path);
        return ($metadata instanceof FileMetadata || $metadata instanceof FolderMetadata);
    }

    /**
     * @param $items
     * @param int $depth
     * @return bool
     * @throws DropboxClientException
     */
    public function deleteItems($items, int $depth = 0) : bool
    {
        foreach ($items as $item) {
            if (array_key_exists('children', $item)
                && !$this->deleteItems($item['children'], $depth + 1))
            {
                return false;
            }
            if (!$this->delete($item['path'], $depth + 1)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $remote
     * @param int $depth
     * @return bool
     * @throws DropboxClientException
     */
    public function createFolder(string $remote, int $depth = 0): bool
    {
        $_path = ($depth === 0) ? $this->getFullPath($remote) : $remote;
        $metadata = $this->client->createFolder($_path);
        return ($metadata instanceof FolderMetadata);
    }

    public function exists(string $remote, int $depth = 0): bool
    {
        $_path = ($depth === 0) ? $this->getFullPath($remote) : $remote;
        if (Str::endsWith('/', $_path)) {
            $_path = substr($_path, 0, strlen($_path) - 1);
        }
        try {
            $metadata = $this->client->getMetadata($_path);
            return true;
        } catch(DropboxClientException $e) {
            return false;
        }
    }
}