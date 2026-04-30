<?php

namespace Hucsuper\TaobaoTmc\Handler;

use Hucsuper\TaobaoTmc\Message\Message;

interface MessageHandlerInterface
{
    public function handle(Message $message): void;
}