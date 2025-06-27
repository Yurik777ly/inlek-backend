<?php

namespace App\Services\Banner;

use App\Models\EVO\EvoSystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BannerService
{
    public function __construct(
        protected readonly EvoSystemSetting $EvoSystemSetting,
    ) {}

    private function getBannersByName($name): ?array
    {
        $data =  $this->EvoSystemSetting
                    ->query()
                    ->where('setting_name', $name)
                    ->first();

        return $data->setting_value ? json_decode($data->setting_value, 1) : [];
    }

    public function getBanners(): array
    {
        $advertCategory = $this->getBannersByName('site_advert_category');
        $advertAside = $this->getBannersByName('site_advert_aside');
        $advertHomeAside = $this->getBannersByName('site_advert_home_aside');
        $advertHomeMain = $this->getBannersByName('site_advert_home_main');
        $advertHomeSlider = $this->getBannersByName('site_advert_home_slider');
        $advertCatalog = $this->getBannersByName('site_advert_catalog');
        $advertCategory = $this->getBannersByName('site_advert_category');

        return [
            'aside'     => $advertAside, 
            'homeAside' => $advertHomeAside,
            'homeMain'  => $advertHomeMain,
            'homeSlider'=> $advertHomeSlider,
            'catalog'   => $advertCatalog,
            'category'  => $advertCategory,
        ];
    }
}
