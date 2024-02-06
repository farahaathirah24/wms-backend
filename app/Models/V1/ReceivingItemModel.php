<?php

namespace App\Models\V1\App;

use CodeIgniter\Model;

class ReceivingItemModel extends Model
{
    protected $table            = 'receiving_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['receiving_status', 'receiving_qty', 'receiving_staff', 'receiving_datetime', 'receiving_status', 'receiving_approval', 'active', 'updated_by', 'updated_at',
        // column that is allowed to update
    ];

    public function getReceivingItemInfo(array|string|null $conditions, $selectColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);
        $builder->join('receiving_lists', 'receiving_lists.id = ' . $this->table . '.receiving_lists_id', 'left');

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->get()->getRow();
    }
}
