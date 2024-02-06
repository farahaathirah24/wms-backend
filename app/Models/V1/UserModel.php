<?php

namespace App\Models\V1;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'email',
        'active',
    ];

    public function getActiveUser(array|string|null $conditions, array|string $selectColumn = '*')
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);
        $builder->where('users.active', '1');

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->get()->getRow();
        // $a = $db->getLastQuery()->getQuery();
        // pre($a);die;
    }

    public function getUserCompany($userId)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select([
            'companys.company_uuid',
            'companys.name',
            'companys.img',
        ]);

        $builder->join('companys', 'companys.id = users.companys_id');
        $builder->where('users.id', $userId);
        $builder->where('companys.active', 1);

        return $builder->get()->getResult();
    }

    public function getUserLocation($userId)
    {
        $db      = db_connect();
        $builder = $db->table('users_locations');
        $builder->join('locations', 'locations.id = users_locations.locations_id');

        $builder->where('users_locations.users_id', $userId);
        $builder->where('users_locations.active', ACTIVE);

        return $builder->get()->getResult();
    }

    public function getUserModulePermision($userId)
    {
        $db      = db_connect();
        $builder = $db->table('users_modules');

        $builder->select([
            'locations.code  as locations_code',
            'locations.locations_uuid as locations_uuid',

            'modules.modules_uuid',
            'modules.name as modules_name',

            'sub_modules.sub_modules_uuid',
            'sub_modules.name as  sub_modules_name',
            'sub_modules.subtitle as sub_modules_subtitle',
            'users_modules.permisions as modules_permisions',
        ]);
        $builder->join('sub_modules', 'sub_modules.id = users_modules.sub_modules_id');
        $builder->join('modules', 'modules.id = sub_modules.modules_id');
        $builder->join('locations', 'locations.id = users_modules.locations_id', 'left');
        $builder->where('users_modules.users_id', $userId);
        $builder->where('users_modules.active', ACTIVE);
        $builder->orderBy('modules.id', 'asc');

        return $builder->get()->getResult();
    }

    public function getUserInfo(array|string|null $conditions, $selectColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->get()->getRow();
    }

    public function getUserId(array|string|null $conditions, $selectColumn)
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
