<?php

namespace App\Services\Article;

use App\Models\ArticlesView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ArticleService
{
    public function __construct(
        protected readonly ArticlesView $ArticlesView,
    ) {}

    public function getArticleList(): Collection
    {
        return $this->ArticlesView
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

    public function getArticleById(int $article_id): array
    {
        $article = $this->ArticlesView
                    ->query()
                    ->where(['published' => 1, 'contentid' => $article_id])
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
        return $article->toArray();
    }
}
