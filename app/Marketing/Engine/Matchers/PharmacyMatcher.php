<?php

namespace App\Marketing\Engine\Matchers;

use App\Marketing\DTO\CartItem;
use App\Marketing\Engine\Context\MarketingContext;
use App\Marketing\Models\MarketingEvent;
use App\Marketing\Models\MarketingPharmacy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PharmacyMatcher
{
    /**
     * Кэш pharmacy content id / ID_punkt => pharmacy_parent_id.
     *
     * @var array<string, string>
     */
    protected $parentIdByKey = [];

    /**
     * Оставляет мероприятия, подходящие хотя бы одной позиции корзины.
     *
     * @param Collection<int, MarketingEvent> $events
     *
     * @return Collection<int, MarketingEvent>
     */
    public function match(
        Collection $events,
        MarketingContext $context
    ): Collection {

        return $events->filter(

            function (MarketingEvent $event) use ($context) {

                foreach ($context->items as $cartItem) {
                    if ($this->eventMatchesCartItem($event, $cartItem, $context)) {
                        return true;
                    }
                }

                return false;
            }

        )->values();
    }

    /**
     * Проверка применимости ММ к конкретной позиции корзины.
     */
    public function eventMatchesCartItem(
        MarketingEvent $event,
        CartItem $cartItem,
        ?MarketingContext $context = null
    ): bool {

        $dateTime = ($context !== null && $context->dateTime)
            ? $context->dateTime
            : now();

        $pharmacies = $event->relationLoaded('pharmacies')
            ? $event->pharmacies
            : $event->pharmacies()->get();

        if ($pharmacies->isEmpty()) {
            return false;
        }

        foreach ($pharmacies as $pharmacy) {
            if (!$this->isPharmacyActive($pharmacy, $dateTime)) {
                continue;
            }

            if ($this->matchesPharmacyRow($pharmacy, $cartItem)) {
                return true;
            }
        }

        return false;
    }

    protected function isPharmacyActive(
        MarketingPharmacy $pharmacy,
        $dateTime
    ): bool {

        if (empty($pharmacy->date_from)) {
            return true;
        }

        return $pharmacy->date_from <= $dateTime;
    }

    protected function matchesPharmacyRow(
        MarketingPharmacy $pharmacy,
        CartItem $cartItem
    ): bool {

        $uid = trim((string)$pharmacy->pharmacy_uid);

        if ($uid === '' || $uid === '0') {
            return false;
        }

        // Группа аптек 1С: id_pharmacy = "00-00000002", is_group = true
        // → все аптеки сайта с TV pharmacy_parent_id = этот id.
        if ($pharmacy->is_group) {
            return $this->matchesGroup($uid, $cartItem);
        }

        // Обычная аптека: id_pharmacy = 75 → TV ID_punkt.
        return trim((string)$cartItem->pharmacyUid) !== ''
            && $uid === trim((string)$cartItem->pharmacyUid);
    }

    protected function matchesGroup(string $groupUid, CartItem $cartItem): bool
    {
        $parentId = trim((string)$cartItem->pharmacyParentId);

        if ($parentId !== '' && $parentId === $groupUid) {
            return true;
        }

        // Fallback: читаем TV pharmacy_parent_id по аптеке корзины
        // (если parent_id не попал в CartItem).
        $resolved = $this->resolveParentIdForCartItem($cartItem);

        return $resolved !== '' && $resolved === $groupUid;
    }

    protected function resolveParentIdForCartItem(CartItem $cartItem): string
    {
        if ((int)$cartItem->pharmacyId > 0) {
            $key = 'id:' . (int)$cartItem->pharmacyId;
            if (!array_key_exists($key, $this->parentIdByKey)) {
                $this->parentIdByKey[$key] = $this->loadParentIdByContentId(
                    (int)$cartItem->pharmacyId
                );
            }

            return $this->parentIdByKey[$key];
        }

        $uid = trim((string)$cartItem->pharmacyUid);
        if ($uid === '') {
            return '';
        }

        $key = 'uid:' . $uid;
        if (!array_key_exists($key, $this->parentIdByKey)) {
            $this->parentIdByKey[$key] = $this->loadParentIdByPunkt($uid);
        }

        return $this->parentIdByKey[$key];
    }

    protected function loadParentIdByContentId(int $contentId): string
    {
        $tvId = $this->pharmacyParentTvId();
        if (!$tvId) {
            return '';
        }

        $value = DB::table('evo_site_tmplvar_contentvalues')
            ->where('tmplvarid', $tvId)
            ->where('contentid', $contentId)
            ->value('value');

        return trim((string)$value);
    }

    protected function loadParentIdByPunkt(string $punkt): string
    {
        $parentTvId = $this->pharmacyParentTvId();
        $punktTvId = $this->idPunktTvId();

        if (!$parentTvId || !$punktTvId) {
            return '';
        }

        $contentId = DB::table('evo_site_tmplvar_contentvalues as punkt')
            ->join(
                'evo_site_tmplvar_contentvalues as parent',
                'parent.contentid',
                '=',
                'punkt.contentid'
            )
            ->where('punkt.tmplvarid', $punktTvId)
            ->where('punkt.value', $punkt)
            ->where('parent.tmplvarid', $parentTvId)
            ->value('parent.value');

        return trim((string)$contentId);
    }

    protected function pharmacyParentTvId(): ?int
    {
        static $id = null;

        if ($id === null) {
            $id = (int) DB::table('evo_site_tmplvars')
                ->where('name', 'pharmacy_parent_id')
                ->value('id');
        }

        return $id > 0 ? $id : null;
    }

    protected function idPunktTvId(): ?int
    {
        static $id = null;

        if ($id === null) {
            $id = (int) DB::table('evo_site_tmplvars')
                ->where('name', 'ID_punkt')
                ->value('id');
        }

        return $id > 0 ? $id : null;
    }
}
