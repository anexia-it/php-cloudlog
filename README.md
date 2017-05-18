php-cloudlog
===

php-cloudlog is a client library for Anexia CloudLog.

Currently it only provides to push events to CloudLog. Querying is possible in a future release.

There are two connection types:
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
$client->pushEvents("message");

// Push document
$client->pushEvents('{
  "timestamp": 1495024205123,
  "user": "test",
  "severity": 1,
  "message": "My first CloudLog event"
}');
```
