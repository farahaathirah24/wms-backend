<?php

namespace App\Models\V1\App;

use CodeIgniter\Model;

class PutawaySessionModel extends Model
{
    protected $table            = 'putaway_sessions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_sessions_id' ,
        'products_id' ,
        'racks_id',
        'barcode' ,  
        'qty',
        'status'   ];

    public function getPutawayList($limit, $offset, $UserSession)
    {
        $request = service('request');
        $sort    = urlencode($request->getVar('sort_by'));
        $filter  = $request->getVar('filters');
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select('products.products_uuid,products.sku, products.description, putaway_sessions.qty');
        $builder->join('products', 'putaway_sessions.products_id = products.id', 'left');
        $builder->where('putaway_sessions.user_sessions_id', $UserSession);
        if ($filter) {
            $builder = applyFilter($builder, $filter);
        }
        if ($sort) {
            $builder = applySorting($builder, $sort);
        }
        $builder->limit($limit, $offset);
        $builder->groupBy('products.sku');

        return $builder->get()->getResult();
    }

    public function getTotalPutawayList($UserSession)
    {
        $request = service('request');
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select('products.sku, products.description, putaway_sessions.qty');
        $builder->join('products', 'putaway_sessions.products_id = products.id', 'left');
        $builder->where('putaway_sessions.user_sessions_id', $UserSession);
        $builder->groupBy('products.sku');

        return $builder->get()->getNumRows();
    }

    public function resetPutawaySessionQty($conditions, $setColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->set($setColumn);

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->update();
    }

    public function updateRacks($conditions, $setColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->set($setColumn);

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->update();
    }
    public function checkPutawaySession(array|string|null $conditions, $selectColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->get()->getRow();
    }

    public function updatePutawaySession($qty, array|string|null $conditions)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->set('qty', 'qty + ' . $qty, false);

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->update();
    }
    public function getPutawaySessionId(array|string|null $conditions, $selectColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);

        foreach ($conditions as $column => $value) {
            $builder->where($column, $value);
        }

        return $builder->get()->getResult();
    }

}
