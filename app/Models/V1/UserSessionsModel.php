<?php

namespace App\Models\V1;

use CodeIgniter\Model;

class UserSessionsModel extends Model
{
    protected $table            = 'user_sessions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'session_uuid',
        'users_id',
        'sub_modules_id',
        'ongoing_list',
        'location_in',
        'locations_id',
        'created_at',
        'status',
        'locations_id',
    ];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    public function updateSession(string|array $sessionUuid, object|array $data)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);

        if (is_array($sessionUuid)) {
            $builder->whereIn('session_uuid', $sessionUuid);
        } else {
            $builder->where('session_uuid', $sessionUuid);
        }

        return $builder->update($data);
    }

    public function getSessionInfo(array|string|null $conditions, array|string $selectColumn = '*')
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);
        $builder->where('user_sessions.status', ACTIVE);
        $builder->join('sub_modules', 'sub_modules.id = user_sessions.sub_modules_id');

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->get()->getRow();
    }

    public function checkUserSession($session_uuid, $moduleId, $userId)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($this->primaryKey);
        $builder->where('status', USER_ACTIVE);
        if ($session_uuid) {
            $builder->where('session_uuid', $session_uuid);
        }
        $builder->where('sub_modules_id', $moduleId);
        $builder->where('users_id', $userId);

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
