<?php
namespace CloudLog;

use RdKafka\Conf;
use RdKafka\Producer;
use RdKafka\ProducerTopic;
use RdKafka\TopicConf;

/**
 * PHP client to push and query data to Anexia CloudLog
 */
class Client {

    /**
     * Index indentifier
     * @var string $index
     */
    private $index;

    /**
     * CA file path
     * @var string $index
     */
    private $caFile;

    /**
     * Certificate file path
     * @var string $certFile
     */
    private $certFile;

    /**
     * Key file path
     * @var string $keyFile
     */
    private $keyFile;

    /**
     * Token for http api
     * @var string $token
     */
    private $token;

    /**
     * ProducerTopic $topic
     * @var ProducerTopic $topic
     */
    private $topic;

    /**
     * Default brokers
     * @var string $brokers
     */
    private $brokers = "anx-bdp-broker0401.bdp.anexia-it.com:443,anx-bdp-broker0402.bdp.anexia-it.com:443,anx-bdp-broker0403.bdp.anexia-it.com:443";

    /**
     * Default api
     * @var string $api
     */
    private $api = "https://anx-bdp-api0401.bdp.anexia-it.com";

    /**
     * @var bool $isHttp
     */
    private $isHttp = false;

    /**
     * @var string $hostname
     */
    private $hostname = "";

    /**
     * Default constructor
     */
    private function __construct() {
        $this->hostname = gethostname();
    }

    /**
     * Creates a new client (kafka)
     *
     * @param string $index Index identifier
     * @param string $caFile ca file path
     * @param string $certFile cert file path
     * @param string $keyFile key file path
     * @throws \Exception if configuration is invalid
     *
     * @return Client
     */
    public static function create($index, $caFile, $certFile, $keyFile) {

        $client = new Client();

        $client->index = $index;
        $client->caFile = $caFile;
        $client->certFile = $certFile;
        $client->keyFile = $keyFile;

        $client->checkKafkaConfiguration();
        $client->createProducer();

        $client->isHttp = false;

        return $client;

    }

    /**
     * Creates a new client (http)
     *
     * @param string $index Index identifier
     * @param string $token Auth token
     * @throws \Exception if configuration is invalid
     *
     * @return Client
     */
    public static function createHttp($index, $token) {

        $client = new Client();

        $client->index = $index;
        $client->token = $token;

        $client->checkHttpConfiguration();

        $client->isHttp = true;

        return $client;

    }

    /**
     * Create kafka producer
     */
    private function createProducer() {

        $config = new Conf();
        $config->set("security.protocol", "ssl");
        $config->set("ssl.ca.location", $this->caFile);
        $config->set("ssl.certificate.location", $this->certFile);
        $config->set("ssl.key.location", $this->keyFile);

        $producer = new Producer($config);
        $producer->addBrokers($this->brokers);

        $topicConfig = new TopicConf();
        $topicConfig->set("request.required.acks", -1);
        $topicConfig->set("compression.codec", "gzip");

        $this->topic = $producer->newTopic($this->index, $topicConfig);

    }

    /**
     * Sends an events to CloudLog
     * @param string|array $event
     */
    public function pushEvent($event) {
        $this->pushEvents([$event]);
    }

    /**
     * Sends one or more events to CloudLog
     * @param string|array $events
     */
    public function pushEvents($events) {
        if(!is_array($events)) {
            $events = [$events];
        }
        $meta = array(
            "cloudlog_client_type" => $this->isHttp ? "php-client-http" : "php-client-kafka",
            "cloudlog_source_host" => $this->hostname
        );
        $timestamp = round(microtime(true)*1000);

        if($this->isHttp) {
            $parsedEvents = array();
            foreach($events as $event) {
                $parsedEvents[] = $this->addMetadata($timestamp, $event, $meta);
            }
            $this->sendOverHttp($parsedEvents);
        } else {
            foreach($events as $event) {
                $event = $this->addMetadata($timestamp, $event, $meta);
                $this->topic->produce(RD_KAFKA_PARTITION_UA, 0, $event);
            }
        }

    }

    /**
     * Send events to http api
     *
     * @param $events
     * @return bool
     */
    private function sendOverHttp($events) {

        $url = $this->api . "/v1/index/" . $this->index . "/data";
        $body = '{ "records": ' . json_encode($events) . ' }';
        $header = array(
            "Authorization: " . $this->token,
            "Content-Type: application/json"
        );

        if(function_exists('curl_version')) {

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_exec($ch);

            if (!curl_errno($ch)) {
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($status == 201) {
                    return true;
                }
            }

        } else {

            $opts = array('http' =>
                array(
                    'method'  => 'POST',
                    'header'  => $header,
                    'content' => $body
                )
            );
            $context = stream_context_create($opts);
            file_get_contents($url, false, $context);
            if(isset($http_response_header[0])) {
                if (strpos($http_response_header[0], '201') !== false) {
                    return true;
                }
            }
        }

        return false;

    }

    /**
     * Add meta data to event
     *
     * @param int $timestamp
     * @param string $event
     * @param array $meta
     *
     * @return string
     */
    private function addMetadata($timestamp, $event, $meta) {

        $data = $event;

        if(is_object($event)) {
            $data = (array)$event;
        }
        if(!is_array($data)) {
            $data = @json_decode($event, true);
        }
        if(!is_array($data)) {
            $data = array(
                "message" => $event
            );
        }
        if(!isset($data["timestamp"])) {
            $data["timestamp"] = $timestamp;
        }
        $data = array_merge($data, $meta);

        return json_encode($data);
    }

    /**
     * Validates the kafka configuration
     *
     * @throws \Exception if configuration is invalid
     */
    private function checkKafkaConfiguration() {

        if(!file_exists($this->caFile)) {
            throw new \Exception("ca file not found at: ".$this->caFile);
        }
        if(!file_exists($this->certFile)) {
            throw new \Exception("certificate file not found at: ".$this->certFile);
        }
        if(!file_exists($this->keyFile)) {
            throw new \Exception("key file file not found at: ".$this->keyFile);
        }
        if(empty($this->index)) {
            throw new \Exception("missing index");
        }

    }

    /**
     * Validates the http configuration
     *
     * @throws \Exception if configuration is invalid
     */
    private function checkHttpConfiguration() {

        if(empty($this->token)) {
            throw new \Exception("missing token");
        }
        if(empty($this->index)) {
            throw new \Exception("missing index");
        }

    }

}

?>
