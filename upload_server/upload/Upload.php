<?php
ini_set('memory_limit', '512M');
date_default_timezone_set('Asia/Shanghai');
require_once('Upload.php');
// http服务绑定的ip及端口
$serv = new swoole_http_server("0.0.0.0", 9502);
/**
 * 处理请求
 */
$serv->on('Request', function($request, $response) {
$up = new fileupload;
    //设置属性(上传的位置， 大小， 类型， 名是是否要随机生成)
    $up -> set("path", "./images/");
    $up -> set("maxsize", 2000000);
    $up -> set("allowtype", array("gif", "png", "jpg","jpeg"));
    $up -> set("israndname", false);
    if($up -> upload("pic")) {
    $result = $up->getFileName();
    } else {
       $result = $up->getErrorMsg();
    }
 	$response->cookie("User", "Frank");
	$response->end($result);
});
$serv->start();
