<?php

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

// Define API keys
//const OPENAI_API_KEY = 'your_openai_apikey';
//
//// Register on this website to get https://elevenlabs.io/
//const ELEVENLABS_API_KEY = 'your_elevenlabs_apikey';
//
//// Create based on this API address https://elevenlabs.io/docs/api-reference/add-voice
//const VOICE_ID = 'your_voice_id';
//
//// OpenAI API address, can be replaced with your own
//const OPENAI_URL = '';
const OPENAI_API_KEY = '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';
const ELEVENLABS_API_KEY = '3a626ceaef5e28f2a796d27803731bb2';
const VOICE_ID = '1ttHju2T92ev9Ghp6BYC';
const OPENAI_URL = 'https://gptapi.usa.ysbluo.com/v1';

// Set OpenAI API key
$gpt_client = OpenAI::factory()
    ->withApiKey(OPENAI_API_KEY)
    ->withBaseUri(OPENAI_URL)
    ->withHttpClient(new Client(['verify' => false,'http_errors' => false,'stream' => true]))
    ->make();

function my_generator($response): Generator
{
    foreach ($response as $chunk) {
        $delta = $chunk->choices[0]->delta;
//        echo $delta->content;
        yield $delta->content;
    }
}

function chat_completion($query): Generator
{
    global $gpt_client;

    $response = $gpt_client->chat()->createStreamed([
        'model' => 'gpt-3.5-turbo',
        'messages' => [['role' => 'user', 'content' => $query]],
        'temperature' => 1,
    ]);

    return my_generator($response);
}

function text_chunker($chunks): Generator
{
    $splitters = [".", ",", "?", "!", ";", ":", "—", "-", "(", ")", "[", "]", "}", " "];
    $buffer = "";

    foreach ($chunks as $text) {
        $text = $text ?? '';

        $startsWithAny = array_reduce($splitters, fn($carry, $splitter) => $carry || str_starts_with($text, $splitter), false);

        if ($startsWithAny) {
            yield $buffer . " ";
            $buffer = $text;
        } elseif (array_reduce($splitters, fn($carry, $splitter) => $carry || str_ends_with($text, $splitter), false)) {
            yield $buffer . $text[0] . " ";
            $buffer = substr($text, 1);
        } else {
            $buffer .= $text;
        }
    }

    if ($buffer) {
        yield $buffer . " ";
    }
}

class ElevenLabsTextToSpeech
{
    private $socket;
    private $voiceId;
    private $modelId;
    private $apiKey;

    // Record connection status for storing whether the connection has been authenticated
    private array $connectionStatus = [];

    public function __construct($voiceId, $modelId,$apiKey)
    {
        $this->voiceId = $voiceId;
        $this->modelId = $modelId;
        $this->apiKey = $apiKey;
    }

    public function startWebSocket()
    {
        $this->socket = new Worker("websocket://0.0.0.0:8080");

        $this->socket->onConnect = function ($connection) {
            // Get the token from the request header
            echo date('H:i:s')."Received a client connection\n";
        };

        $this->socket->onMessage = function ($connection, $data) {
            $decodedData = json_decode($data, true);

            echo date('H:i:s')."Received client message:\n";
            // Check if the token has been verified
            if (empty($this->connectionStatus[$connection->id])){
                if(empty($decodedData['token'])){
                    echo "No token provided".PHP_EOL;
                    $connection->send(json_encode(['code'=>1,'msg'=>'Token cannot be empty!'],JSON_UNESCAPED_UNICODE));
                    $connection->close();
                }else{
                    // Start token verification
                    var_dump($decodedData['token']);
                    $this->connectionStatus[$connection->id] = true;
                }
            } elseif (isset($decodedData['text'])) {
                echo $decodedData['text'].PHP_EOL;
                // Call the GPT API to get chat content
                $text_iterator = chat_completion($decodedData['text']);

                // Convert text to speech and send the response
                $this->sendTextToSpeech($text_iterator,$connection);
            }
        };

        $this->socket->onClose = function ($connection){
            unset($this->connectionStatus[$connection->id]);
        };

        Worker::runAll();
    }

    private function sendTextToSpeech($text_iterator,$client_conn)
    {
        $uri = "ws://api.elevenlabs.io:443/v1/text-to-speech/{$this->voiceId}/stream-input?model_id={$this->modelId}";

        $ws = new AsyncTcpConnection($uri);

        // Set to use ssl for encryption, making it wss
        $ws->transport = 'ssl';

        $ws->onConnect = function () use ($text_iterator,$ws) {
            echo date('H:i:s')."Connected to ElevenLabs\n";

            $message = [
                'text' => " ",
                'voice_settings' => [
                    'similarity_boost' => 0.75,
                    'stability' => 0.5,
                    'style' => 0,
                    'use_speaker_boost' => true
                ],
                'optimize_streaming_latency'=>2,
                'generation_config' => [
                    'chunk_length_schedule' => [120, 160, 250, 290],
                ],
                "xi_api_key"=>$this->apiKey, // replace with your API key
            ];

            // Send an empty string for the first time
            $ws->send(json_encode($message));

            // Loop through the generator to get data, calling the text-to-speech API in the loop
            foreach ($text_iterator as $content){
                // Loop to output the content of the GPT response
                echo $content;
                if(empty($content)){
                    continue;
                }
                $message = [
                    'text' => $content,
                ];
                $ws->send(json_encode($message));
            }
            // Add an empty string to indicate the end
            $this->sendEndOfSequence($ws);

            echo PHP_EOL.date('H:i:s')."Finished sending text-to-speech requests to ElevenLabs\n";
        };

        $ws->onMessage = function ($connection, $data) use($client_conn) {
            echo date('H:i:s')."Received response from ElevenLabs:".PHP_EOL;
            $decodedData = json_decode($data, true);

            // Directly pass the ElevenLabs response to the client
            $client_conn->send($data);
            if (empty($decodedData['audio'])) {
                var_dump($data);
            }else{
                // Handle audio data, you can save, play, or perform other operations here
                // Here you can save the audio locally
                file_put_contents("t2.mp3",base64_decode($decodedData['audio']),FILE_APPEND);
            }

            if (isset($decodedData['isFinal']) && $decodedData['isFinal']) {
                // Logic for ending the connection
                echo date('H:i:s')."Speech generation completed, and sent completion to the client\n";
                $connection->close();
            }
        };
        $ws->onClose = function (){
            echo "Closed ElevenLabs connection\n";
        };

        $ws->connect();
    }

    private function sendEndOfSequence($ws)
    {
        // Logic for sending the end signal
        $message = [
            'text' => "",
        ];

        $ws->send(json_encode($message));
    }
}

// Usage example
$modelId = 'eleven_multilingual_v2'; // Replace with the actual Model ID

$textToSpeech = new ElevenLabsTextToSpeech(VOICE_ID, $modelId,ELEVENLABS_API_KEY);
$textToSpeech->startWebSocket();
