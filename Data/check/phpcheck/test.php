<?php

class Ws{
    private $shell;
    private $connection;
    private $isConnection;

    private $ws;
    public function __construct(){
        //监听0.0.0.0:8081端口
        $this->ws = new Swoole\WebSocket\Server('0.0.0.0', 8081);

        $this->ws->on("open", [$this, "onOpen"]);
        $this->ws->on("message", [$this, "onMessage"]);
        $this->ws->on("close", [$this, "onClose"]);

        $this->ws->start();
    }

    public function onOpen($ws, $request){
        var_dump($request->fd, $request->server);
    }

    public function onMessage($ws, $frame){
        $data = json_decode($frame->data, true);
        switch(key($data)){
            case "data":    
                fwrite($this->shell[$frame->fd], $data['data']);
                usleep(800);
                while($line = fgets($this->shell[$frame->fd])){
                    $ws->push($frame->fd, $line);
                }
            break;
            case "auth":    
                if($this->loginSSH($data["auth"], $frame)){
                    $ws->push($frame->fd, "连接中...");
                    while($line = fgets($this->shell[$frame->fd])){
                        $ws->push($frame->fd, $line);
                    }
                } else {
                    $ws->push($frame->fd, "登录失败");
                }
            break;
            default:
                if($this->isConnection[$frame->fd]){
                    while($line = fgets($this->shell[$frame->fd])){
                        $ws->push($frame->fd, $line);
                    }
                }
            break;
        }
    }

    public function onClose($ws, $fd){
        $this->isConnection[$fd] = false;
        echo "client-{$fd} is closed\n";
    }

    public function loginSSH($auth, $frame){
        $this->connection[$frame->fd] = ssh2_connect($auth['server'], $auth['port']);
        if(ssh2_auth_password($this->connection[$frame->fd], $auth['user'], $auth['password'])){
            $this->shell[$frame->fd] = ssh2_shell($this->connection[$frame->fd], 'xterm', null, 80, 24, SSH2_TERM_UNIT_CHARS);
            sleep(1);   
            $this->isConnection[$frame->fd] = true;
            return true;
        } else {
            return false;
        }
    }
}

new ws();
