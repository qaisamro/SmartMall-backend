<?php

namespace App\Repositories;

use App\Models\Shelf;

class ShelfRepository extends BaseRepository
{
    public function __construct(Shelf $model)
    {
        parent::__construct($model);
    }

    public function getByBranch($branchId)
    {
        return $this->model->where('branch_id', $branchId)->get();
    }

    public function getByMall($mallId)
    {
        return $this->model->where('mall_id', $mallId)->get();
    }
}
