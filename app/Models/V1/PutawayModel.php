<?php

namespace App\Models\V1\App;

use CodeIgniter\Model;

class PutawayModel extends Model
{
    protected $table            = 'putaway_date';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id', 'putaway_uuid', 'date', 'total_received', 'total_putaway', 'created_at', 'created_by', 'active', 'updated_at'];

    public function getPutawayInfo(array|string|null $conditions, $selectColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->get()->getRow();
    }

    public function updatePutawayitem($total_received, array|string|null $conditions)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->set('total_received	', 'total_received	 + ' . $total_received, false);

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->update();
    }
}
