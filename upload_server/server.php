<?php
$server = new swoole_websocket_server('127.0.0', 9505);
$server->on('open', 
        function (swoole_websocket_server $server, $request) {
            echo "server: handshake success with fd{$request->fd}\n";
        });
$server->on('message', 
        function (swoole_websocket_server $server, $frame) {
            $update_path = 'uploads/';
            $data = json_decode($frame->data, 1);
            $exe = str_replace('/', '.', 
                    strstr(strstr($data['data'], ';', TRUE), '/'));
            $exe = $exe == '.jpeg' ? '.jpg' : $exe;
            $tmp = base64_decode(substr(strstr($data['data'], ','), 1));
            $path = $update_path . md5(rand(1000, 999)) . time() . $exe;
            file_put_contents($path, $tmp);
            unset($frame->data);
            $server->push($frame->fd, $path);
            echo "image path : {$path}\n";
            // {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
            // $server->push($frame->fd, "this is server");
        });
$server->on('close', 
        function ($ser, $fd) {
            echo "client {$fd} closed\n";
        });

$server->start();
?>