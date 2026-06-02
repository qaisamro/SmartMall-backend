<?php

namespace App\Repositories;

use App\Models\Product;

class ProductRepository extends BaseRepository
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function getByMall($mallId)
    {
        return $this->model->where('mall_id', $mallId)->get();
    }

    public function findByBarcode($barcode)
    {
        return $this->model->where('barcode', $barcode)->firstOrFail();
    }
}
