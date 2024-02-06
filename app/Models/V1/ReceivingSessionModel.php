<?php

namespace App\Models\V1\App;

use CodeIgniter\Model;

class ReceivingSessionModel extends Model
{
    protected $table            = 'receiving_sessions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['user_sessions_id',
        'queue_id',
        'parking_bay',
        'barcode',
        'product_id',
        'qty',
        'status',
        'created_at',
        // column that is allowed to update
    ];

    public function checkReceivingSession(array|string|null $conditions, $selectColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->get()->getRow();
    }

    public function updateReceivingSession($qty, array|string|null $conditions)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->set('qty', 'qty + ' . $qty, false);

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->update();
    }

    public function resetReceivingSessionQty($conditions, $setColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->set($setColumn);

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->update();
        //   $a = $db->getLastQuery()->getQuery();
        // pre($a);die;
    }

    public function getReceivingSessionId(array|string|null $conditions, $selectColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->get()->getResult();
    }
}
