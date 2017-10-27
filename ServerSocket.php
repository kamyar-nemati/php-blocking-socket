<?php

$pid = getmypid();

set_time_limit(0);

error_reporting(E_ALL);

define("BOT", "<-BOT->");
define("EOT", "<-EOT->");
define("TOT", "<-TOT->");

define("ENDL", "\n");

require_once dirname(__FILE__) . '/StateObject.php';

$somaxconn = (int)shell_exec("cat /proc/sys/net/core/somaxconn");

const _LENGTH = 1024;

$config = parse_ini_file("config.ini", TRUE);

$host = $config['server']['host'];
$port = $config['server']['port'];

try {
    $last_err_msg = "";
    
    $serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

    if ($serverSocket === FALSE) {
        $last_err_msg = socket_strerror(socket_last_error());
        throw new Exception("onCreate: {$last_err_msg}");
    }
    
    echo "Socket created" . ENDL;

    $foo = socket_bind($serverSocket, $host, $port);

    if ($foo === FALSE) {
        $last_err_msg = socket_strerror(socket_last_error($serverSocket));
        throw new Exception("onBind: {$last_err_msg}");
    }
    
    echo "Socket bound on {$host}:{$port}" . ENDL;

    $bar = socket_listen($serverSocket, $somaxconn);

    if ($bar === FALSE) {
        $last_err_msg = socket_strerror(socket_last_error($serverSocket));
        throw new Exception("onListen: {$last_err_msg}");
    }
    
    echo "Socket is listening" . ENDL;
} catch (Exception $ex) {
    echo "Failure {$ex->getMessage()}" . ENDL;
    die();
}

do {
    try {
        $clientSock = socket_accept($serverSocket);
        
        if ($clientSock === FALSE) {
            $last_err_msg = socket_strerror(socket_last_error($serverSocket));
            throw new Exception("onAccept: {$last_err_msg}");
        }

        $clientHost = NULL;
        $clientPort = NULL;
        $yow = socket_getpeername($clientSock, $clientHost, $clientPort);

        if ($yow === FALSE) {
            echo "Failed to get client's identity" . ENDL;
        }
        
        echo "Connection accepted from {$clientHost}:{$clientPort}" . ENDL;
    } catch (Exception $ex) {
        echo "Failure {$ex->getMessage()}" . ENDL;
        break;
    }
    
    $client = new StateObject($clientHost, $clientPort, $clientSock);

//    $child_pid = pcntl_fork();

//    if (getmypid() === $pid) {
//        unset($client);
//    }

    if (getmypid() === $pid/*$child_pid*/) {
        
        $aborted = FALSE;
        
        do {
            try {
                $packet = socket_read($client->socket, _LENGTH, PHP_NORMAL_READ);
                
                if ($packet === FALSE) {
                    $last_err_msg = socket_strerror(socket_last_error($client->socket));
                    throw new Exception("onRead: {$last_err_msg}");
//                    throw new Exception();
                }
                
                $client->buffer = trim($packet);
                
//                echo "Packet received: {$client->buffer}" . ENDL;
                
                if ($client->buffer === TOT) {
                    echo "Connection terminated by client" . ENDL;
                    $aborted = TRUE;
                    break;
                }

                $client->message .= $client->buffer;

                if (stripos($client->message, BOT) !== FALSE) {
                    $client->message = "";
                }
            } catch (Exception $ex) {
                echo "Failure {$ex->getMessage()}" . ENDL;
                break;
            }
        } while (stripos($client->message, EOT) === FALSE);
        
        if (!$aborted) {
            $client->message = str_replace(EOT, "", $client->message);
            $client->message .= ENDL;

            echo "Received message: {$client->message}" . ENDL;

            try {
                $gee = socket_write($client->socket, $client->message, strlen($client->message));

                if ($gee === FALSE) {
    //                $last_err_msg = socket_strerror(socket_last_error($client->socket));
    //                throw new Exception("onWrite: {$last_err_msg}");
                    throw new Exception();
                }
            } catch (Exception $ex) {
    //            echo "Failure {$ex->getMessage()}" . ENDL;
            }
        }
        
        try {
            socket_shutdown($client->socket, 2);
            socket_close($client->socket);
            
            echo "Connection closed from {$client->host}:{$client->port}" . ENDL;
        } catch (Exception $ex) {
            
        }
        
        unset($client);
    }
    
} while (TRUE); //signal handling to be put...

socket_shutdown($serverSocket, 2);
socket_close($serverSocket);

//$ps_stat = NULL;
//pcntl_wait($ps_stat);

die();









