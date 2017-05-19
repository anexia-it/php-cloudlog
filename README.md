php-cloudlog
===

php-cloudlog is a client library for Anexia CloudLog.

Currently it only provides to push events to CloudLog. Querying is possible in a future release.

There are two possible connection type:
- directly connected for high throughput
  - requires php-rdkafka (relies on librdkafka >= 0.9.1)
- http client


## Get started

```sh
$ composer require anexia-it/php-cloudlog
```

composer.json
```sh
{
    "require": {
        "anexia-it/php-cloudlog": "^0.3.0"
    }
}
```

## Quickstart

```php
<?php

use CloudLog\Client;

// Init CloudLog client
$client = Client::create("index","ca.pem","cert.pem","cert.key");

// Alternative CloudLog client (http)
$client = Client::createHttp("index","token");

// Push simple message
$client->pushEvent("message");

// Push document (multiple formats supported: object, assoc array, json string)

// object
class Event {
    public $timestamp;
    public $user;
    public $message;
    public $severity;
}

$event = new Event();
$event->timestamp = 1495024205123;
$event->user = "test";
$event->severity = 1;
$event->message = "My first CloudLog event";

$client->pushEvent($event);

// associative array
$client->pushEvent([
    "timestamp" => 1495024205123,
    "user" => "test",
    "severity" => 1,
    "message" => "My first CloudLog event"
]);

// json string
$client->pushEvent('{
  "timestamp": 1495024205123,
  "user": "test",
  "severity": 1,
  "message": "My first CloudLog event"
}');
```
