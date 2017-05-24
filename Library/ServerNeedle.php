<?php
/**
 * 服务器探针
 * @frank
 */
namespace DwComment\Library;
use Phalcon\Di, DwComment\Responses\JsonResponse, DwComment\Responses\CsvResponse;
use Phalcon\Http\Request;
use DwComment\Modules\V1\Controllers\RestController;

class ServerNeedle extends RestController {

    public function __construct () {
    }

    /**
     * 服务器操作系统名称
     *
     * @return string
     */
    public static function os_name () {
        return PHP_OS;
    }
    // 判断请求是否为Ajax请求
    public static function isAjax () {
        $request = new Request();
        return $request->isAjax();
    }
    // 获取请求服务器的host
    public static function getHttpHost () {
        $request = new Request();
        return $request->getHttpHost();
    }
    // 等同于$_SERVER['REMOTE_ADDR']
    public static function getServer () {
        $request = new Request();
        return $request->getServer();
    }
    // 获取请求的类型
    public static function getMethod () {
        $request = new Request();
        return $request->getMethod();
    }
    // 判断是否是Post
    public static function isPost () {
        $request = new Request();
        return $request->isPost();
    }
    // 获取请求的URL
    public static function getURI () {
        $request = new Request();
        return $request->getURI();
    }

    /**
     * 服务器版本名称
     *
     * @return string
     */
    public static function os_version () {
        return php_uname('r');
    }

    /**
     * 服务器域名
     *
     * @return mixed
     */
    public static function server_host () {
        return $_SERVER['SERVER_NAME'];
    }

    /**
     * 服务器IP
     *
     * @return mixed
     */
    public static function server_ip () {
        $request = new Request();
        return $request->getServerAddress();
    }

    /**
     * web服务器信息
     *
     * @return mixed
     */
    public static function server_software () {
        return $_SERVER['SERVER_SOFTWARE'];
    }

    /**
     * 服务器语言
     *
     * @return string
     */
    public static function accept_language () {
        return getenv("HTTP_ACCEPT_LANGUAGE");
    }

    /**
     * 服务器端口
     *
     * @return string
     */
    public static function server_port () {
        return $_SERVER['SERVER_PORT'];
    }

    /**
     * PHP版本
     *
     * @return string
     */
    public static function php_version () {
        return PHP_VERSION;
    }

    /**
     * PHP运行方式
     *
     * @return string
     */
    public static function php_sapi_name () {
        return strtoupper(php_sapi_name());
    }
}