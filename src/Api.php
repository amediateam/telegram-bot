<?php

namespace Telegram\Bot;

use Closure;
use Illuminate\Support\Collection;
use Psr\Http\Message\StreamInterface;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\FileUpload\InputFileInterface;
use Telegram\Bot\HttpClients\GuzzleHttpClient;
use Telegram\Bot\Objects\Chat;
use Telegram\Bot\Objects\ChatMember;
use Telegram\Bot\Objects\File;
use Telegram\Bot\Objects\GameHighScore;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\UnknownObject;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Objects\User;
use Telegram\Bot\Objects\UserProfilePhotos;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\WebhookInfo;

/**
 * Class Api.
 */
class Api
{
    /**
     * @var string Version number of the Telegram Bot PHP SDK.
     */
    const VERSION = '3.0.0';

    /**
     * @var string The name of the environment variable that contains the Telegram Bot API Access Token.
     */
    const BOT_TOKEN_ENV_NAME = 'TELEGRAM_BOT_TOKEN';

    /**
     * @var TelegramClient The Telegram client service.
     */
    protected $client;

    /**
     * @var string Telegram Bot API Access Token.
     */
    protected $accessToken = null;

    /**
     * @var TelegramResponse|null Stores the last request made to Telegram Bot API.
     */
    protected $lastResponse;

    /**
     * @var bool Indicates if the request to Telegram will be asynchronous (non-blocking).
     */
    protected $isAsyncRequest = false;

    /**
     * The array of waiting async responses.
     *
     * @var TelegramResponse[]
     */
    protected $waitingResponses = [];

    /**
     * Timeout of the request in seconds.
     *
     * @var int
     */
    protected $timeOut = 60;

    /**
     * Connection timeout of the request in seconds.
     *
     * @var int
     */
    protected $connectTimeOut = 10;

    /**
     * The fulfillment handler to be called after each successful API call.
     *
     * @var null|Closure function(TelegramResponse $response, float $elapsedTime)
     */
    protected $onFulfilled;

    /**
     * The rejection handler to be called after each failed API call.
     *
     * @var null|Closure function(TelegramResponse $response, float $elapsedTime)
     */
    protected $onRejected;

    /**
     * Instantiates a new Telegram super-class object.
     *
     *
     * @param string              $token                      The Telegram Bot API Access Token.
     * @param bool                $async                      (Optional) Indicates if the request to Telegram
     *                                                        will be asynchronous (non-blocking).
     * @param GuzzleHttpClient $httpClientHandler          (Optional) Custom HTTP Client Handler.
     *
     * @throws TelegramSDKException
     */
    public function __construct($token = null, $async = false, $httpClientHandler = null)
    {
        $token = isset($token) ? $token : getenv(static::BOT_TOKEN_ENV_NAME);

        if (!$token) {
            throw new TelegramSDKException('Required "token" not supplied in config and could not find fallback environment variable "'.static::BOT_TOKEN_ENV_NAME.'"');
        }

        $this->setAccessToken($token);

        $this->client = new TelegramClient($httpClientHandler);

        if (isset($async)) {
            $this->setAsyncRequest($async);
        }
    }

    /**
     * Wait for the responses of async requests and empty the waitingResponses array.
     */
    public function __destruct()
    {
        $this->asyncWait();
    }


    /**
     * Returns the TelegramClient service.
     *
     * @return TelegramClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Returns Telegram Bot API Access Token.
     *
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Returns the last response returned from API request.
     *
     * @return TelegramResponse
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * Sets the bot access token to use with API requests.
     *
     * @param string $accessToken The bot access token to save.
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function setAccessToken($accessToken)
    {
        if (is_string($accessToken)) {
            $this->accessToken = $accessToken;

            return $this;
        }

        throw new \InvalidArgumentException('The Telegram bot access token must be of type "string"');
    }

    /**
     * Wait for the responses of async requests and empty the waitingResponses array.
     *
     * @return TelegramResponse[]
     */
    public function asyncWait()
    {
        $waiting = $this->waitingResponses;

        $this->waitingResponses = [];

        foreach ($waiting as $response) {
            try {
                $response->wait();
            } catch (\Exception $e) {
                //
            }
        }

        return $waiting;
    }

    /**
     * Make this request asynchronous (non-blocking).
     *
     * @param bool $isAsyncRequest
     *
     * @return $this
     */
    public function setAsyncRequest($isAsyncRequest)
    {
        $this->isAsyncRequest = $isAsyncRequest;

        if (! $this->isAsyncRequest()) {
            $this->asyncWait();
        }

        return $this;
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

    /*
     * Api Methods
     */

    /**
     * A simple method for testing your bot's auth token.
     * Returns basic information about the bot in form of a User object.
     *
     * @link https://core.telegram.org/bots/api#getme
     *
     * @return User|Closure
     */
    public function getMe()
    {
        $response = $this->post('getMe');

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new User($response->getDecodedBody());
        }, $response);
    }

    /*
     * Chat & Messaging Methods
     */

    /**
     * Send text messages.
     *
     * <code>
     * $params = [
     *   'chat_id'                  => '',
     *   'text'                     => '',
     *   'parse_mode'               => '',
     *   'disable_web_page_preview' => '',
     *   'disable_notification'     => '',
     *   'reply_to_message_id'      => '',
     *   'reply_markup'             => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#sendmessage
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var string     $params ['text']
     * @var string     $params ['parse_mode']
     * @var bool       $params ['disable_web_page_preview']
     * @var bool       $params ['disable_notification']
     * @var int        $params ['reply_to_message_id']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function sendMessage(array $params)
    {
        $response = $this->post('sendMessage', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new Message($response->getDecodedBody());
        }, $response);
    }

    /**
     * Forward messages of any kind.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'from_chat_id'         => '',
     *   'disable_notification' => '',
     *   'message_id'           => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#forwardmessage
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var int        $params ['from_chat_id']
     * @var bool       $params ['disable_notification']
     * @var int        $params ['message_id']
     *
     * @return Message|Closure
     */
    public function forwardMessage(array $params)
    {
        $response = $this->post('forwardMessage', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new Message($response->getDecodedBody());
        }, $response);
    }

    /**
     * Send Photos.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'photo'                => '',
     *   'caption'              => '',
     *   'disable_notification' => '',
     *   'reply_to_message_id'  => '',
     *   'reply_markup'         => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#sendphoto
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var string     $params ['photo']
     * @var string     $params ['caption']
     * @var bool       $params ['disable_notification']
     * @var int        $params ['reply_to_message_id']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function sendPhoto(array $params)
    {
        return $this->uploadFile('sendPhoto', $params, ['photo']);
    }

    /**
     * Send regular audio files.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'audio'                => '',
     *   'duration'             => '',
     *   'performer'            => '',
     *   'title'                => '',
     *   'disable_notification' => '',
     *   'reply_to_message_id'  => '',
     *   'reply_markup'         => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#sendaudio
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var string     $params ['audio']
     * @var int        $params ['duration']
     * @var string     $params ['performer']
     * @var string     $params ['title']
     * @var string     $params ['caption']
     * @var bool       $params ['disable_notification']
     * @var int        $params ['reply_to_message_id']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function sendAudio(array $params)
    {
        return $this->uploadFile('sendAudio', $params, ['audio']);
    }

    /**
     * Send general files.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'document'             => '',
     *   'caption'              => '',
     *   'disable_notification' => '',
     *   'reply_to_message_id'  => '',
     *   'reply_markup'         => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#senddocument
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var string     $params ['document']
     * @var string     $params ['caption']
     * @var bool       $params ['disable_notification']
     * @var int        $params ['reply_to_message_id']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function sendDocument(array $params)
    {
        return $this->uploadFile('sendDocument', $params, ['document']);
    }

    /**
     * Send .webp stickers.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'sticker'              => '',
     *   'disable_notification' => '',
     *   'reply_to_message_id'  => '',
     *   'reply_markup'         => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#sendsticker
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var string     $params ['sticker']
     * @var bool       $params ['disable_notification']
     * @var int        $params ['reply_to_message_id']
     * @var string     $params ['reply_markup']
     *
     * @throws TelegramSDKException
     *
     * @return Message|Closure
     */
    public function sendSticker(array $params)
    {
        if (is_file($params['sticker']) && (pathinfo($params['sticker'], PATHINFO_EXTENSION) !== 'webp')) {
            throw new TelegramSDKException('Invalid Sticker Provided. Supported Format: Webp');
        }

        return $this->uploadFile('sendSticker', $params, ['sticker']);
    }

    /**
     * Send Video File, Telegram clients support mp4 videos (other formats may be sent as Document).
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'video'                => '',
     *   'duration'             => '',
     *   'width'                => '',
     *   'height'               => '',
     *   'caption'              => '',
     *   'disable_notification' => '',
     *   'reply_to_message_id'  => '',
     *   'reply_markup'         => '',
     * ];
     * </code>
     *
     * @see  sendDocument
     * @link https://core.telegram.org/bots/api#sendvideo
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var string     $params ['video']
     * @var int        $params ['duration']
     * @var int        $params ['width']
     * @var int        $params ['height']
     * @var string     $params ['caption']
     * @var bool       $params ['disable_notification']
     * @var int        $params ['reply_to_message_id']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function sendVideo(array $params)
    {
        return $this->uploadFile('sendVideo', $params, ['video']);
    }

    /**
     * Send voice audio files.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'voice'                => '',
     *   'duration'             => '',
     *   'disable_notification' => '',
     *   'reply_to_message_id'  => '',
     *   'reply_markup'         => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#sendaudio
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var string     $params ['voice']
     * @var string     $params ['caption']
     * @var int        $params ['duration']
     * @var bool       $params ['disable_notification']
     * @var int        $params ['reply_to_message_id']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function sendVoice(array $params)
    {
        return $this->uploadFile('sendVoice', $params, ['voice']);
    }

    /**
     * Send video messages.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'video_note'           => '',
     *   'duration'             => '',
     *   'length'               => '',
     *   'disable_notification' => '',
     *   'reply_to_message_id'  => '',
     *   'reply_markup'         => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#sendvideonote
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var string     $params ['video_note']
     * @var int        $params ['duration']
     * @var int        $params ['length']
     * @var bool       $params ['disable_notification']
     * @var int        $params ['reply_to_message_id']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function sendVideoNote(array $params)
    {
        return $this->uploadFile('sendVideoNote', $params, ['video_note']);
    }

    /*
     * Game Methods.
     */

    /**
     * Send game.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'game_short_name'      => '',
     *   'disable_notification' => '',
     *   'reply_to_message_id'  => '',
     *   'reply_markup'         => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#sendgame
     *
     * @param array $params
     *
     * @return Message|Closure
     */
    public function sendGame(array $params)
    {
        $response = $this->post('sendGame', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new Message($response->getDecodedBody());
        }, $response);
    }

    /**
     * Use this method to set the score of the specified user in a game.
     * On success, if the message was sent by the bot, returns the edited Message, otherwise returns True.
     * Returns an error, if the new score is not greater than the user's current score in the chat.
     *
     * <code>
     * $params = [
     *   'user_id'              => '',
     *   'score'                => '',
     *   'force'                => '',
     *   'disable_edit_message' => '',
     *   'chat_id'              => '',
     *   'message_id'           => '',
     *   'inline_message_id'    => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#setgamescore
     *
     * @param array $params
     *
     * @return Message|true|Closure
     */
    public function setGameScore(array $params)
    {
        $response = $this->post('setGameScore', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            $body = $response->getDecodedBody();

            if ($body['result'] === true) {
                return true;
            }

            return new Message($body);
        }, $response);
    }

    /**
     * Use this method to get data for high score tables.
     * Will return the score of the specified user and several of his neighbors in a game.
     *
     * <code>
     * $params = [
     *   'user_id'           => '',
     *   'chat_id'           => '',
     *   'message_id'        => '',
     *   'inline_message_id' => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#getgamehighscores
     *
     * @param array $params
     *
     * @return GameHighScore[]|Closure
     */
    public function getGameHighScores(array $params)
    {
        $response = $this->post('getGameHighScores', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            $body = $response->getDecodedBody();

            $scores = [];

            foreach ($body['result'] as $score) {
                $scores[] = new GameHighScore($score);
            }

            return $scores;
        }, $response);
    }

    /*
     * Payment Methods
     */

    /**
     * Send invoice.
     *
     * Your bot can accept payments from Telegram users.
     * Please see the introduction to payments for more details on the process and how to set up payments for your bot.
     * Please note that users will need Telegram v.4.0 or higher to use payments (released on May 18, 2017).
     *
     * @link https://core.telegram.org/bots/api#sendinvoice
     *
     * @param array $params
     *
     * @return Message|Closure
     */
    public function sendInvoice(array $params)
    {
        $response = $this->post('sendInvoice', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new Message($response->getDecodedBody());
        }, $response);
    }

    /**
     * Reply to shipping queries.
     *
     * If you sent an invoice requesting a shipping address and the parameter is_flexible was specified,
     * the Bot API will send an Update with a shipping_query field to the bot.
     *
     * @link https://core.telegram.org/bots/api#answershippingquery
     *
     * @param array $params
     *
     * @return true|Closure
     */
    public function answerShippingQuery(array $params)
    {
        $response = $this->post('answerShippingQuery', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return $response->getRequest();
        }, $response);
    }

    /**
     * Respond to pre-checkout queries.
     *
     * Once the user has confirmed their payment and shipping details,
     * the Bot API sends the final confirmation in the form of an Update with the field pre_checkout_query.
     * Use this method to respond to such pre-checkout queries.
     * Note: The Bot API must receive an answer within 10 seconds after the pre-checkout query was sent.
     *
     * @link https://core.telegram.org/bots/api#answerprecheckoutquery
     *
     * @param array $params
     *
     * @return true|Closure
     */
    public function answerPreCheckoutQuery(array $params)
    {
        $response = $this->post('answerPreCheckoutQuery', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return $response->getRequest();
        }, $response);
    }

    /*
     * Chat & Messaging Methods
     */

    /**
     * Send point on the map.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'latitude'             => '',
     *   'longitude'            => '',
     *   'disable_notification' => '',
     *   'reply_to_message_id'  => '',
     *   'reply_markup'         => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#sendlocation
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var float      $params ['latitude']
     * @var float      $params ['longitude']
     * @var bool       $params ['disable_notification']
     * @var int        $params ['reply_to_message_id']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function sendLocation(array $params)
    {
        $response = $this->post('sendLocation', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new Message($response->getDecodedBody());
        }, $response);
    }

    /**
     * Send information about a venue.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'latitude'             => '',
     *   'longitude'            => '',
     *   'title'                => '',
     *   'address'              => '',
     *   'foursquare_id'        => '',
     *   'disable_notification' => '',
     *   'reply_to_message_id'  => '',
     *   'reply_markup'         => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#sendvenue
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var float      $params ['latitude']
     * @var float      $params ['longitude']
     * @var string     $params ['title']
     * @var string     $params ['address']
     * @var string     $params ['foursquare_id']
     * @var bool       $params ['disable_notification']
     * @var int        $params ['reply_to_message_id']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function sendVenue(array $params)
    {
        $response = $this->post('sendVenue', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new Message($response->getDecodedBody());
        }, $response);
    }

    /**
     * Send phone contacts.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'phone_number'         => '',
     *   'first_name'           => '',
     *   'last_name'            => '',
     *   'disable_notification' => '',
     *   'reply_to_message_id'  => '',
     *   'reply_markup'         => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#sendcontact
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var string     $params ['phone_number']
     * @var string     $params ['first_name']
     * @var string     $params ['last_name']
     * @var bool       $params ['disable_notification']
     * @var int        $params ['reply_to_message_id']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function sendContact(array $params)
    {
        $response = $this->post('sendContact', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new Message($response->getDecodedBody());
        }, $response);
    }

    /**
     * Broadcast a Chat Action.
     *
     * <code>
     * $params = [
     *   'chat_id' => '',
     *   'action'  => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#sendchataction
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var string     $params ['action']
     *
     * @throws TelegramSDKException
     *
     * @return true|Closure
     */
    public function sendChatAction(array $params)
    {
        $validActions = [
            'typing',
            'upload_photo',
            'record_video',
            'upload_video',
            'record_audio',
            'upload_audio',
            'upload_document',
            'find_location',
            'record_video_note',
            'upload_video_note',
        ];

        if (isset($params['action']) && in_array($params['action'], $validActions)) {
            $response = $this->post('sendChatAction', $params);

            return $this->prepareResponse(function (TelegramResponse $response) {
                return $response->getResult();
            }, $response);
        }

        throw new TelegramSDKException('Invalid Action! Accepted value: '.implode(', ', $validActions));
    }

    /*
     * Administration Methods
     */

    /**
     * Returns a list of profile pictures for a user.
     *
     * <code>
     * $params = [
     *   'user_id' => '',
     *   'offset'  => '',
     *   'limit'   => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#getuserprofilephotos
     *
     * @param array $params
     *
     * @var int     $params ['user_id']
     * @var int     $params ['offset']
     * @var int     $params ['limit']
     *
     * @return UserProfilePhotos|Closure
     */
    public function getUserProfilePhotos(array $params)
    {
        $response = $this->post('getUserProfilePhotos', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new UserProfilePhotos($response->getDecodedBody());
        }, $response);
    }

    /**
     * Returns basic info about a file and prepare it for downloading.
     *
     * <code>
     * $params = [
     *   'file_id' => '',
     * ];
     * </code>
     *
     * The file can then be downloaded via the link
     * https://api.telegram.org/file/bot<token>/<file_path>,
     * where <file_path> is taken from the response.
     *
     * @link https://core.telegram.org/bots/api#getFile
     *
     * @param array $params
     *
     * @var string  $params ['file_id']
     *
     * @return File|Closure
     */
    public function getFile(array $params)
    {
        $response = $this->post('getFile', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new File($response->getDecodedBody());
        }, $response);
    }

    /**
     * Kick a user from a group or a supergroup.
     *
     * In the case of supergroups, the user will not be able to return to the group on their own using
     * invite links etc., unless unbanned first.
     *
     * The bot must be an administrator in the group for this to work.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'user_id'              => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#kickchatmember
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var int        $params ['user_id']
     *
     * @return true|Closure
     */
    public function kickChatMember(array $params)
    {
        $response = $this->post('kickChatMember', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return $response->getResult();
        }, $response);
    }

	/**
	 *  Use this method for your bot to leave a group, supergroup or channel.
	 *
	 * <code>
	 * $params = [
	 *   'chat_id'              => '',
	 * ];
	 * </code>
	 *
	 * @link  https://core.telegram.org/bots/api/#leavechat
	 *
	 * @param array    $params
	 *
	 * @var int|string $params ['chat_id']
	 *
	 * @return true|Closure
	 */
    public function leaveChat(array $params)
    {
        $response = $this->post('leaveChat', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return $response->getResult();
        }, $response);
    }

    /**
     * Unban a previously kicked user in a supergroup or channel.
     *
     * The user will not return to the group or channel automatically, but will be able to join via link, etc.
     *
     * The bot must be an administrator in the group for this to work.
     *
     * <code>
     * $params = [
     *   'chat_id'              => '',
     *   'user_id'              => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#unbanchatmember
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var int        $params ['user_id']
     *
     * @return true|Closure
     */
    public function unbanChatMember(array $params)
    {
        $response = $this->post('unbanChatMember', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return $response->getResult();
        }, $response);
    }

    /*
     * Callback Query Methods
     */

    /**
     * Send answers to callback queries sent from inline keyboards.
     *
     * he answer will be displayed to the user as a notification at the top of the chat screen or as an alert.
     *
     * <code>
     * $params = [
     *   'callback_query_id'  => '',
     *   'text'               => '',
     *   'show_alert'         => '',
     *   'url'                => '',
     *   'cache_time'         => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#answerCallbackQuery
     *
     * @param array $params
     *
     * @var string  $params ['callback_query_id']
     * @var string  $params ['text']
     * @var bool    $params ['show_alert']
     *
     * @return true|Closure
     */
    public function answerCallbackQuery(array $params)
    {
        $response = $this->post('answerCallbackQuery', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return $response->getResult();
        }, $response);
    }

    /*
     * Edit Methods
     */

    /**
     * Edit text messages sent by the bot or via the bot (for inline bots).
     *
     * <code>
     * $params = [
     *   'chat_id'                  => '',
     *   'message_id'               => '',
     *   'inline_message_id'        => '',
     *   'text'                     => '',
     *   'parse_mode'               => '',
     *   'disable_web_page_preview' => '',
     *   'reply_markup'             => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#editMessageText
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var int        $params ['message_id']
     * @var string     $params ['inline_message_id']
     * @var string     $params ['text']
     * @var string     $params ['parse_mode']
     * @var bool       $params ['disable_web_page_preview']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function editMessageText(array $params)
    {
        $response = $this->post('editMessageText', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new Message($response->getDecodedBody());
        }, $response);
    }

    /**
     * Edit captions of messages sent by the bot or via the bot (for inline bots).
     *
     * <code>
     * $params = [
     *   'chat_id'                  => '',
     *   'message_id'               => '',
     *   'inline_message_id'        => '',
     *   'caption'                  => '',
     *   'reply_markup'             => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#editMessageCaption
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var int        $params ['message_id']
     * @var string     $params ['inline_message_id']
     * @var string     $params ['caption']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function editMessageCaption(array $params)
    {
        $response = $this->post('editMessageCaption', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new Message($response->getDecodedBody());
        }, $response);
    }

    /**
     * Edit only the reply markup of messages sent by the bot or via the bot (for inline bots).
     *
     * <code>
     * $params = [
     *   'chat_id'                  => '',
     *   'message_id'               => '',
     *   'inline_message_id'        => '',
     *   'reply_markup'             => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#editMessageReplyMarkup
     *
     * @param array    $params
     *
     * @var int|string $params ['chat_id']
     * @var int        $params ['message_id']
     * @var string     $params ['inline_message_id']
     * @var string     $params ['reply_markup']
     *
     * @return Message|Closure
     */
    public function editMessageReplyMarkup(array $params)
    {
        $response = $this->post('editMessageReplyMarkup', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new Message($response->getDecodedBody());
        }, $response);
    }

    /**
     * Delete a message.
     *
     * A message can only be deleted if it was sent less than 48 hours ago.
     * Any such recently sent outgoing message may be deleted.
     * Additionally, if the bot is an administrator in a group chat, it can delete any message.
     * If the bot is an administrator in a supergroup, it can delete messages from any other user and service messages
     * about people joining or leaving the group (other types of service messages may only be removed by the group creator).
     * In channels, bots can only remove their own messages. Returns True on success.
     *
     * <code>
     * $params = [
     *    'chat_id' => '',
     *    'message_id' => '',
     * ];
     * </code>
     * @link https://core.telegram.org/bots/api#editMessageReplyMarkup
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var int        $params ['message_id']
     *
     * @return true|Closure
     */
    public function deleteMessage(array $params)
    {
        $response = $this->post('deleteMessage', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return $response->getResult();
        }, $response);
    }

    /*
     * Inline Mode Methods
     */

    /**
     * Use this method to send answers to an inline query.
     *
     * <code>
     * $params = [
     *   'inline_query_id'      => '',
     *   'results'              => [],
     *   'cache_time'           => 0,
     *   'is_personal'          => false,
     *   'next_offset'          => '',
     *   'switch_pm_text'       => '',
     *   'switch_pm_parameter'  => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#answerinlinequery
     *
     * @param array     $params
     *
     * @var string      $params ['inline_query_id']
     * @var array       $params ['results']
     * @var int|null    $params ['cache_time']
     * @var bool|null   $params ['is_personal']
     * @var string|null $params ['next_offset']
     * @var string|null $params ['switch_pm_text']
     * @var string|null $params ['switch_pm_parameter']
     *
     * @return true|Closure
     */
    public function answerInlineQuery(array $params = [])
    {
        if (is_array($params['results'])) {
            $params['results'] = json_encode($params['results']);
        }

        $response = $this->post('answerInlineQuery', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return $response->getResult();
        }, $response);
    }

    /*
     * Administration Methods
     */

    /**
     * @param array $params
     *
     * @return Chat|Closure
     */
    public function getChat(array $params)
    {
        $response = $this->post('getChat', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new Chat($response->getDecodedBody());
        }, $response);
    }

    /**
     * @param array $params
     *
     * @return ChatMember[]|Closure
     */
    public function getChatAdministrators(array $params)
    {
        $response = $this->post('getChatAdministrators', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            $members = [];

            foreach ($response->getResult() as $member) {
                $members[] = new ChatMember($member);
            }

            return $members;
        }, $response);
    }

    /**
     * @param array $params
     *
     * @return int|Closure
     */
    public function getChatMembersCount(array $params)
    {
        $response = $this->post('getChatMembersCount', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return $response->getResult();
        }, $response);
    }

    /**
     * @param array $params
     *
     * @return ChatMember|Closure
     */
    public function getChatMember(array $params)
    {
        $response = $this->post('getChatMember', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new ChatMember($response->getDecodedBody());
        }, $response);
    }

    /*
     * Update & Webhook Methods
     */
    /**
     * Use this method to get current webhook status. Requires no parameters.
     * On success, returns a WebhookInfo object. If the bot is using getUpdates, will return an object with the url field empty.
     *
     * @return WebhookInfo|Closure
     */
    public function getWebhookInfo()
    {
        $response = $this->post('getWebhookInfo');

        return $this->prepareResponse(function (TelegramResponse $response) {
            return new WebhookInfo($response->getDecodedBody());
        }, $response);
    }

    /**
     * Set a Webhook to receive incoming updates via an outgoing webhook.
     *
     * <code>
     * $params = [
     *   'url'             => '',
     *   'certificate'     => '',
     *   'max_connections' => '',
     *   'allowed_updates' => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#setwebhook
     *
     * @param array $params
     *
     * @var string  $params ['url']                HTTPS url to send updates to.
     * @var string  $params ['certificate']        Upload your public key certificate so that the root certificate in
     *                                             use can be checked.
     * @var string  $params ['max_connections']    Maximum allowed number of simultaneous HTTPS connections to the webhook for update delivery, 1-100.
     *                                             Defaults to 40.
     *                                             Use lower values to limit the load on your bot‘s server, and higher values to increase your bot’s throughput.
     * @var string[]  $params ['allowed_updates']  List the types of updates you want your bot to receive.
     *                                             For example, specify [“message”, “edited_channel_post”, “callback_query”] to only receive updates of these types.
     *                                             See Update for a complete list of available update types.
     *                                             Specify an empty list to receive all updates regardless of type (default).
     *                                             If not specified, the previous setting will be used.
     *
     * @throws TelegramSDKException
     *
     * @return true on success
     */
    public function setWebhook(array $params)
    {
        if (filter_var($params['url'], FILTER_VALIDATE_URL) === false) {
            throw new TelegramSDKException('Invalid URL Provided');
        }

        if (parse_url($params['url'], PHP_URL_SCHEME) !== 'https') {
            throw new TelegramSDKException('Invalid URL, should be a HTTPS url.');
        }

        return $this->uploadFile('setWebhook', $params, ['certificate']);
    }

    /**
     * Returns the webhook update sent by Telegram.
     * Works only if you set a webhook.
     *
     * @see setWebhook
     *
     * @return Update
     */
    public function getWebhookUpdate()
    {
        $body = json_decode(file_get_contents('php://input'), true);

        $update = new Update($body);

        return $update;
    }

    /**
     * @deprecated
     */
    public function getWebhookUpdates()
    {
        return $this->getWebhookUpdate();
    }

    /**
     * Removes the outgoing webhook (if any).
     *
     * @deprecated
     *
     * @return true on success.
     */
    public function removeWebhook()
    {
        return $this->deleteWebhook();
    }

    /**
     * Use this method to remove webhook integration if you decide to switch back to getUpdates.
     *
     * @return true|Closure
     */
    public function deleteWebhook()
    {
        $response = $this->post('deleteWebhook');

        return $this->prepareResponse(function (TelegramResponse $response) {
            return $response->getResult();
        }, $response);
    }

    /**
     * Use this method to receive incoming updates using long polling.
     *
     * <code>
     * $params = [
     *   'offset'  => '',
     *   'limit'   => '',
     *   'timeout' => '',
     * ];
     * </code>
     *
     * @link https://core.telegram.org/bots/api#getupdates
     *
     * @param array  $params
     * @var int|null $params ['offset']
     * @var int|null $params ['limit']
     * @var int|null $params ['timeout']
     *
     * @return Update[]|Collection|Closure
     */
    public function getUpdates(array $params = [])
    {
        $response = $this->post('getUpdates', $params);

        return $this->prepareResponse(function (TelegramResponse $response) {
            $updates = $response->getDecodedBody();

            $data = [];
            if (isset($updates['result'])) {
                foreach ($updates['result'] as $body) {
                    $update = new Update($body);

                    $data[] = $update;
                }
            }

            return new Collection($data);
        }, $response);
    }

    /**
     * Builds a custom keyboard markup.
     *
     * <code>
     * $params = [
     *   'keyboard'          => '',
     *   'resize_keyboard'   => '',
     *   'one_time_keyboard' => '',
     *   'selective'         => '',
     * ];
     * </code>
     *
     * @deprecated Use Telegram\Bot\Keyboard\Keyboard::make(array $params = []) instead.
     *             To be removed in next major version.
     *
     * @link       https://core.telegram.org/bots/api#replykeyboardmarkup
     *
     * @param array $params
     *
     * @var array   $params ['keyboard']
     * @var bool    $params ['resize_keyboard']
     * @var bool    $params ['one_time_keyboard']
     * @var bool    $params ['selective']
     *
     * @return string
     */
    public function replyKeyboardMarkup(array $params)
    {
        return Keyboard::make($params);
    }

    /**
     * Hide the current custom keyboard and display the default letter-keyboard.
     *
     * <code>
     * $params = [
     *   'remove_keyboard' => true,
     *   'selective'       => false,
     * ];
     * </code>
     *
     * @deprecated Use Telegram\Bot\Keyboard\Keyboard::hide(array $params = []) instead.
     *             To be removed in next major version.
     *
     * @link       https://core.telegram.org/bots/api#replykeyboardhide
     *
     * @param array $params
     *
     * @var bool    $params ['remove_keyboard']
     * @var bool    $params ['selective']
     *
     * @return string
     */
    public static function replyKeyboardHide(array $params = [])
    {
        return Keyboard::hide($params);
    }

    /**
     * Display a reply interface to the user (act as if the user has selected the bot‘s message and tapped ’Reply').
     *
     * <code>
     * $params = [
     *   'force_reply' => true,
     *   'selective'   => false,
     * ];
     * </code>
     *
     * @deprecated Use Telegram\Bot\Keyboard\Keyboard::forceReply(array $params = []) instead.
     *             To be removed in next major version.
     *
     * @link       https://core.telegram.org/bots/api#forcereply
     *
     * @param array $params
     *
     * @var bool    $params ['force_reply']
     * @var bool    $params ['selective']
     *
     * @return string
     */
    public static function forceReply(array $params = [])
    {
        return Keyboard::forceReply($params);
    }

    /**
     * Sends a POST request to Telegram Bot API and returns the result.
     *
     * @param string $endpoint
     * @param array  $params
     * @param bool   $fileUpload Set true if a file is being uploaded.
     *
     * @return TelegramResponse
     */
    protected function post($endpoint, array $params = [], $fileUpload = false)
    {
        if ($fileUpload) {
            $params = ['multipart' => $params];
        } else {

            if (array_key_exists('reply_markup', $params)) {
                $params['reply_markup'] = (string)$params['reply_markup'];
            }

            $params = ['form_params' => $params];
        }

        return $this->sendRequest($endpoint,$params);
    }

    /**
     * Sends a multipart/form-data request to Telegram Bot API and returns the result.
     * Used primarily for file uploads.
     *
     * @param string $endpoint
     * @param array  $params
     * @param array  $files
     *
     * @throws TelegramSDKException
     *
     * @return Message|true|Closure
     */
    protected function uploadFile($endpoint, array $params, array $files)
    {
        foreach ($files as $key) {
            if (array_key_exists($key, $params) && !is_resource($params[$key]) && ! $params[$key] instanceof StreamInterface) {
                if ($params[$key] instanceof InputFileInterface) {
                    $params[$key] = $params[$key]->open();
                } else {
                    $validUrl = filter_var($params[$key], FILTER_VALIDATE_URL);
                    $params[$key] = (is_file($params[$key]) || $validUrl) ? (new InputFile($params[$key]))->open() : (string) $params[$key];
                }
            }
        }

        $i = 0;
        $multipart_params = [];

        foreach ($params as $name => $contents) {
            if (is_null($contents)) {
                continue;
            }

            $multipart_params[$i]['name'] = $name;
            $multipart_params[$i]['contents'] = $contents;
            ++$i;
        }

        $response = $this->post($endpoint, $multipart_params, true);


        return $this->prepareResponse(function (TelegramResponse $response) use ($endpoint) {
            if ($endpoint == 'setWebhook') {
                return $response->getResult();
            }

            return new Message($response->getDecodedBody());
        }, $response);
    }

    /**
     * Sends a request to Telegram Bot API and returns the result.
     *
     * @param string $endpoint
     * @param array  $params
     *
     * @return TelegramResponse
     */
    protected function sendRequest($endpoint, array $params = [])
    {
        $request = $this->request($endpoint, $params);

        $time = microtime(true);

        $promise = $this->client->sendRequest($request);

        $response = new TelegramResponse($request, $promise);

        $handler = function () use ($response, $time) {
            $elapsedTime = microtime(true) - $time;

            if ($response->isError()) {
                $this->rejected($response, $elapsedTime);
            } else {
                $this->fulfilled($response, $elapsedTime);
            }
        };

        $promise->then($handler, $handler);

        return $response;
    }

    /**
     * Instantiates a new TelegramRequest entity.
     *
     * @param string $endpoint
     * @param array  $params
     *
     * @return TelegramRequest
     */
    protected function request($endpoint, array $params = [])
    {
        return new TelegramRequest(
            $this->getAccessToken(),
            $endpoint,
            $params,
            $this->isAsyncRequest(),
            $this->getTimeOut(),
            $this->getConnectTimeOut()
        );
    }

    /**
     * Send the Closure $parser if Api is in async mode, otherwise send the return value of the Closure $parser.
     *
     * @param Closure $parser
     * @param TelegramResponse $response
     *
     * @throws TelegramSDKException
     *
     * @return Closure|mixed
     */
    protected function prepareResponse(Closure $parser, TelegramResponse $response)
    {
        $prepared = function () use ($parser, $response) {
            $response->wait()->throwException();
            return $parser($response);
        };

        if ($this->isAsyncRequest()) {
            $this->waitingResponses[] = $response;

            return $prepared;
        }

        return $prepared();
    }

    /**
     * Magic method to process any "get" requests.
     *
     * @param $method
     * @param $arguments
     *
     * @return bool|TelegramResponse|UnknownObject
     */
    public function __call($method, $arguments)
    {
        $action = substr($method, 0, 3);
        if ($action === 'get') {
            /* @noinspection PhpUndefinedFunctionInspection */
            $class_name = studly_case(substr($method, 3));
            $class = 'Telegram\Bot\Objects\\'.$class_name;
            $response = $this->post($method, $arguments[0] ?: []);

            if (class_exists($class)) {
                return new $class($response->getDecodedBody());
            }

            return $response;
        }
        $response = $this->post($method, $arguments[0]);

        return new UnknownObject($response->getDecodedBody());
    }

    /**
     * @return int
     */
    public function getTimeOut()
    {
        return $this->timeOut;
    }

    /**
     * @param int $timeOut
     *
     * @return $this
     */
    public function setTimeOut($timeOut)
    {
        $this->timeOut = $timeOut;

        return $this;
    }

    /**
     * @return int
     */
    public function getConnectTimeOut()
    {
        return $this->connectTimeOut;
    }

    /**
     * @param int $connectTimeOut
     *
     * @return $this
     */
    public function setConnectTimeOut($connectTimeOut)
    {
        $this->connectTimeOut = $connectTimeOut;

        return $this;
    }

    /**
     * Clear or set a fulfillment handler on the subsequent API requests.
     *
     * <code>
     * $api->onFulfilled(function (TelegramResponse $response, $elapsedTime) {
     *     //Profile the api delay
     *     //...
     * })
     * </code>
     *
     * @param null|Closure $onFulfilled function(TelegramResponse $response, float $elapsedTime)
     *
     * @return $this
     */
    public function onFulfilled(Closure $onFulfilled = null)
    {
        $this->onFulfilled = $onFulfilled;

        return $this;
    }

    /**
     * Clear or set a rejection handler on the subsequent API requests.
     *
     * <code>
     * $api->onRejected(function (TelegramResponse $response, $elapsedTime) {
     *     //Profile the api delay
     *     //Log the API exception
     *     //...
     * })
     * </code>
     *
     * @param null|Closure $onRejected function(TelegramResponse $response, float $elapsedTime)
     *
     * @return $this
     */
    public function onRejected(Closure $onRejected = null)
    {
        $this->onRejected = $onRejected;

        return $this;
    }

    /**
     * @param TelegramResponse $request
     * @param float            $elapsedTime
     */
    protected function fulfilled(TelegramResponse $request, $elapsedTime)
    {
        if (is_callable($this->onFulfilled)) {
            call_user_func_array($this->onFulfilled, [$request, $elapsedTime]);
        }
    }

    /**
     * @param TelegramResponse $request
     * @param float            $elapsedTime
     */
    protected function rejected(TelegramResponse $request, $elapsedTime)
    {
        if (is_callable($this->onRejected)) {
            call_user_func_array($this->onRejected, [$request, $elapsedTime]);
        }
    }
}
