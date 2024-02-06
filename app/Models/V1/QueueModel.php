<?php

namespace App\Models\V1;

use CodeIgniter\Model;

class QueueModel extends Model
{
    protected $table            = 'queue';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'active',
        'updated_at',
        'updated_by',
        // column that is allowed to update
    ];

    public function getQueueInfo($queue_uuid)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($this->primaryKey);
        $builder->where('active', ACTIVE);
        $builder->where('queue_uuid', $queue_uuid);

        return $builder->get()->getRow();
    }

    public function getQueueList($offset, $limit)
    {
        $request = service('request');
        $filter  = $request->getVar('filters');
        $sort    = urlencode($request->getVar('sort_by'));
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select([
            'queue.queue_uuid',
            'queue.queue_number',
            'suppliers.supplier_code as supplier_code',
            'receiving_status.name as status',
        ]);
        $builder->join('receiving_lists', 'receiving_lists.queue_id = queue.id');
        $builder->join('receiving_status', 'receiving_status.id = receiving_lists.receiving_status');
        $builder->join('suppliers', 'suppliers.id = receiving_lists.supplier_id');
        $builder->where('receiving_lists.receiving_status', RECEIVING_PENDING);
        $builder->groupBy('queue.queue_number');
        $builder->limit($limit, $offset);
        if ($filter) {
            $builder = applyFilter($builder, $filter);
        }
        if ($sort) {
            $builder = applySorting($builder, $sort);
        }

        return $builder->get()->getResultArray();
    }

    public function getTotalQueueList()
    {
        $request = service('request');
        $db      = db_connect();
        $builder = $db->table($this->table);

        $builder->select('*');
        $builder->join('receiving_lists', 'receiving_lists.queue_id = queue.id');
        $builder->where('receiving_lists.receiving_status', RECEIVING_PENDING);

        return $builder->get()->getNumRows();
    }

    public function GetQueueItemList($limit, $offset, $queue_uuid, $UserSession)
    {
        $request = service('request');
        $sort    = urlencode($request->getVar('sort_by'));
        $db      = db_connect();
        $builder = $db->table('receiving_items');

        $subquery = $db->table('receiving_sessions')
            ->select('COALESCE(SUM(qty), 0)')
            ->where('product_id = products.id')
            ->where('queue_id = queue.id')
            ->where('user_sessions_id', $UserSession)
            ->getCompiledSelect();

        $builder->select([
            'products.products_uuid',
            'products.sku',
            'products.description',
            'products.required_serial_number',
            'receiving_items.qty',
            "({$subquery}) as rqty",
        ], false);

        $builder->join('receiving_lists', 'receiving_items.receiving_lists_id = receiving_lists.id', 'left');
        $builder->join('queue', 'receiving_lists.queue_id = queue.id', 'left');
        $builder->join('products', 'receiving_items.product_id = products.id', 'left');
        $builder->where('queue.id', $queue_uuid);
        $builder->where('queue.active', ACTIVE);
        $builder->limit($limit, $offset);
        if ($sort) {
            $builder = applySorting($builder, $sort);
        }
        $builder->groupBy('products.sku');

        return $builder->get()->getResultArray();
    }

    public function getTotalQueueItemList($queue_uuid, $UserSession)
    {
        $request = service('request');
        $db      = db_connect();
        $builder = $db->table('receiving_items');

        $subquery = $db->table('receiving_sessions')
            ->select('COALESCE(SUM(qty), 0)')
            ->where('product_id = products.id')
            ->where('queue_id = queue.id')
            ->where('user_sessions_id', $UserSession)
            ->getCompiledSelect();

        $builder->select([
            'products.products_uuid',
            'products.sku',
            'products.description',
            'products.required_serial_number',
            'receiving_items.qty',
            "({$subquery}) as rqty",
        ], false);

        $builder->join('receiving_lists', 'receiving_items.receiving_lists_id = receiving_lists.id', 'left');
        $builder->join('queue', 'receiving_lists.queue_id = queue.id', 'left');
        $builder->join('products', 'receiving_items.product_id = products.id', 'left');
        $builder->where('queue.id', $queue_uuid);
        $builder->where('queue.active', ACTIVE);
        $builder->groupBy('products.sku');

        return $builder->get()->getNumRows();
    }

    public function getQueueItem($queue_uuid, $UserSession)
    {
        $db      = db_connect();
        $builder = $db->table('receiving_items');

        $subquery = $db->table('receiving_sessions')
            ->select('COALESCE(SUM(qty), 0)')
            ->where('product_id = products.id')
            ->where('queue_id = queue.id')
            ->where('user_sessions_id', $UserSession)
            ->getCompiledSelect();

        $builder->select([
            'receiving_items.id',
            'receiving_items.qty',
            'receiving_items.product_id',
            "({$subquery}) as rqty",
        ], false);

        $builder->join('receiving_lists', 'receiving_items.receiving_lists_id = receiving_lists.id', 'left');
        $builder->join('queue', 'receiving_lists.queue_id = queue.id', 'left');
        $builder->join('products', 'receiving_items.product_id = products.id', 'left');
        $builder->where('queue.id', $queue_uuid);
        $builder->where('queue.active', ACTIVE);
        $builder->groupBy('products.sku');

        return $builder->get()->getResultArray();
    }

    public function getQuantitydiff($queueId, $UserSession)
    {
        $db      = db_connect();
        $builder = $db->table('queue');

        $subquery = $db->table('receiving_sessions')
            ->select('COALESCE(SUM(receiving_sessions.qty), 0)')
            ->where('receiving_sessions.queue_id = queue.id')
            ->where('receiving_sessions.user_sessions_id', $UserSession)
            ->getCompiledSelect();

        $builder->select([
            'SUM(receiving_items.qty) as sumQty',
            "({$subquery}) as sumRqty",
        ], false);
        $builder->join('receiving_lists', 'queue.id = receiving_lists.queue_id', 'left');
        $builder->join('receiving_items', 'receiving_lists.id = receiving_items.receiving_lists_id', 'left');

        $builder->where('queue.id', $queueId);
        $builder->where('queue.active', ACTIVE);

        // $builder->groupBy('products.sku');
        return $builder->get()->getRowObject();
    }
}
