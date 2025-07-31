<?php

namespace App\Services\Content;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\EVO\EvoSiteContent;

class ContentService
{
    public function __construct(
        protected readonly EvoSiteContent $EvoSiteContent,
    ) {}

    public function getContentById(int $id): array
    {
        $content = $this->EvoSiteContent
                    ->query()
                    ->where(['published' => 1, 'id' => $id])
                    ->first()
                    ->makeHidden([
        "type",
        "contentType",
        "longtitle",
        "link_attributes",
        "published",
        "pub_date",
        "unpub_date",
        "parent",
        "isfolder",
        "introtext",
        "richtext",
        "template",
        "menuindex",
        "searchable",
        "cacheable",
        "createdby",
        "createdon",
        "editedby",
        "editedon",
        "deleted",
        "deletedon",
        "deletedby",
        "publishedby",
        "menutitle",
        "hide_from_tree",
        "privateweb",
        "privatemgr",
        "content_dispo",
        "hidemenu",
        "alias_visible",
                    ]);
        return $content->toArray();
    }

    public function getCities()
    {
        $content = $this->EvoSiteContent
            ->query()
            ->where(['template' => 17])
            ->get(['id', 'pagetitle', 'alias', 'published']);

        $cities = $content->map(function ($city) {
            $coords = [
                'minsk'       => ['lat' => 53.9045,   'lng' => 27.5615],
                'zhodino'     => ['lat' => 54.0156,   'lng' => 27.3099],
                'gomel'       => ['lat' => 52.4345,   'lng' => 30.9754],
                'brest'       => ['lat' => 52.0975,   'lng' => 23.7341],
                'grodno'      => ['lat' => 53.6690,   'lng' => 23.8138],
                'vitebsk'     => ['lat' => 55.1904,   'lng' => 30.2049],
                'mogilev'     => ['lat' => 53.8930,   'lng' => 30.3310],
                'soligorsk'   => ['lat' => 52.9560,   'lng' => 27.5380],
                'osipovichi'  => ['lat' => 53.1447,   'lng' => 29.0553],
                'lida'        => ['lat' => 53.8889,   'lng' => 25.3061],
                'molodechno'  => ['lat' => 54.3312,   'lng' => 26.8422],
                'baranovichi' => ['lat' => 53.1328,   'lng' => 26.0144],
                'mozyr'       => ['lat' => 52.0458,   'lng' => 29.2516],
            ];

            $alias = $city->alias;

            $city->latitude  = $coords[$alias]['lat'] ?? null;
            $city->longitude = $coords[$alias]['lng'] ?? null;

            $city->is_delivery_available = ($alias === 'minsk');

            return $city;
        });

        return $cities->toArray();
    }

    public function getCustomerInfo()
    {
        $content = $this->EvoSiteContent
                    ->query()
                    ->where(['template' => 19])
                    ->get(['id', 'pagetitle', 'alias', 'published', 'content']);

        return $content->toArray();
    }
}
