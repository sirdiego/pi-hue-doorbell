<?php
include __DIR__ . '/vendor/autoload.php';
$config = include __DIR__ . '/config.php';

use Calcinai\PHPi\Pin;
use Calcinai\PHPi\Pin\PinFunction;

$loop = \React\EventLoop\Factory::create();
$board = \Calcinai\PHPi\Factory::create($loop);
$pin = $board->getPin($config['bcm_pin']);
$pin->setFunction(PinFunction::INPUT);

$client = new \GuzzleHttp\Client();
$timer = new \stdclass;
$timer->active = false;

$loop->addPeriodicTimer(0.1, function () use ($pin, $client, $config, $timer) {
    if($pin->getLevel() && !$timer->active) {
        $timer->active = true;
        $resp = $client->request('PUT', 'http://' . $config['hue_bridge_ip'] . '/api/' . $config['hue_bridge_user'] . '/lights/'.$config['hue_light_id'].'/state', [
            'json' => ['alert' => 'lselect']
        ]);
        echo $resp->getStatusCode() . PHP_EOL;
        echo $resp->getBody() . PHP_EOL;
    }
});

$loop->addPeriodicTimer(5, function () use ($timer) {
    $timer->active = false;
});

$loop->run();
