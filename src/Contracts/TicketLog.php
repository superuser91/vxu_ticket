<?php

namespace Vgplay\VxuTicket\Contracts;


interface TicketLog
{
    public static function saveVxuScanLogs(array $logs);
}
