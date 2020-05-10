<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;

class VolumeController extends AbstractStoreController
{
    public function detail(Request $req, array $params)
    {
        $volume_id = (int)$params['id'];
        $user_id = $this->user->id ?: null;
        $country_code = Context::getClientCountryCode();
        $currency = CountryService::getCurrency($country_code);

        $volume = StoreComicVolumeRepository::findPurchasable($volume_id, $country_code, ['with_manga' => true]);
        if (!$volume) {
            return null;
        }

        if (!$volume->getIsAvailable()) {
            return $this->renderAuto('store/error/content_unavailable.html.twig');
        }

        $comic_id = $volume->getStoreComic()->getId();

        $all_volumes = StoreComicVolumeRepository::findPurchasableByComicId($comic_id, $country_code);
        $first_page_chapters = ComicChapterService::getChapters($comic_id, $country_code, $user_id);

        $num_total_chapters = StoreComicChapterRepository::countPurchasableByComicId($comic_id, $country_code);

        PriceService::appendPriceData($country_code, $currency, array_merge($all_volumes, [$volume]));


        $meta_records = MetaRecordRepository::findByEISBN($volume->getEISBN());
        $meta_record = $meta_records[0];
        $rating = $meta_record->getRating();

        // Payment情報
        if ($user_id ) {
            $payment = Payment::getPaymentSetting($this->user->getOrmUser());
        } else {
            $payment = null;
        }

        $history = $user_id ? History::createByUser($user_id) : History::createByCookie($req);
        $timestamp = Context::isProductionOnly() ? null : $req->query->get('timestamp');
        $data      = StoreService::getStoreTopData($user_id, $country_code, $history, $timestamp);
        $new_volumes = $data['new_volumes'];
        $on_sale_items = $data['on_sale_items'];

        $response = $this->renderAuto('store/comic_volume/detail.html.twig', [
            'country_code'          => $country_code,
            'volume'                => $volume,
            'rating'                => $rating,
            'all_volumes_data'      => array_map(
                function (StoreComicVolume $volume) {
                    return $volume->toViewData();
                },
                $all_volumes
            ),
            'chapters_data'         => array_map(
                function (StoreComicChapter $chapter) {
                    return $chapter->toViewData();
                },
                $first_page_chapters
            ),
            'new_volumes'           => array_map(
                function (StoreComicVolume $volume) {
                    return $volume->toViewData();
                },
                $new_volumes
            ),
            'on_sale_items'         => array_map(
                function (StoreComicVolume $volume) {
                    return $volume->toViewData();
                },
                $on_sale_items
            ),
            'num_total_chapters'              => $num_total_chapters,
            'num_chapters_per_page'           => ComicChapterService::NUM_ITEMS_PER_PAGE,
            'registered_card'                 => $payment['registered_card'],
            'braintree_token'                 => $payment['braintree_token'],
            'default_payment_method'          => $payment['default_payment_method'],
            'initial_payment_method'          => $payment['initial_payment_method'],
            'braintree_registration_datetime' => $payment['braintree_registration_datetime'],
            'featured_items'                  => $featured_items,
        ]);
        
        return $response;
    }
}
