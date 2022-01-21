<?php

namespace Vgplay\VxuTicket\Traits;

trait HasTicketTrait
{
    public function getTicket()
    {
        return $this->ticket;
    }

    public function incrementTicket(int $amount): bool
    {
        return $this->increment('ticket', $amount);
    }
}
