<?php
include __DIR__ . '/vendor/autoload.php';
$config = include __DIR__ . '/config.php';

use Calcinai\PHPi\Pin;
use Calcinai\PHPi\Pin\PinFunction;
use Monolog\Logger;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\RollbarHandler;

$log = new Logger('pi-hue-doorbell');
$log->pushHandler(new SyslogHandler('pi-hue-doorbell'));
if(isset($config['rollbar'])) {
    Rollbar::init($config['rollbar']);
    $log->pushHandler(new RollbarHandler(Rollbar::$instance));
}

$loop = \React\EventLoop\Factory::create();
$board = \Calcinai\PHPi\Factory::create($loop);
$pin = $board->getPin($config['bcm_pin']);
$pin->setFunction(PinFunction::INPUT);

$client = new \GuzzleHttp\Client();
$timer = new \stdclass;
$timer->active = false;

$loop->addPeriodicTimer(0.1, function () use ($pin, $client, $config, $timer, $log) {
    if($pin->getLevel() && !$timer->active) {
        try {
            $timer->active = true;
            $resp = $client->request('PUT', 'http://' . $config['hue_bridge_ip'] . '/api/' . $config['hue_bridge_user'] . '/lights/'.$config['hue_light_id'].'/state', [
                'json' => ['alert' => 'lselect']
            ]);
            echo $resp->getStatusCode() . PHP_EOL;
        } catch (GuzzleHttp\Exception\TransferException $e) {
            $timer->active = false;
            $log->error($e->getMessage());
        }
    }
});

$loop->addPeriodicTimer(5, function () use ($timer) {
    $timer->active = false;
});

$loop->run();
