php-cloudlog
===

php-cloudlog is a client library for Anexia CloudLog.

Currently it only provides to push events to CloudLog. Querying is possible in a future release.

There are two possible connection type:
- directly connected for high throughput
  - requires php-rdkafka (relies on librdkafka >= 0.9.1)
- http client


## Install

```sh
composer require anexia-it/php-cloudlog
```

## Quickstart

```php
<?php

use CloudLog\Client;

// Init CloudLog client
$client = Client::create("index","ca.pem","cert.pem","cert.key");

// Alternative CloudLog client (http)
$client = new Client::createHttp("index","token");

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
