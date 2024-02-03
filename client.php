<?php
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

require_once __DIR__ . '/vendor/autoload.php';

$worker = new Worker();

$worker->onWorkerStart = function ($worker) {

    $con = new AsyncTcpConnection('ws://127.0.0.1:8080');

    $con->onConnect = function (AsyncTcpConnection $con) {
        // Send token verification message first
        $con->send(json_encode(['token' => 'your_valid_token']));
        // Now you can send chat content
        $con->send(json_encode(['text' => 'hello world!']));
    };

    $con->onMessage = function (AsyncTcpConnection $con, $data) {
        $decodedData = json_decode($data, true);

        if (isset($decodedData['audio'])) {
            // Process audio data, you can save, play, or perform other operations
            // Here you can save this audio to a local file
            echo "Received response, writing to mp3 file..." . PHP_EOL;
            file_put_contents("test.mp3", base64_decode($decodedData['audio']), FILE_APPEND);
        } else {
            var_dump($data);
        }

        if (isset($decodedData['isFinal']) && $decodedData['isFinal']) {
            // Logic for ending the connection
            echo date('H:i:s') . "Received complete, closing the connection\n";
            $con->close();
        }
    };

    $con->onClose = function ($connection) {
        echo "Connection closed\n";
    };

    $con->connect();
};

Worker::runAll();
