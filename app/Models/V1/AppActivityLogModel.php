<?php

namespace App\Models\V1\App;

use CodeIgniter\Model;
use Exception;

class AppActivityLogModel extends Model
{
    protected $table            = 'app_activity_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'staff_id',
        'module_id',
        'action_type',
        'description',
        'id_affected',
        'data',
        'created_at',
    ];

    /**
     * Inserts a new record into the app_activity_logs table with the provided information.
     *
     * @param int          $userId            The user ID.
     * @param string       $actionDescription The action description.
     * @param int          $moduleId          The module ID.
     * @param int          $action            The action type. (0:other, 1:view, 2:update, 3:delete	)
     * @param int          $idAffected        The ID affected.
     * @param array|object $recordAffected    The record affected.
     *
     * @return bool Returns true if the insert is successful, otherwise throws an exception.
     *
     * @throws Exception
     */
    public function logActivity(int $userId, string $actionDescription, int $moduleId = 0, int $action = 0, int $idAffected = 0, array|object $recordAffected = [])
    {
        $auditLog = [
            'users_id'    => $userId,
            'modules_id'  => $moduleId,
            'action_type' => $action,
            'description' => $actionDescription, // action description
            'id_affected' => $idAffected, // id records  affected
            'data'        => json_encode($recordAffected), // records affected
            'created_at'  => getDateTime(), // records affected
        ];

        if (! $this->db->table($this->table)->insert($auditLog)) {
            throw new Exception('Unable To insert Audit log, Query Failed');
        }

        return true;
    }
}
