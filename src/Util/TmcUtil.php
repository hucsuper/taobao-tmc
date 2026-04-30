<?php

namespace Hucsuper\TaobaoTmc\Util;

use Illuminate\Support\Facades\File;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Throwable;

class TmcUtil
{
    /**
     * @return string
     */
    public static function getMillisecondTimestamp(): string
    {
        [$microseconds, $seconds] = explode(' ', microtime());
        return sprintf('%.0f', (floatval($microseconds) + floatval($seconds)) * 1000);
    }

    /**
     * @param $content
     * @param string $fileName
     * @return void
     */
    public static function log($content, string $fileName = 'tmc')
    {
        $fileName = str_replace('/', '_', $fileName);
        $path = storage_path('logs').'/'.$fileName;
        if (empty(File::extension($path))) {
            $path .= '.log';
        }
        if ($content instanceof Throwable) {
            $logContent = $content->__toString();
        } elseif (is_array($content) || is_object($content)) {
            $logContent = json_encode($content, JSON_UNESCAPED_UNICODE);
        } elseif (is_null($content) || is_bool($content)) {
            $logContent = var_export($content, true);
        } else {
            $logContent = $content;
        }

        $handles = [
            (new RotatingFileHandler($path, 0, Logger::INFO, true, 0755))
                //->pushProcessor(new IntrospectionProcessor($level, [], 1))
                ->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true)),
        ];
        $logger = new Logger('log', $handles);
        $logger->addRecord(Logger::INFO, $logContent);
    }
}