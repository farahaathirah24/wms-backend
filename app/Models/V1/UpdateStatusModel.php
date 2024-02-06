<?php

namespace App\Models\V1;

use CodeIgniter\Model;
use Exception;

class UpdateStatusModel extends Model
{
    protected $table            = ''; // Specify the table name
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        // Specify the columns that are allowed to update
    ];

    public function UpdateStatus($queueId, $status, $active, $userId, $userSessionsId)
    {
        $this->db->transStart();

        try {
            $this->db->query("UPDATE queue
                SET
                    active = {$active},
                    updated_by = {$userId},
                    updated_at = now()
                WHERE
                    id = {$queueId}");

            $this->db->query("UPDATE receiving_lists
                SET
                    receiving_status = {$status},
                    updated_at = now(),
                    updated_by = {$userId}
                WHERE
                    queue_id = {$queueId}");
            $idReceivingList = $this->db->affectedRows();

            $this->db->query("UPDATE receiving_items
                SET
                    receiving_status = {$status},
                    updated_at = now(),
                    updated_by = {$userId}
                WHERE
                    receiving_lists_id = {$idReceivingList}");
            $sql = $this->db->getLastQuery();

            if ($userSessionsId) {
                $this->db->query("UPDATE receiving_sessions
                SET
                    status = {$active}
                WHERE
                    queue_id = {$queueId}
                    AND user_sessions_id = {$userSessionsId}");

                $this->db->query("UPDATE user_sessions
                SET
                    status = {$active}
                WHERE
                    id = {$userSessionsId}");
            }
            $this->db->transComplete();

            return true;
        } catch (Exception $e) {
            $this->db->transRollback();

            return false;
        }
    }
}
