<?php

use Burdock\Config\Config;
use Burdock\CloudStorage\DropboxAdapter;
use Burdock\Utils\Str;
use Burdock\Utils\Fs;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

const DS = DIRECTORY_SEPARATOR;

class DropboxAdapterTest extends TestCase
{
    protected $logger  = null;
    protected $adapter = null;
    protected static $tmp_base_dir = null;

    public static function setUpBeforeClass(): void
    {
        self::$tmp_base_dir = realpath(__DIR__.DS.'..').DS.'tmp';
        if (file_exists(self::$tmp_base_dir)) Fs::rmDir(self::$tmp_base_dir);
        mkdir(self::$tmp_base_dir);
    }

    public function setUp(): void
    {
        $cwd = __DIR__;
        $config  = Config::load($cwd.'/config.json');
        $logger  = new Logger('DbxTest');
        $handler = new StreamHandler('php://stdout', Logger::INFO);
        $logger->pushHandler($handler, Logger::DEBUG);
        $this->adapter = new DropboxAdapter($config->getValue('dropbox'), $logger);
    }

    public function test_fileOperation()
    {
        $r_dir = Str::randomChars(8, ['/','\\',':']);
        $jpg   = 'sky_mountain_sunflower.jpg';
        $l_jpg = __DIR__ . DS . $jpg;
        $r_jpg = $r_dir . '/' . $jpg;
        $this->adapter->upload($l_jpg, $r_jpg);

        $xls   = 'sample_template.xlsx';
        $l_xls = __DIR__ . DS . $xls;
        $r_xls = $r_dir . '/' . $xls;
        $this->adapter->upload($l_xls, $r_xls);

        $d_dir = self::$tmp_base_dir . DS . $r_dir;
        mkdir($d_dir);

        $d_jpg = $this->adapter->download($r_jpg, $d_dir.DS.$jpg);
        $original = new \SplFileObject($l_jpg);
        $download = new \SplFileObject($d_jpg);
        $this->assertEquals($original->getSize(), $download->getSize());

        $d_xls = $this->adapter->download($r_xls, $d_dir.DS.$xls);
        $original = new \SplFileObject($l_xls);
        $download = new \SplFileObject($d_xls);
        $this->assertEquals($original->getSize(), $download->getSize());

        $list = $this->adapter->getList('/');
        $this->adapter->deleteRecursive($list);
        $download = null; //if not exists, causes Resource temporarily unavailable on rmdir...
        Fs::rmDir($d_dir);
    }

    public static function tearDownAfterClass() : void
    {
        Fs::rmDir(self::$tmp_base_dir);
    }
}
