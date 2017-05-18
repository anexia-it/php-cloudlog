php-cloudlog
===

php-cloudlog is a client library for Anexia CloudLog.

Currently it only provides to push events to CloudLog. Querying is possible in a future release.

## Requirements

- php-rdkafka
- librdkafka >= 0.9.1

## Get started

```sh
$ composer require anexia-it/php-cloudlog
```

composer.json
```sh
{
    "require": {
        "anexia-it/php-cloudlog": "^0.2.0"
    }
}
```

## Quickstart

```php
<?php

use CloudLog\Client;

// Init CloudLog client
$client = new Client("index","ca.pem","cert.pem","cert.key");

// Push simple message
$client->pushEvents("message");

// Push document
$client->pushEvents('{
  "timestamp": 1495024205123,
  "user": "test",
  "severity": 1,
  "message": "My first CloudLog event"
}');
```
