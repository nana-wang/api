<?php
namespace DwComment\Library;
use DwComment\Library\WebSocketClient;
use DwComment\Modules\V1\Controllers\RestController;
use Phalcon\Config;

class FunctionCommon extends RestController {

    public $redisHandler;

    public function __construct () {
        parent::__construct();
        $this->redisHandler = $this->redis;
    }

    public static function formate ($time) {
        $now = time();
        $year = ($now - $time) / (60 * 60 * 24 * 30 * 12);
        if ($year > 1) {
            if ($year > 2) {return date("Y-m-d", $time);}
            return intval($year) . " 年前";
        }
        $Month = ($now - $time) / (60 * 60 * 24 * 30);
        if ($Month > 1) {return intval($Month) . " 月前";}
        $Day = ($now - $time) / (60 * 60 * 24);
        if ($Day > 1) {return intval($Day) . " 天前";}
        $Hours = ($now - $time) / (60 * 60);
        if ($Hours > 1) {return intval($Hours) . " 小时前";}
        $Hours = ($now - $time) / 60;
        if ($Hours > 1) {return intval($Hours) . " 分钟前";}
        $Seconds = $now - $time;
        if ($Seconds > 1) {
            return intval($Seconds) . " 秒前";
        } else {
            return '刚刚';
        }
    }

    /**
     * 真实ip地址
     *
     * @return unknown|string
     */
    public static function server_ip () {
        static $realip;
        if (isset($_SERVER)) {
            if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
            } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
                $realip = $_SERVER["HTTP_CLIENT_IP"];
            } else {
                $realip = $_SERVER["REMOTE_ADDR"];
            }
        } else {
            if (getenv("HTTP_X_FORWARDED_FOR")) {
                $realip = getenv("HTTP_X_FORWARDED_FOR");
            } else if (getenv("HTTP_CLIENT_IP")) {
                $realip = getenv("HTTP_CLIENT_IP");
            } else {
                $realip = getenv("REMOTE_ADDR");
            }
        }
        return $realip;
    }



    public static function build ($list, $pk = 'id', $pid = 'comment_parent_id', $child = 'children', $root = 0) {
        $tree = array();
        if (is_array($list)) {
            // 创建基于主键的数组引用
            $refer = array();
            foreach ($list as $key => $data) {
                $refer[$data[$pk]] = & $list[$key];
            }
            foreach ($list as $key => $data) {
                // 判断是否存在parent
                $parentId = $data[$pid];
                if ($root == $parentId) {
                    $tree[] = & $list[$key];
                } else {
                    if (isset($refer[$parentId])) {
                        $parent = & $refer[$parentId];
                        $parent[$child][] = & $list[$key];
                    }
                }
            }
        }
        return $tree;
    }

    
   
}
