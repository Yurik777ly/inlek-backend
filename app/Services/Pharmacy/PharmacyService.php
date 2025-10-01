<?php

namespace App\Services\Pharmacy;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\PharmaciesView;

class PharmacyService 
{
    public function __construct(
        protected readonly PharmaciesView $PharmaciesView,
    ) {}

    public function getPharmacies($address = ''): Collection
    {
        $queryObject = $this->PharmaciesView
                    ->query()
                    ->where('published', 1);
        if(!empty($address)) {
            $queryObject->where('address', 'like', '%'.trim($address).'%');
        }
        
        return  $queryObject
                    ->get()
                    ->makeHidden([
                        'create_dttm_raw', 
                        'edited_dttm_raw', 
                        'published_dttm_raw',
                        'content',
                        'menutitle',
                        'published_dttm',
                        'edited_dttm',
                        'published',
                        'pub_date',
                        'create_dttm',
                    ]);
    }

    public function getPharmacyById($id = PharmaciesView::PHARMACY_ID_FOR_DELIVERY): Collection
    {
        $queryObject = $this->PharmaciesView
                    ->query()
                    ->where('pharmacy_id', $id);
        
        return  $queryObject
                    ->get()
                    ->makeHidden([
                        'create_dttm_raw', 
                        'edited_dttm_raw', 
                        'published_dttm_raw',
                        'content',
                        'menutitle',
                        'published_dttm',
                        'edited_dttm',
                        'published',
                        'pub_date',
                        'create_dttm',
                    ]);
    }
}