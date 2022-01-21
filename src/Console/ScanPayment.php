<?php

namespace Vgplay\VxuTicket\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Vgplay\LaravelCachedSetting\Setting\Setting;
use Vgplay\VxuTicket\Contracts\TicketLog;
use Vgplay\VxuTicket\Contracts\TicketOwner;
use Vgplay\VxuTicket\Models\VxuLog;

class ScanPayment extends Command
{
    protected $signature = 'scan:payment {game} {--rate=100} {--has-spent=true} {--limit=20}';

    protected $count = 0;

    protected $bar;

    public function handle()
    {
        Setting::fromCache()->get([
            config('vgp_vxu_ticket.vxu_scan_start_time_setting_key', 'event_start_time'),
            config('vgp_vxu_ticket.vxu_scan_end_time_setting_key', 'event_end_time'),
        ]);

        $date_start = strtotime(setting('vxu_scan_start_time', ''));
        $date_end   = strtotime(setting('vxu_scan_end_time', ''));

        if (is_null($date_start) || is_null($date_end)) {
            $this->line('Vui lòng thiết lập thông tin trong phần cài đặt để bắt đầu quét');
            return -1;
        }

        $this->line('Ngày bắt đầu: ' . (Carbon::createFromTimestamp($date_start))->format('d/m/Y H:i:s'));
        $this->line('Ngày kết thúc: ' . (Carbon::createFromTimestamp($date_end))->format('d/m/Y H:i:s'));

        $this->scan($date_start, $date_end);

        return 0;
    }

    protected function scan($date_start, $date_end)
    {
        $chunk = (int) $this->option('limit');

        $this->line('Proccessing...');

        $this->bar = $this->output->createProgressBar((int) $this->option('limit'));

        $this->line('Scan from wallet id: ' . $this->getLastScanId());

        DB::transaction(function () use ($date_start, $date_end, $chunk) {
            $this->scanLogs($date_start, $date_end)
                ->chunkById($chunk, function ($logs) {
                    if ($this->count >= (int) $this->option('limit')) return false;
                    $this->process($logs);
                });
        });

        $this->newLine(2);
        $this->line('Scan to wallet id: ' . $this->getLastScanId());
        $this->line('Scan finished: ' . $this->count . '/' . (int) $this->option('limit'));
        $this->line('--------------');
    }

    protected function process($logs)
    {
        $scanLogs = [];

        foreach ($logs as $log) {
            if ($this->count >= (int) $this->option('limit')) {
                break;
            }

            $ticket = $this->exchangeVxuToTicket($log->wallet_log_amount);

            if ($ticket <= 0) {
                $this->bar->setProgress($this->count);
                continue;
            }

            $scanLogs[] = [
                'game_id' => $log->wallet_log_source,
                'vgp_id' => $log->wallet_log_uid,
                'vxu' => abs($log->wallet_log_amount),
                'ticket' => abs($ticket),
                'created_at' => $log->create_time,
                'note' => sprintf('Nạp Vxu mã %s', $log->wallet_log_txid),
            ];

            $this->increaseTicket($log->wallet_log_uid, $ticket);

            $this->count++;

            $this->bar->setProgress($this->count);
        }

        if (!empty($log)) $this->updateLastScanId($log->wallet_log_id);

        $this->saveScanLogs($scanLogs);
    }

    protected function scanLogs($from, $to)
    {
        return VxuLog::ofGame($this->argument('game'))
            ->inDateRange($from, $to)
            ->when($this->option('has-spent'), function ($q) {
                $q->hasSpent();
            })
            ->when(!$this->option('has-spent'), function ($q) {
                $q->justCharged();
            })
            ->where('wallet_log_id', '>', $this->getLastScanId())
            ->orderBy('wallet_log_id', 'ASC');
    }

    protected function getLastScanId()
    {
        $settingKey = sprintf('wallet_last_scan_id_%s', $this->argument('game'));

        if (!setting($settingKey)) {
            return Setting::firstOrCreate([
                'key' => $settingKey
            ], [
                'is_hidden' => true
            ])->value ?? 0;
        }

        return (int) setting($settingKey);
    }

    protected function updateLastScanId($newLastId)
    {
        return set_setting('wallet_last_scan_id_' . $this->argument('game'), $newLastId);
    }

    protected function increaseTicket($vgpId, $amount)
    {
        $playerModel = config('vgp_vxu_ticket.players.model', config('auth.providers.users.model'));

        /**
         * @var TicketOwner
         */
        $player = $playerModel::firstOrCreate(['id' => $vgpId], [
            'username' => 'AUTO_' . $vgpId,
        ]);

        return $player->incrementTicket($amount);
    }

    public function saveScanLogs(array $logs)
    {
        $ticketLogModel = config('vgp_vxu_ticket.ticket_logs.model');

        if (!in_array(TicketLog::class, class_implements($ticketLogModel))) {
            throw new \Exception(sprintf("%s model must implement %s Interface", $ticketLogModel, TicketLog::class));
        }

        return $ticketLogModel::saveVxuScanLogs($logs);
    }

    protected function exchangeVxuToTicket($vxu)
    {
        return floor(abs($vxu) / (int) $this->option('rate'));
    }
}
