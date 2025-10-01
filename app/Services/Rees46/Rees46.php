<?php

namespace App\Services\Rees46;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class Rees46
{
    const SHOP_ID = 'a46b953ef509cadb85a3692a13dfac';
    const DID = 'KwkHbFxeho';
    const SID = '8Cix7LJ0Pw';
    const CODE_TOP = '4535a5ced10038a697eabcf3c0d570bc'; //Похожие товары
    const CODE_BOTTOM = '94ff31e12486593f01b00e32b83bd1f4'; //С этим товаром также смотрят
    public static function getRecommendation($categoryId = null): array
    {
        $params = [
            'shop_id' => self::SHOP_ID,
            'did' => self::DID,
            'sid' => self::SID,
            'seance' => self::SID,
            'segment' => 'B',
            'stream' => 'website',
        ];

        if ($categoryId) $params['category'] = $categoryId;

        $response = Http::timeout(1)
            ->get('https://api.rees46.ru/recommend/' . self::CODE_TOP, $params);

        if($response->successful() && $response->json('recommends')) {
            $productIds = $response->json('recommends');
            try {
                return self::getProductsRecommendation($productIds);
            } catch (\Exception $exception) {
                return [];
            }
        }
        return [];
    }

    private static function getProductsRecommendation(array $ids): array
    {
        $need = implode(', ', array_fill(0, count($ids), '?'));
        $result = DB::select('SELECT
            	`esc`.`id` AS product_id,
            	`esc`.`parent` AS parent,
            	`esc`.`alias` AS alias,
            	`esc`.`pagetitle` AS pagetitle,
            	`estc1`.`value` AS image,
            	`estc10`.`value` AS product_price_from,
            	`estc11`.`value` AS product_price_from_old,
            	`estc12`.`value` AS product_price_from_percent,
            	`estc13`.`value` AS product_sticker
            FROM evo_site_content as esc
            LEFT JOIN evo_site_tmplvar_contentvalues as estc1 ON `esc`.`id` = `estc1`.`contentid` and `estc1`.`tmplvarid` = 5
            LEFT JOIN evo_site_tmplvar_contentvalues as estc10 ON `esc`.`id` = `estc10`.`contentid` and `estc10`.`tmplvarid` = 7
            LEFT JOIN evo_site_tmplvar_contentvalues as estc11 ON `esc`.`id` = `estc11`.`contentid` and `estc11`.`tmplvarid` = 8
            LEFT JOIN evo_site_tmplvar_contentvalues as estc12 ON `esc`.`id` = `estc12`.`contentid` and `estc12`.`tmplvarid` = 9
            LEFT JOIN evo_site_tmplvar_contentvalues as estc13 ON `esc`.`id` = `estc13`.`contentid` and `estc13`.`tmplvarid` = 14
            where `esc`.id in ('.$need.')', $ids);

        return $result;
    }
}
