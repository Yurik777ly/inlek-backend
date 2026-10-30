<?php

namespace App\Marketing\Engine\Matchers;

use App\Marketing\Engine\Context\MarketingContext;
use App\Marketing\Models\MarketingEvent;
use Illuminate\Support\Collection;

class EventMatcher
{
    /**
     * @return Collection<int, MarketingEvent>
     */
    public function match(
        MarketingContext $context
    ): Collection {

        return MarketingEvent::query()
            ->with(['items.condition', 'pharmacies'])
            ->where('is_ended', false)
            ->where('no_use_on_site', false)
            ->where('type', 1)
            ->whereIn('type_of_discount', [1, 2])
            ->where('provide_type', 1)
            ->where('date_from', '<=', $context->dateTime)
            ->where('date_to', '>=', $context->dateTime)
            ->orderByDesc('type')
            ->orderBy('id')
            ->get();
    }
}
