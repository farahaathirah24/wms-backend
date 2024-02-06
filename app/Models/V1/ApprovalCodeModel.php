<?php

namespace App\Models\V1;

use CodeIgniter\Model;

class ApprovalCodeModel extends Model
{
    protected $table            = 'approval_code';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['used_by', 'active', 'used_on',
        // column that is allowed to update
    ];

    public function getApprovalCode($encApprovalCode)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select([
            $this->primaryKey,
            'users_id',
        ]);
        $builder->where('code', $encApprovalCode);
        $builder->where('active', ACTIVE);

        return $builder->get()->getRow();
    }
}
