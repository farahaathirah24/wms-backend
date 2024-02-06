<?php

namespace App\Models\V1;

use CodeIgniter\Model;

class SerialNumbersModel extends Model
{
    protected $table            = 'serial_numbers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'products_id',
        'serial_number',
        'created_at',
        'created_by',
        'active',
        'serial_numbers_uuid',
    ];

    public function getSerialNumber($serialNumber)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($this->primaryKey);
        $builder->where('active', ACTIVE);
        $builder->where('serial_number', $serialNumber);

        return $builder->get()->getRow();
    }

    public function getSerialNumberByUuid(array|string|null $value, $selectColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);
        $builder->Where('serial_numbers_uuid', $value);

        return $builder->get()->getRow();
    }

    public function getSerialNumbers(string $productUuid, array $selectColumn = [])
    {
        $request = service('request');
        $sort    = $request->getVar('sort_by');
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);
        $builder->join('products', 'serial_numbers.products_id = products.id', 'left');
        $builder->join('serial_numbers_status', 'serial_numbers.id = serial_numbers_status.serial_numbers_id', 'left');
        $builder->where('products.products_uuid', $productUuid);
        $builder->where('serial_numbers.active', ACTIVE);

        return $builder;
    }

    public function getReceivingSerialNumber(string $productUuid, int $userSessionId, int $queueId, int $limit, int $offset, array $selectColumn = [])
    {
        $request = service('request');
        $sort    = $request->getVar('sort_by');
        $builder = $this->getSerialNumbers($productUuid, $selectColumn);
        $builder->join('receiving_sessions', 'serial_numbers_status.receiving_sessions_id = receiving_sessions.id', 'left');
        $builder->where('receiving_sessions.user_sessions_id', $userSessionId);
        $builder->where('receiving_sessions.queue_id', $queueId);

        $builder->limit($limit, $offset);
        if ($sort) {
            $builder = applySorting($builder, $sort);
        }

        return $builder->get()->getResult();
    }

    public function getTotalReceivingSerialNumber(string $productUuid, int $userSessionId, int $queueId, array $selectColumn = [])
    {
        $builder = $this->getSerialNumbers($productUuid, $selectColumn);
        $builder->join('receiving_sessions', 'serial_numbers_status.receiving_sessions_id = receiving_sessions.id', 'left');
        $builder->where('receiving_sessions.user_sessions_id', $userSessionId);
        $builder->where('receiving_sessions.queue_id', $queueId);

        return $builder->get()->getNumRows();
    }
    public function getPutawaySerialNumber(string $productUuid, int $userSessionId, int $limit, int $offset, array $selectColumn = [])
    {
        $request = service('request');
        $sort    = $request->getVar('sort_by');
        $builder = $this->getSerialNumbers($productUuid, $selectColumn);
        $builder->join('putaway_sessions_serial_numbers', 'serial_numbers_status.serial_numbers_id = putaway_sessions_serial_numbers.serial_numbers_id', 'left');
        $builder->join('putaway_sessions', 'putaway_sessions_serial_numbers.putaway_sessions_id = putaway_sessions.id', 'left');
        $builder->where('putaway_sessions.user_sessions_id', $userSessionId);
        $builder->where('putaway_sessions_serial_numbers.active', ACTIVE);
        $builder->limit($limit, $offset);
        if ($sort) {
            $builder = applySorting($builder, $sort);
        }
        return $builder->get()->getResult();
    }

    public function getTotalPutawaySerialNumber(string $productUuid, int $userSessionId, array $selectColumn = [])
    {
        $builder = $this->getSerialNumbers($productUuid, $selectColumn);
        $builder->join('putaway_sessions_serial_numbers', 'serial_numbers_status.serial_numbers_id = putaway_sessions_serial_numbers.serial_numbers_id', 'left');
        $builder->join('putaway_sessions', 'putaway_sessions_serial_numbers.putaway_sessions_id = putaway_sessions.id', 'left');
        $builder->where('putaway_sessions.user_sessions_id', $userSessionId);
        $builder->where('putaway_sessions_serial_numbers.active', ACTIVE);
        return $builder->get()->getNumRows();
    }
}