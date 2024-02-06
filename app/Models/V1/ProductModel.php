<?php

namespace App\Models\V1;

use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table            = 'products';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        // column that is allowed to update
    ];

    public function getProductInfo(array|string|null $value, $selectColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);
        $builder->orWhere('barcode_1', $value)
            ->orWhere('barcode_2', $value)
            ->orWhere('barcode_3', $value)
            ->orWhere('sku', $value);

        return $builder->get()->getRow();
    }

    public function getProductByProductUuid(array|string|null $productsUuid, $selectColumn)
    {
        $db      = db_connect();
        $builder = $db->table($this->table);
        $builder->select($selectColumn);
        $builder->where('products.products_uuid', $productsUuid);

        return $builder->get()->getRow();
    }

    // public function getProductBySkuBarcode(string $skuBarcode, array|string $selectColumn)
    // {
    //     $db      = db_connect();
    //     $skuBarcode  = $db->escape($skuBarcode);
    //     $builder = $db->table($this->table);
    //     $builder->select($selectColumn);
    //     $where = "(sku={$skuBarcode} OR barcode_1={$skuBarcode} OR barcode_2={$skuBarcode} OR barcode_3 ={$skuBarcode})";
    //     $builder->where('active', ACTIVE);
    //     return $builder->get()->getRow();

    // }
}
