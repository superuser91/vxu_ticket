<?php

namespace Vgplay\VxuTicket\Contracts;

interface TicketOwner
{
    public function getTicket();
    public function incrementTicket(int $amount): bool;
}
