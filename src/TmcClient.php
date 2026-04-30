<?php

namespace Hucsuper\TaobaoTmc;

use Hucsuper\TaobaoTmc\Constants\MessageFields;
use Hucsuper\TaobaoTmc\Constants\MessageKind;
use Hucsuper\TaobaoTmc\Constants\MessageType;
use Hucsuper\TaobaoTmc\Exception\FailMessageException;
use Hucsuper\TaobaoTmc\Handler\MessageHandlerInterface;
use Hucsuper\TaobaoTmc\Message\Message;
use Hucsuper\TaobaoTmc\Message\Reader;
use Hucsuper\TaobaoTmc\Message\Writer;
use Hucsuper\TaobaoTmc\Util\TmcUtil;
use Throwable;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Timer;

class TmcClient
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var AsyncTcpConnection
     */
    protected $connection;

    /**
     * @var bool
     */
    protected $isStart = false;

    /**
     * @var int
     */
    protected $pullRequestTimerId;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $name;

    /**
     * @param $name
     */
    public function __construct($name)
    {
        $this->config = config('tmc.connections.'.$name);
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/7 18:16
     * @return $this
     */
    public function start()
    {
        if ($this->isStart) {
            return $this;
        }
        $this->createConnection()->connect();
        $this->isStart = true;
        return $this;
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/22 17:16
     * @return AsyncTcpConnection
     */
    protected function createConnection()
    {
        $tmcConfig = config('tmc');
        $address = $tmcConfig['uri'];
        // 以websocket协议连接远程websocket服务器
        $connection = new AsyncTcpConnection($address);
        //心跳检测
        $connection->websocketPingInterval = 55;
        // 设置数据类型(可选) BINARY_TYPE_BLOB为文本 BINARY_TYPE_ARRAYBUFFER为二进制
        $connection->websocketType = \Workerman\Protocols\Ws::BINARY_TYPE_ARRAYBUFFER;

        $connection->onConnect = [$this, 'onConnect'];
        $connection->onWebSocketConnect = [$this, 'onWebSocketConnect'];
        $connection->onMessage = [$this, 'onMessage'];
        $connection->onError = [$this, 'onError'];
        $connection->onClose = [$this, 'onClose'];
        $this->setConnection($connection);
        return $connection;
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/7 18:29
     * @param AsyncTcpConnection $connection
     * @return $this
     */
    public function setConnection(AsyncTcpConnection $connection)
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/22 17:10
     * @param array $params
     * @param string $secret
     * @param string $signMethod
     * @return string
     */
    protected function generateSign(array $params, string $secret, string $signMethod = 'MD5'): string
    {
        // 第一步：把字典按Key的字母顺序排序
        ksort($params);

        // 第二步：把所有参数名和参数值串在一起
        $query = '';
        if ($signMethod === 'MD5') {
            $query .= $secret;
        }
        foreach ($params as $key => $value) {
            if (!empty($key) && !empty($value)) {
                $query .= $key.$value;
            }
        }

        // 第三步：把请求主体拼接在参数后面
        if (!empty($body)) {
            $query .= $body;
        }

        // 第四步：使用MD5/HMAC加密
        if ($signMethod === 'HMAC') {
            $bytes = hash_hmac('md5', $query, $secret, true);
        } elseif ($signMethod === 'HMAC_SHA256') {
            $bytes = hash_hmac('sha256', $query, $secret, true);
        } else {
            $query .= $secret;
            $bytes = md5($query, true);
        }

        // 第五步：把二进制转化为大写的十六进制
        return strtoupper(bin2hex($bytes));
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/7 17:54
     * @param AsyncTcpConnection $connection
     * @return void
     */
    public function onConnect(AsyncTcpConnection $connection)
    {
        $this->println(__FUNCTION__);
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/22 17:26
     * @return void
     */
    protected function login()
    {
        $query = [
            'app_key' => $this->config['app_key'],
            'group_name' => 'default',
            'timestamp' => TmcUtil::getMillisecondTimestamp()
        ];
        $query['sign'] = $this->generateSign($query, $this->config['app_secret']);
        $query['sdk_version'] = 'SDK';
        $query['intranet_ip'] = '127.0.0.1';
        $msg = new Message();
        $msg->setMessageType(MessageType::CONNECT);
        $msg->setContent($query);
        $this->sendMessage($msg);
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/22 17:24
     * @param Message $message
     * @return bool|null
     */
    protected function sendMessage(Message $message)
    {
        return $this->connection->send(Writer::write($message));
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/7 17:54
     * @param AsyncTcpConnection $connection
     * @param $message
     * @return void
     */
    public function onWebSocketConnect(AsyncTcpConnection $connection, $message)
    {
        $this->println(__FUNCTION__);
        $this->println('握手成功，开始登录...');
        $this->login();
        $this->println('登录成功...');
        $this->pullRequest();
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/7 17:54
     * @param AsyncTcpConnection $connection
     * @param $message
     * @return void
     */
    public function onMessage(AsyncTcpConnection $connection, $message)
    {
        //注意处理时间耗时, 收到消息后若阻塞1分钟仍未处理完毕(当前onMessage函数未执行完毕), 将导致确认超时, 会导致重发消息, 请根据处理tps设置分组流控!
        $this->println(__FUNCTION__);
        $this->println('接收到的消息:'.$message, true);
        $resMsg = $this->parseMessage($message);
        $messageType = $resMsg->getMessageType();
        $this->println('解析后的消息:'.$resMsg->toString(), true);
        try {
            if ($messageType == MessageType::CONNECTACK) {//握手
                $this->connectAck($resMsg);
                return;
            }

            $handler = $this->getHandler();
            if ($handler) {
                $handler->handle($resMsg);
            }

            if ($messageType == MessageType::SEND) {//服务端推送数据
                $reqMsg = $this->createConfirmMessage(intval($resMsg->getContent()['id']));
                //发送确认消息
                $this->sendMessage($reqMsg);
            }
        } catch (Throwable $throwable) {
            // 若希望这条消息等会再重发一次过来 则标记消息消费失败  约6分钟后重新加入消费队列待消费
            $this->println($throwable->getMessage(), true);
            if ($throwable instanceof FailMessageException) {
                $reqMsg = $this->createFailMessage(intval($resMsg->getContent()['id']), $throwable->getMessage());
                $this->sendMessage($reqMsg);
            }
            // 重试注意：不是所有的异常都需要系统重试。
            // 对于字段不全、主键冲突问题，导致写DB异常，不可重试，否则消息会一直重发
            // 对于，由于网络问题，权限问题导致的失败，可重试。
            // 重试时间 6分钟不等，不要滥用，否则会引起雪崩
        }
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/22 18:17
     * @param Message $msg
     * @return void
     */
    protected function connectAck(Message $msg)
    {
        $this->println(sprintf('token:%s', $msg->getToken()));
        $this->token = $msg->getToken();
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/22 18:17
     * @param int $id
     * @return Message
     */
    protected function createConfirmMessage(int $id): Message
    {
        $msg = new Message();
        $msg->setMessageType(MessageType::SEND);
        $msg->setContent([
            MessageFields::KIND => MessageKind::CONFIRM,
            MessageFields::CONFIRM_ID => $id,
        ]);
        $msg->setToken($this->token);
        return $msg;
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/24 10:07
     * @param int $id
     * @param $errorMsg
     * @return Message
     */
    protected function createFailMessage(int $id, $errorMsg): Message
    {
        $msg = new Message();
        $msg->setMessageType(MessageType::SEND);
        $msg->setContent([
            MessageFields::KIND => MessageKind::FAILED,
            MessageFields::CONFIRM_ID => $id,
            MessageFields::CONFIRM_MSG => $errorMsg,
        ]);
        return $msg;
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/22 18:17
     * @return void
     */
    protected function pullRequest()
    {
        if ($this->pullRequestTimerId) {
            Timer::del($this->pullRequestTimerId);
        }
        $this->pullRequestTimerId = Timer::add(15, function () {
            try {
                $msg = new Message();
                $msg->setMessageType(MessageType::SEND);
                $msg->setContent([
                    MessageFields::KIND => MessageKind::PULL_REQUEST,
                ]);
                $msg->setToken($this->token);
                if (!$this->sendMessage($msg)) {
                    $this->println('pull_request send message fail');
                    $this->connection->close();
                    $this->connection->reconnect();
                }
            } catch (Throwable $throwable) {
                $this->println('pull_request error:'.$throwable->getMessage());
            }
        });
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/22 17:36
     * @param string $data
     * @return Message
     */
    protected function parseMessage(string $data): Message
    {
        return Reader::read($data);
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/7 17:54
     * @param AsyncTcpConnection $connection
     * @param $code
     * @param $message
     * @return void
     */
    public function onError(AsyncTcpConnection $connection, $code, $message)
    {
        $this->println(__FUNCTION__.':'.$message);
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/7 17:54
     * @param AsyncTcpConnection $connection
     * @return void
     */
    public function onClose(AsyncTcpConnection $connection)
    {
        $this->println(__FUNCTION__);
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/24 14:57
     * @return MessageHandlerInterface|null
     */
    protected function getHandler()
    {
        $handler = $this->config['handler'];
        if (!class_exists($handler)) {
            return null;
        }
        return new $handler();
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/22 17:34
     * @param $message
     * @param bool $fileLog
     * @return void
     */
    protected function println($message, bool $fileLog = false)
    {
        if ($this->config['debug']) {
            if ($message instanceof Throwable) {
                $logContent = $message->__toString();
            } elseif (is_array($message) || is_object($message)) {
                $logContent = json_encode($message, JSON_UNESCAPED_UNICODE);
            } elseif (is_null($message) || is_bool($message)) {
                $logContent = var_export($message, true);
            } else {
                $logContent = $message;
            }
            if ($fileLog) {
                TmcUtil::log($logContent, class_basename($this).'-'.$this->name);
            }
            echo '['.now()->toDateTimeString().'] '.$logContent.PHP_EOL;
        }
    }
}