<?php

namespace Kafka\Consumer\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Kafka\Consumer\Contracts\Consumer;
use Kafka\Consumer\Entities\Config\Sleep;
use Kafka\Consumer\Exceptions\InvalidCommitException;
use Kafka\Consumer\Exceptions\InvalidConsumerException;

class PhpKafkaConsumerCommand extends Command
{
    protected $signature = 'arquivei:php-kafka-consumer {--topic=} {--consumer=} {--groupId=} {--maxAttempt=} {--commit=} {--dlq=} {--sleep=} {--maxMessage=}';

    protected $description = 'A consumer of Kafka in PHP';

    private $dlq;
    private $topic;
    private $sleep;
    private $config;
    private $groupId;
    private $maxAttempt;
    private $maxMessage;

    public function __construct()
    {
        parent::__construct();
        $this->config = config('php-kafka-consumer');
    }

    public function handle()
    {
        $consumer = $this->option('consumer');
        $commmit = $this->option('commit');
        $this->validateConsumer($consumer);
        $this->validateCommit($commmit);

        $this->dlq = $this->option('dlq');
        $this->topic = $this->option('topic');
        $this->sleep = (int)$this->option('sleep');
        $this->groupId = $this->option('groupId');
        $this->maxAttempt = (int)$this->option('maxAttempt');
        $this->maxMessage = (int)$this->option('maxMessage');


        $config = new \Kafka\Consumer\Entities\Config(
            new \Kafka\Consumer\Entities\Config\Sasl(
                $this->config['sasl']['username'],
                $this->config['sasl']['password'],
                $this->config['sasl']['mechanisms']
            ),
            $this->getTopic(),
            $this->config['broker'],
            $commmit,
            $this->getGroupId(),
            new $consumer(),
            new \Kafka\Consumer\Entities\Config\MaxAttempt($this->getMaxAttempt()),
            $this->config['securityProtocol'],
            $this->getDlq(),
            new Sleep($this->getSleepTime()),
            $this->getMaxMessage()
        );

        (new \Kafka\Consumer\Consumer($config))->consume();
    }

    private function getTopic(): string
    {
        return (is_string($this->topic) && strlen($this->topic) > 1) ? $this->topic : $this->config['topic'];
    }

    private function getGroupId(): string
    {
        return (is_string($this->groupId) && strlen($this->groupId) > 1) ? $this->groupId : $this->config['groupId'];
    }

    private function getMaxMessage(): int
    {
        return (is_int($this->maxMessage) && $this->maxMessage >= 1) ? $this->maxMessage : -1;
    }

    private function getMaxAttempt(): ?int
    {
        return (is_int($this->maxAttempt) && $this->maxAttempt >= 1) ? $this->maxAttempt : null;
    }

    private function getDlq(): ?string
    {
        return (is_string($this->dlq) && strlen($this->dlq) > 1) ? $this->dlq : null;
    }

    private function getSleepTime(): ?int
    {
        return (is_int($this->sleep) && $this->sleep >= 1) ? $this->sleep : null;
    }

    private function validateCommit(?int $commit): void
    {
        if (is_null($commit) || $commit < 1) {
            throw new InvalidCommitException();
        }
    }

    private function validateConsumer(?string $consumer): void
    {
        if (!class_exists($consumer) || !in_array(Consumer::class, class_implements($consumer))) {
            throw new InvalidConsumerException();
        }
    }
}

