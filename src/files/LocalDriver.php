<?php

/**
 * 本地上传驱动
 */

namespace dux\files;

class LocalDriver implements FilesInterface {

    protected $config = [
        'save_path' => ''
    ];

    public function __construct($config = []) {
        $config['save_path'] = str_replace('\\', '/', $config['save_path']);
        $config['save_path'] = rtrim($config['save_path']);
        $this->config = $config;
    }

    public function checkPath($dir) {
        $dir = $this->config['save_path'] . $dir;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!is_writable($dir)) {
            throw new \Exception("Storage directory without permission!");
        }
        return true;
    }

    public function save($data, $info) {
        $absolutePach = $this->config['save_path'] . $info['dir'] . $info['name'];
        $relativePath = $info['dir'] . $info['name'];
        if (is_file($absolutePach)) {
            return $relativePath;
        }
        $file = fopen($absolutePach, "w+");
        if (!stream_copy_to_stream($data, $file)) {
            throw new \Exception("The file save failed!");
        }
        fclose($file);
        return $relativePath;
    }

    public function del($name) {
        $dir = $this->config['save_path'] . '/' . trim($name, '/');
        if (is_file($name)) {
            return @unlink($name);
        }
        return true;
    }
}
