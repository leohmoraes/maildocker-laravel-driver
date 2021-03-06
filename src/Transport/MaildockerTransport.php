<?php

namespace Guissilveira\MailDrivers\Maildocker\Transport;

use Swift_Image;
use Swift_MimePart;
use Swift_Attachment;
use Swift_Mime_Message;
use GuzzleHttp\ClientInterface;
use Illuminate\Mail\Transport\Transport;

class MaildockerTransport extends Transport
{
    const MAXIMUM_FILE_SIZE = 6291456;

    protected $client;
    protected $options;

    public function __construct(ClientInterface $client, $api_key, $api_secret)
    {
        $this->client = $client;
        $this->options = [
            'auth' => array($api_key,$api_secret,'basic')
        ];
    }

    /**
     * Send the given Message.
     *
     * Recipient/sender data will be retrieved from the Message API.
     * The return value is the number of recipients who were accepted for delivery.
     *
     * @param Swift_Mime_Message $message
     * @param string[]           $failedRecipients An array of failures by-reference
     *
     * @return int
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {
        list($from, $fromName) = $this->getFromAddresses($message);
        
        $payload = $this->options;
        $data = [
        
            'from'     => array('name' => isset($fromName) ? $fromName : null, 'email' => $from),
            'subject'  => $message->getSubject(),
            'html'     => $message->getBody()
        ];
        
        $this->setTo($data, $message);
        $this->setCc($data, $message);
        $this->setBcc($data, $message);
        $this->setText($data, $message);
        $this->setAttachment($data, $message);

        $payload += ['json' => $data];
        return $this->client->request('POST', 'https://ecentry.io:443/api/maildocker/v1/mail/', $payload);
    }
    /**
     * @param  $data
     * @param  Swift_Mime_Message $message
     */
    protected function setTo(&$data, Swift_Mime_Message $message)
    {
        if ($to = $message->getTo()) {
            foreach ($to as $email => $name) {
                $data['to'][] = array('email' => $email, 'name' => $name);
            }
        }
    }
    /**
     * @param $data
     * @param Swift_Mime_Message $message
     */
    protected function setCc(&$data, Swift_Mime_Message $message)
    {
        if ($cc = $message->getCc()) {
            foreach ($cc as $email => $name) {
                $data['cc'][] = array('email' => $email, 'name' => $name);
            }
        }
    }
    /**
     * @param $data
     * @param Swift_Mime_Message $message
     */
    protected function setBcc(&$data, Swift_Mime_Message $message)
    {
        if ($bcc = $message->getBcc()) {
            foreach ($bcc as $email => $name) {
                $data['bcc'][] = array('email' => $email, 'name' => $name);
            }
        }
    }
    /**
     * Get From Addresses.
     *
     * @param Swift_Mime_Message $message
     * @return array
     */
    protected function getFromAddresses(Swift_Mime_Message $message)
    {
        if ($message->getFrom()) {
            foreach ($message->getFrom() as $address => $name) {
                return [$address, $name];
            }
        }
        return [];
    }
    /**
     * Set text contents.
     *
     * @param $data
     * @param Swift_Mime_Message $message
     */
    protected function setText(&$data, Swift_Mime_Message $message)
    {
        foreach ($message->getChildren() as $attachment) {
            if (!$attachment instanceof Swift_MimePart) {
                continue;
            }
            $data['text'] = $attachment->getBody();
        }
    }
    /**
     * Set Attachment Files.
     *
     * @param $data
     * @param Swift_Mime_Message $message
     */
    protected function setAttachment(&$data, Swift_Mime_Message $message)
    {
        foreach ($message->getChildren() as $attachment) {
            if (!$attachment instanceof Swift_Attachment || !strlen($attachment->getBody()) > self::MAXIMUM_FILE_SIZE) {
                continue;
            }
            $handler = tmpfile();
            fwrite($handler, $attachment->getBody());
            $data['attachments[' . $attachment->getFilename() . ']'] = $handler;
        }
    }
}
