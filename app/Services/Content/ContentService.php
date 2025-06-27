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
        return $content->toArray();
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
