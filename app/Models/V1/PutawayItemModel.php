<?php

namespace App\Models\V1\App;

use CodeIgniter\Model;

class PutawayItemModel extends Model
{
    protected $table            = 'putaway_item';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'putaway_date_id', 
        'products_id', 
        'receiving_qty', 
        'putaway_qty', 
        'parking_bays_id', 
        'created_at', 
        'created_by', 
        'active', 
        'updated_at',
        // column that is allowed to update
    ];
    public function getPutawayItem(array|string|null $conditions, $selectColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);
        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->get()->getRow();
    }
}
