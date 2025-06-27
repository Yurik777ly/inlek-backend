<?php

namespace App\Http\Dto;

class BaseDTO
{
    protected $hidden = [];

    public function toArray()
    {
        $this->hidden[] = 'hidden';
        $out = array_diff_key(get_object_vars($this), array_flip($this->hidden));
        return $out;
    }
}
