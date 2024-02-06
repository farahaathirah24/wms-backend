<?php

namespace App\Models\V1;

use CodeIgniter\Model;

class LocationModel extends Model
{
    protected $table            = 'locations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        // column that is allowed to update
    ];

    public function getLocation($location)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($this->primaryKey);
        $builder->where('active', ACTIVE);
        $builder->where('locations_uuid', $location);

        return $builder->get()->getRow();
    }
}
