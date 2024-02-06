<?php

namespace App\Models\V1;

use CodeIgniter\Model;

class SerialNumbersStatusModel extends Model
{
    protected $table            = 'serial_numbers_status';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'serial_numbers_id',
        'receiving_items_id',
        'putaway_items_id',
        'rack_item_id',
        'receiving_sessions_id',
        // column that is allowed to update
    ];
}
