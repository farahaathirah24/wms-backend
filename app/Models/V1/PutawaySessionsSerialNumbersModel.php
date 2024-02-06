<?php

namespace App\Models\V1\App;

use CodeIgniter\Model;

class PutawaySessionsSerialNumbersModel extends Model
{
    protected $table            = 'putaway_sessions_serial_numbers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'putaway_sessions_id',
        'serial_numbers_id',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'active',
          // column that is allowed to update
    ];

}
