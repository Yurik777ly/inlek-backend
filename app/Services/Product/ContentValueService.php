<?php

namespace App\Services\Product;

use App\Models\EVO\EvoSiteTmplvarContentvalue;
use App\Models\EVO\EvoSiteTmplvar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContentValueService
{
    public function __construct(
        protected readonly EvoSiteTmplvarContentvalue $Contentvalue,
        protected readonly EvoSiteTmplvar $Tmplvar,
    ) {}

    public function getContentValues(Collection $data, $hide = 1): array
    {
        $ids = collect($data)->map(function ($row) use ($data) {
            return $row->id;
        });
        $dataq = $this->Contentvalue
            ->query()
            ->whereIn('id', $ids)
            ->with('tmplvar')
            ->get();
        
        $datak = [];
        collect($data)->map(function ($id) use ($dataq, &$datak) {
            $row = $dataq->where('tmplvarid', $id->tmplvarid)->first();
            if(!$row) return [];
            if(!empty($row->value)) {
                $datak[$row->tmplvar->name] = $row->value;
            }
        });  
        return $datak;
    }
}
/*
            unset($contentValues['product_similar']);
            unset($contentValues['product_related']);
            unset($contentValues['product_cities']);
            unset($contentValues['product_pharmacies']);
*/