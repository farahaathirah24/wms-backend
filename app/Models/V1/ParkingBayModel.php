<?php

namespace App\Models\V1;

use CodeIgniter\Model;

class ParkingBayModel extends Model
{
    protected $table            = 'parking_bays';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        // column that is allowed to update
    ];

    public function checkParkingBay($parkingBay, $moduleId)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($this->primaryKey);
        $builder->where('name', $parkingBay);
        $builder->where('type', $moduleId);
        $builder->where('active', ACTIVE);

        return $builder->get()->getRow();
    }

    public function getParkingBaysUuid($parkingBay, $moduleId)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select('parking_bays_uuid');
        $builder->where('name', $parkingBay);
        $builder->where('type', $moduleId);
        $builder->where('active', ACTIVE);

        return $builder->get()->getRow();
    }

    public function getParkingBaysidByUuid($parkingBay, $moduleId)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($this->primaryKey);
        $builder->where('parking_bays_uuid', $parkingBay);
        $builder->where('type', $moduleId);
        $builder->where('active', ACTIVE);

        return $builder->get()->getRow();
    }
}
