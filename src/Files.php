<?php

/**
 * 上传类
 */

namespace dux;

class Files {


    private $config = [
        'max_size' => 1048576, //保存文件大小限制 默认10M
        'allow_exts' => [], //允许的文件后缀
        'save_rule' => 'md5', //命名规则
    ];

    private $driverConfig = [];

    protected $driver = null;
    protected $object = null;


    /**
     * 实例化类
     * @param string $driver
     * @param array $config
     * @throws \Exception
     */
    public function __construct(string $driver, array $config = []) {
        $this->driver = $driver;
        if (!class_exists($this->driver)) {
            throw new \Exception('The file driver class does not exist', 500);
        }
        $config['url'] = trim(str_replace('\\', '/', $config['url']), '/');
        $config['domain'] = trim(str_replace('\\', '/', $config['domain']), '/');
        $this->driverConfig = array_merge($this->driverConfig, $config);
        if (empty($this->driverConfig)) {
            throw new \Exception($this->driver . ' file config error', 500);
        }
    }

    /**
     * 保存大小
     * @param int $size
     * @return $this
     */
    public function setSize($size = 1048576) {
        $this->config['max_size'] = $size;
        return $this;
    }

    /**
     * 允许个数
     * @param array $ext
     * @return $this
     */
    public function setExt($ext = []) {
        $this->config['allow_exts'] = $ext;
        return $this;
    }

    /**
     * 命名规则
     * @param $rule
     * @return $this
     */
    public function setRule($rule) {
        $this->config['save_rule'] = $rule;
        return $this;
    }

    /**
     * 保存文件
     * @param $file
     * @param string $name
     * @param bool $verify
     * @param callable $callback
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function save($file, string $name, bool $verify = false, callable $callback) {
        if (is_string($file)) {
            if (preg_match("/^http(s)?:\\/\\/.+/", $file) || substr($file, 0, 2) == '//') {
                if (substr($file, 0, 2) == '//') {
                    $file = 'http:' . $file;
                }
                $url = $file;
                $file = fopen('php://temp/' . md5($url), 'w');
                $request = (new \GuzzleHttp\Client())->request('GET', $url, [
                    'headers' => [
                        'Referer' => $url,
                    ],
                    'timeout' => 10
                ]);
                fwrite($file, $request->getBody()->getContents());
                rewind($file);
            } else {
                $file = fopen($file, 'r');
                if (!$file) {
                    throw new \Exception("The file does not exist!");
                }
            }
        }
        $name = str_replace('\\', '/', $name);
        if (!$verify) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $content = stream_get_contents($file);
        rewind($file);
        $size = strlen($content);
        $mime = $finfo->buffer($content);
        if (empty($ext) || !in_array($ext, $this->config['allow_exts'])) {
            $ext = (new \Mimey\MimeTypes())->getExtension($mime);
        }
        $ext = strtolower($ext);
        if ($this->config['allow_exts'] && !in_array($ext, $this->config['allow_exts'])) {
            throw new \Exception("Save the format is not supported!");
        }
        $pathInfo = pathinfo($name);
        $dir = trim(trim($pathInfo['dirname'], '.'), '/');
        $dir = $dir ? "/{$dir}/" : '/';
        if (!$this->getObj()->checkPath($dir)) {
            throw new \Exception("Do not use the file directory!");
        }
        $name = $pathInfo['filename'];
        $fun = $this->config['save_rule'];
        if ($fun) {
            $name = call_user_func($fun, $content);
        }
        $name = $name . '.' . $ext;
        $info = ['dir' => $dir, 'name' => $name, 'size' => $size, 'mime' => $mime, 'ext' => $ext];
        if ($callback) {
            $file = call_user_func_array($callback, [$file, $info]);
            if (!$file) {
                throw new \Exception("Returns the file does not exist!");
            }
        }
        $url = $this->getObj()->save($file, $info);
        @fclose($file);
        $info['url'] = $url;
        return $info;
    }

    /**
     * 删除文件
     * @param string $name
     * @return mixed
     * @throws \Exception
     */
    public function del(string $name) {
        $name = trim(str_replace('\\', '/', $name), '/');
        $name = '/' . $name;
        return $this->getObj()->del($name);
    }

    /**
     * 驱动对象
     * @return files\FilesInterface|null
     * @throws \Exception
     */
    public function getObj() {
        if ($this->object) {
            return $this->object;
        }
        $this->object = new $this->driver($this->driverConfig);
        if (!$this->object instanceof \dux\files\FilesInterface) {
            throw new \Exception('The send class must interface class inheritance', 500);
        }
        return $this->object;
    }

}