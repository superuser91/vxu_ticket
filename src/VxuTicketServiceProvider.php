<?php

namespace Vgplay\VxuTicket;

use Illuminate\Support\ServiceProvider;
use Vgplay\VxuTicket\Console\ScanPayment;

class VxuTicketServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/vgp_vxu_ticket.php', 'vgp_vxu_ticket');

        $this->commands([
            ScanPayment::class,
        ]);
    }
}
