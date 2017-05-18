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
     * Creates a new client
     *
     * @param string $index Index identifier
     * @param string $caFile ca file path
     * @param string $certFile cert file path
     * @param string $keyFile key file path
     * @throws \Exception if configuration is invalid
     */
    public function __construct($index, $caFile, $certFile, $keyFile) {

        $this->index = $index;
        $this->caFile = $caFile;
        $this->certFile = $certFile;
        $this->keyFile = $keyFile;

        $this->checkConfiguration();
        $this->createProducer();

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
        $producer->setLogLevel(7);
        $producer->addBrokers($this->brokers);

        $topicConfig = new TopicConf();
        $topicConfig->set("request.required.acks", -1);
        $topicConfig->set("compression.codec", "gzip");

        $this->topic = $producer->newTopic($this->index, $topicConfig);

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
            "cloudlog_client_type" => "php-client",
            "cloudlog_source_host" => gethostname()
        );
        $timestamp = round(microtime(true)*1000);
        foreach($events as $event) {
            $event = $this->addMetadata($timestamp, $event, $meta);
            echo "send event: ".$event."\n";
            $this->topic->produce(RD_KAFKA_PARTITION_UA, 0, $event);
        }
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
        $data = @json_decode($event, true);
        if(!is_array($data)) {
            $data = array(
                "message" => $event,
                "timestamp" => $timestamp
            );
        }
        $data = array_merge($data, $meta);
        return json_encode($data);
    }

    /**
     * Validates the configuration
     *
     * @throws \Exception if configuration is invalid
     */
    private function checkConfiguration() {

        if(!file_exists($this->caFile)) {
            throw new \Exception("ca file not found at: ".$this->caFile);
        }
        if(!file_exists($this->certFile)) {
            throw new \Exception("certificate file not found at: ".$this->certFile);
        }
        if(!file_exists($this->keyFile)) {
            throw new \Exception("key file file not found at: ".$this->keyFile);
        }

    }

}

?>
