<?php

namespace App\Mail\Transports;

use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailtrapApiTransport extends AbstractTransport
{
    public function __construct(
        protected HttpFactory $http,
        protected string $endpoint,
        protected string $token,
        protected array $from,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    protected function doSend(SentMessage $message): void
    {
        /** @var Email $email */
        $email = $message->getOriginalMessage();

        $payload = [
            'from' => [
                'email' => $this->from['address'],
                'name' => $this->from['name'],
            ],
            'to' => array_map(
                fn (Address $address) => [
                    'email' => $address->getAddress(),
                    'name' => $address->getName(),
                ],
                array_values($email->getTo()),
            ),
            'subject' => $email->getSubject(),
            'text' => $email->getTextBody() ?? '',
            'html' => $email->getHtmlBody() ?? '',
            'category' => 'Auth',
        ];

        $response = $this->http
            ->withToken($this->token)
            ->timeout(15)
            ->post($this->endpoint, $payload);

        if ($response->failed()) {
            throw new TransportException(
                'Mailtrap API error ('.$response->status().'): '.$response->body()
            );
        }
    }

    public function getName(): string
    {
        return 'mailtrap';
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}
