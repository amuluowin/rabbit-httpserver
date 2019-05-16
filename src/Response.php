<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/9
 * Time: 16:53
 */

namespace rabbit\httpserver;


use Psr\Http\Message\ResponseInterface;
use rabbit\helper\FileHelper;
use rabbit\web\Cookie;
use rabbit\web\MessageTrait;
use rabbit\web\SwooleStream;

/**
 * Class Response
 * @package rabbit\httpserver
 */
class Response implements ResponseInterface
{
    use MessageTrait;
    /**
     * @var array
     */
    public static $phrases = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
    ];
    /** @var bool */
    private $_isSend = false;
    /**
     * swoole响应请求
     *
     * @var \Swoole\Http\Response
     */
    private $swooleResponse;

    /**
     * @var string
     */
    private $reasonPhrase = '';

    /**
     * @var int
     */
    private $statusCode = 200;

    /**
     * @var string
     */
    private $charset = 'utf-8';

    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var array
     */
    private $cookies = [];

    /**
     * Response constructor.
     * @param \Swoole\Http\Response $response
     */
    public function __construct(\Swoole\Http\Response $response)
    {
        $this->swooleResponse = $response;
    }

    /**
     * @param int $code
     * @param string $reasonPhrase
     * @return Response|static
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        $clone = &$this;
        $clone->statusCode = (int)$code;
        if (!$reasonPhrase && isset(self::$phrases[$code])) {
            $reasonPhrase = self::$phrases[$code];
        }
        $clone->reasonPhrase = $reasonPhrase;
        return $clone;
    }

    /**
     * @return string
     */
    public function getReasonPhrase()
    {
        return $this->reasonPhrase;
    }

    /**
     *
     */
    public function sendHeaders(): void
    {
        // Write Headers to swoole response
        foreach ($this->getHeaders() as $key => $value) {
            if (is_array($value)) {
                $this->swooleResponse->header($key, implode(';', $value));
            } else {
                $this->swooleResponse->header($key, $value);
            }
        }
    }

    /**
     *
     */
    public function sendCookies(): void
    {
        foreach ((array)$this->cookies as $domain => $paths) {
            foreach ($paths ?? [] as $path => $item) {
                foreach ($item ?? [] as $name => $cookie) {
                    if ($cookie instanceof Cookie) {
                        $this->swooleResponse->cookie($cookie->getName(), $cookie->getValue() ?: 1,
                            $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(),
                            $cookie->isHttpOnly());
                    }
                }
            }
        }
    }

    /**
     * 处理 Response 并发送数据
     */
    public function send(): void
    {
        /**
         * Headers
         */
        $this->sendHeaders();

        /**
         * Cookies
         */
        $this->sendCookies();

        /**
         * Status code
         */
        $this->swooleResponse->status($this->getStatusCode());

        /**
         * Body
         */
        $this->swooleResponse->end($this->getBody()->getContents());
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param $content
     * @return Response
     */
    public function withContent($content): Response
    {
        if ($this->stream) {
            return $this;
        }

        $clone = &$this;
        $clone->stream = new SwooleStream($content);
        return $clone;
    }

    /**
     * @param Cookie $cookie
     * @return Response
     */
    public function withCookie(Cookie $cookie): Response
    {
        $clone = &$this;
        $clone->cookies[$cookie->getDomain()][$cookie->getPath()][$cookie->getName()] = $cookie;
        return $clone;
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @param string $charset
     * @return Response
     */
    public function withCharset(string $charset): Response
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param $name
     * @param null $default
     * @return mixed|null
     */
    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * @param $name
     * @param $value
     * @return Response
     */
    public function withAttribute(string $name, $value): Response
    {
        $clone = &$this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    /**
     * @param string $filePath
     * @param string|null $attachmentName
     * @param array $options
     */
    public function sendFile(string $filePath, string $attachmentName = null, array $options = []): void
    {
        if ($this->_isSend) {
            return;
        }
        if (!isset($options['mimeType'])) {
            $options['mimeType'] = FileHelper::getMimeTypeByExtension($filePath);
        }
        if ($attachmentName === null) {
            $attachmentName = basename($filePath);
        }
        $this->swooleResponse->header('Content-disposition',
            'attachment; filename="' . urlencode($attachmentName) . '"');
        $this->swooleResponse->header('Content-Type', $options['mimeType']);
        $this->swooleResponse->header('Content-Transfer-Encoding', 'binary');
        $this->swooleResponse->header('Cache-Control', 'must-revalidate');
        $this->swooleResponse->header('Pragma', 'public');
        $this->swooleResponse->sendfile($filePath);
        $this->_isSend = true;
    }

    /**
     * @param string $chuck
     * @return bool
     */
    public function sendChuck(string $chuck): bool
    {
        if ($this->_isSend) {
            return false;
        }
        return $this->swooleResponse->write($chuck);
    }

    /**
     * @return \Swoole\Http\Response
     */
    public function getSwooleResponse(): \Swoole\Http\Response
    {
        return $this->swooleResponse;
    }

    /**
     * @param \Swoole\Http\Response $response
     * @return Response
     */
    public function setSwooleResponse(\Swoole\Http\Response $response): self
    {
        $this->swooleResponse = $response;
    }
}