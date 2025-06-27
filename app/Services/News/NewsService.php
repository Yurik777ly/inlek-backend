<?php

namespace App\Services\News;

use App\Models\NewsView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NewsService
{
    public function __construct(
        protected readonly NewsView $NewsView,
    ) {}

    public function getNewsList(): Collection
    {
        return $this->NewsView
                    ->query()
                    ->where('published', 1)
                    ->orderBy('create_dttm_raw', 'DESC')
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
                    ]);
    }

    public function getNewsById(int $news_id): array
    {
        $news = $this->NewsView
                    ->query()
                    ->where(['published' => 1, 'contentid' => $news_id])
                    ->first()
                    ->makeHidden([
                        'create_dttm_raw', 
                        'edited_dttm_raw', 
                        'published_dttm_raw',
                        'menutitle',
                        'pub_date',
                        'published_dttm',
                        'edited_dttm',
                        'published',
                    ]);
        return $news->toArray();
    }
}
