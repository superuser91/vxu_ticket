<?php

/**
 * Created by Reliese Model.
 * Date: Thu, 20 Dec 2018 04:29:23 +0000.
 */

namespace Vgplay\VxuTicket\Models;

use Illuminate\Database\Eloquent\Model;

class VxuLog extends Model
{
    protected $connection = 'wallet';

    protected $table = 'wallet_log';

    protected $primaryKey = 'wallet_log_id';

    public $timestamps = false;

    protected $casts = [
        'wallet_log_amount'        => 'int',
        'wallet_log_status'        => 'int',
        'wallet_log_game_id'       => 'int',
        'wallet_log_total_cash_in' => 'int',
        'wallet_log_bonus'         => 'int',
        'create_time'              => 'int',
        'modify_time'              => 'int'
    ];

    protected $fillable = [
        'wallet_log_uid',
        'wallet_log_txid',
        'wallet_log_amount',
        'wallet_log_source',
        'wallet_log_partner_id',
        'wallet_log_status',
        'wallet_log_reason',
        'wallet_log_game_id',
        'wallet_log_total_cash_in',
        'wallet_log_bonus',
        'create_time',
        'modify_time'
    ];

    public function scopeInDateRange($query, $fromDate, $toDate)
    {
        $query->where('create_time', '>=', $fromDate)
            ->where('create_time', '<=', $toDate);
    }

    public function scopeOfGame($query, $gameId)
    {
        $query->where('wallet_log_source', $gameId);
    }

    public function scopeJustCharged($query)
    {
        $query->where('wallet_log_amount', '>', 0);
    }

    public function scopeHasSpent($query)
    {
        $query->where('wallet_log_amount', '<', 0);
    }
}
