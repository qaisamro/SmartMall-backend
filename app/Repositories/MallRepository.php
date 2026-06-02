<?php

namespace App\Repositories;

use App\Models\Mall;

class MallRepository extends BaseRepository
{
    public function __construct(Mall $model)
    {
        parent::__construct($model);
    }

    public function getActiveMalls(string $search = null)
    {
        $query = $this->model->with('theme')->where('is_active', true)->where('status', 'approved');

        if ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('name_ar', 'LIKE', "%{$search}%")
                    ->orWhere('name_en', 'LIKE', "%{$search}%")
                    ->orWhere('slug', 'LIKE', "%{$search}%")
                    ->orWhere('location_arabic', 'LIKE', "%{$search}%");
            });
        }

        return $query->get();
    }

    public function getByOwner($ownerId)
    {
        return $this->model->with('theme')->where('owner_id', $ownerId)->get();
    }
}
