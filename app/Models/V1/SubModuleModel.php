<?php

namespace App\Models\V1;

use CodeIgniter\Model;

class SubModuleModel extends Model
{
    protected $table            = 'sub_modules';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'modules_id',
        'sub_modules_uuid',
        'name',
        'subtitle',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'active',
    ];

    public function getSubmodules(int $type, string|array|object $selectCol = '*')
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectCol);

        $builder->join('modules', 'modules.id = sub_modules.modules_id');
        $builder->where('modules.type', $type);
        $builder->where('sub_modules.active', 1);

        return $builder->get()->getResult();
    }

    public function getSubModuleDetail(string $moduleUuid, string|array|object $selectCol = '*')
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectCol);

        $builder->join('modules', 'modules.id = sub_modules.modules_id');
        $builder->where('sub_modules.sub_modules_uuid', $moduleUuid);
        $builder->where('sub_modules.active', 1);

        return $builder->get()->getRow();
    }
}
