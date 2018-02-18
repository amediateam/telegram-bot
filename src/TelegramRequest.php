<?php

namespace Telegram\Bot;

/**
 * Class TelegramRequest.
 *
 * Builds Telegram Bot API Request Entity.
 */
class TelegramRequest
{
    /**
     * @var string|null The bot access token to use for this request.
     */
    protected $accessToken;

    /**
     * @var string The API endpoint for this request.
     */
    protected $endpoint;

    /**
     * @var array The parameters to send with this request.
     */
    protected $params;

    /**
     * The file params.
     *
     * @var array
     */
    protected $files;

    /**
     * Indicates if the request to Telegram will be asynchronous (non-blocking).
     *
     * @var bool
     */
    protected $isAsyncRequest;

    /**
     * Timeout of the request in seconds.
     *
     * @var int
     */
    protected $timeOut;

    /**
     * Connection timeout of the request in seconds.
     *
     * @var int
     */
    protected $connectTimeOut;

    /**
     * Multipart attachments.
     *
     * @var array
     */
    protected $attachments = [];

    /**
     * Creates a new Request entity.
     *
     * @param string $accessToken
     * @param string $endpoint
     * @param array  $params
     * @param array  $files
     * @param bool   $isAsyncRequest
     * @param int    $timeOut
     * @param int    $connectTimeOut
     */
    public function __construct($accessToken, $endpoint, array $params, array $files, $isAsyncRequest, $timeOut, $connectTimeOut)
    {
        $this->accessToken = $accessToken;
        $this->endpoint = $endpoint;
        $this->params = $params;
        $this->files  = $files;
        $this->isAsyncRequest = $isAsyncRequest;
        $this->timeOut = $timeOut;
        $this->connectTimeOut = $connectTimeOut;
    }

    /**
     * Return the bot access token for this request.
     *
     * @return string|null
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Return the API Endpoint for this request.
     *
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * Return the params for this request.
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Return the file params.
     *
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Get the guzzle http options.
     *
     * @return array
     */
    public function getOptions()
    {
        if (! $this->files && ! $this->attachments) {
            return ['form_params' => $this->params];
        }

        $i = 0;
        $multipart = [];
        foreach ($this->params as $name => $contents) {
            if (is_null($contents)) {
                continue;
            }

            $multipart[$i]['name'] = $name;
            $multipart[$i]['contents'] = $contents;
            ++$i;
        }

        return ['multipart' => array_merge($multipart, $this->attachments)];
    }

    /**
     * Return the headers for this request.
     *
     * @return array
     */
    public function getHeaders()
    {
        return [
            'User-Agent' => 'Telegram Bot PHP SDK v'.Api::VERSION.' - (https://github.com/halaei/telegram-bot)',
        ];
    }

    /**
     * Check if this is an asynchronous request (non-blocking).
     *
     * @return bool
     */
    public function isAsyncRequest()
    {
        return $this->isAsyncRequest;
    }

    /**
     * @return int
     */
    public function getTimeOut()
    {
        return $this->timeOut;
    }

    /**
     * @return int
     */
    public function getConnectTimeOut()
    {
        return $this->connectTimeOut;
    }

    /**
     * Set the attachments.
     *
     * @param array $attachments
     *
     * @return $this
     */
    public function setAttachments(array $attachments)
    {
        $this->attachments = $attachments;

        return $this;
    }
}
