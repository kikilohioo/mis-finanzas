<?php

namespace App\Events;

use App\Models\BaseModel;

class LogAuditoriaEvent extends Event
{
    /**
     * @var \App\Models\BaseModel
     */
    public $model;

    public function __construct(BaseModel $model)
    {
        $this->model = $model;
    }
}
