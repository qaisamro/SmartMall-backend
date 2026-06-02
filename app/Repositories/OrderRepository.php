<?php

namespace App\Repositories;

use App\Models\Order;

class OrderRepository extends BaseRepository
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    public function getByUser($userId)
    {
        return $this->model->where('user_id', $userId)->with('items.product')->orderBy('created_at', 'desc')->get();
    }

    public function getByMall($mallId)
    {
        return $this->model->where('mall_id', $mallId)->with('items.product', 'user')->orderBy('created_at', 'desc')->get();
    }
}
