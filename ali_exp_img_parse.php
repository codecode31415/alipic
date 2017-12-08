<?php

include "simple_html_dom.php";

$ali_api_key='YOUR_API_KEY';

$lang_id = get_lang();
if ($lang_id == 'ru') {
    $lang = 'ru';
} else {
    $lang = 'en';
}

$cur_page = $_GET['cur_page'];
$offset = $_GET['offset'];

function multi_parser($url) {
    $ch = array();
    $responce = array();
    $mh = curl_multi_init();
    for ($i = 0; $i < sizeof($url); $i++) {
        $ch[$i] = curl_init($url[$i]);
        curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch[$i], CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch[$i], CURLOPT_HEADER, 0);

        $agent = "AAPP Application/1.0 (Windows; U; Windows NT 5.1; de; rv:1.8.0.4)";

        curl_setopt($ch[$i], CURLOPT_URL, $url[$i]);
        curl_setopt($ch[$i], CURLOPT_TIMEOUT, 10);
        curl_setopt($ch[$i], CURLOPT_MAXREDIRS, 50);
        curl_setopt($ch[$i], CURLOPT_COOKIEJAR, 'cookie.txt');
        curl_setopt($ch[$i], CURLOPT_COOKIEFILE, 'cookie.txt');
        curl_multi_add_handle($mh, $ch[$i]);
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
    } while ($running);

    //close all and get content as string
    for ($i = 0; $i < sizeof($url); $i++) {
        curl_multi_remove_handle($mh, $ch[$i]);
        $responce[$i] = curl_multi_getcontent($ch[$i]);
    }

    curl_multi_close($mh);
    return array($responce);
}

$arrContextOptions = array(
    "ssl" => array(
        "verify_peer" => false,
        "verify_peer_name" => false,
    ),
);

$primary_url = filter_input(INPUT_GET, "url");

if (strpos($primary_url, '//group.') == 0) {
    preg_match_all("/(\w+\.\w{2,4})/", $primary_url, $prod_id);
    $prod_id[0][2] = str_replace('.html', '', $prod_id[0][2]);
    $prod_id[0][2];
    $prod_id_url = $prod_id[0][2];
}

$prod_id_url_num = $prod_id_url;

if (strpos($primary_url, '//group.') != 0) {
    $prod_id_url_num_1 = explode('-', $primary_url);
    $prod_id_url_num = $prod_id_url_num_1[1];
}

if (strpos($primary_url, '/store/') != 0) {
    $prod_id_url_num_1 = explode('_', $prod_id_url_num);
    $prod_id_url_num = $prod_id_url_num_1[1];
}

$prod_id_url = "feedback.aliexpress.com/display/productEvaluation.htm?productId=" . $prod_id_url_num . "&ownerMemberId=221794469&companyId=231687423&memberType=seller&startValidDate=&i18n=true&withPictures=true&translate=N&evaStarFilterValue=all+Stars";
$prod_url_api = "http://gw.api.alibaba.com/openapi/param2/2/portals.open/api.getPromotionProductDetail/".$ali_api_key."?fields=originalPrice,salePrice,discount,evaluateScore,volume,storeUrl,productTitle,imageUrl,productUrl,storeName&language=" . $lang . "&productId=" . $prod_id_url_num . "";

$url_compose [0] = $prod_id_url;
$url_compose [1] = $prod_url_api;
$multi_urls = array_values($url_compose);
list($urls) = multi_parser($multi_urls);

$json = json_decode($urls[1]);

foreach ($json as $key) {
    $price_discount = $key->originalPrice;
    $price = $key->salePrice;
    $discount_rate = $key->discount;
    $rate = $key->evaluateScore;
    $orders = $key->volume;
    $store_title = $key->storeName;
    $store_link = $key->storeUrl;
    $prod_title = $key->productTitle;
    $thumb_src = $key->imageUrl;
    break;
}

$html = str_get_html($urls[0], false, stream_context_create($arrContextOptions));

if ($html) {
    foreach ($html->find('input [id="cb-withPictures-filter"]  em') as $element) {

        $pages = $element->innertext;
        $pages = ceil($pages / 10);
        $images = $element->innertext;
    }
    if (!$images) {
        $images = 0;
    }


    foreach ($html->find('p[class="r-score-des"]') as $element) {
        $rating = $element->innertext;
    }
    $urls_list = array();
    $pages_left = $pages;


    if ($cur_page == '') {
        $cur_page = 1;
    }

    for ($i = $cur_page; $i <= $cur_page; $i++) {
        $urls_list[$i] = $prod_id_url . "&page={$i}";
    }

    $multi_urls = array_values($urls_list);
    list($urls) = multi_parser($multi_urls);

    $all_ali_img = array();
    $n = 0;
    for ($i = 0; $i < sizeof($urls); $i++) {
        $arrContextOptions = array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
        );
        $html = str_get_html($urls[$i], false, stream_context_create($arrContextOptions));

        foreach ($html->find('img') as $element) {

            $all_ali_img[$n] = $element->src;
            $n++;
        }

        //comments counter
        $com_num = 0;
        $glob_array = array();
        $img_array = array();
        $stars_array = array();
        $comment_array = array();
        $devilery_array = array();
        $date_array = array();
        $flag_array = array();

        foreach ($html->find('div[class=feedback-item]') as $element) {
            $img_ar = 0;
            $star_ar = 0;
            $com_ar = 0;
            $delivery_ar = 0;
            $date_ar = 0;
            $flag_ar = 0;

            //img urls
            foreach ($element->find('ul[class=util-clearfix] li img') as $img) {
                $img_array[$img_ar] = $img->src;
                $img_ar++;
            }
            $glob_array[$com_num][1] = $img_array;

            //stars
            foreach ($element->find('span[class=star-view] span') as $stars) {
                $stars_array[$star_ar] = $stars->style;
                $stars_array[$star_ar] = str_replace('width:', '', $stars_array[$star_ar]);
                $star_ar++;
            }
            $glob_array[$com_num][2] = $stars_array;

            //comment
            foreach ($element->find('dt[class=buyer-feedback] span') as $comment) {
                $comment_array[$com_ar] = $comment->innertext;
                $com_ar++;
            }
            $glob_array[$com_num][3] = $comment_array;

            //delivery
            foreach ($element->find('div[class=user-order-info] span') as $delivery) {

                $devilery_array[$delivery_ar] = $delivery->innertext;
                $devilery_array[$delivery_ar] = str_replace('Logistics:', '', $devilery_array[$delivery_ar]);
                $devilery_array[$delivery_ar] = str_replace('Size:', '', $devilery_array[$delivery_ar]);
                $devilery_array[$delivery_ar] = str_replace('Color:', '', $devilery_array[$delivery_ar]);

                if ($lang == 'en') {
                    $devilery_array[$delivery_ar] = str_replace('Доставка', 'Logistics', $devilery_array[$delivery_ar]);
                }

                $delivery_ar++;
            }
            $glob_array[$com_num][4] = $devilery_array;

            //date
            foreach ($element->find('dd[class=r-time]') as $date) {
                $date_array[$date_ar] = $date->innertext;
                $date_ar++;
            }
            $glob_array[$com_num][5] = $date_array;

            //flag
            foreach ($element->find('div[class=user-country] b[class=css_flag]') as $flag) {
                $flag_array[$flag_ar] = $flag->innertext;
                $flag_ar++;
            }
            $glob_array[$com_num][6] = $flag_array;
            $com_num++;
        }
    }
    $url_valid = 1;
} else {
    $url_valid = 0;
}

function rate($rate_value) {
    switch ($rate_value) {
        case '100%':
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            break;

        case '90%':
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            break;

        case '80%':
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            break;

        case '60%':
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            break;

        case '40%':
            echo "<div class='reviews-grid-item__rating-star star is-active'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            break;


        case '20%':
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            echo "<div class='reviews-grid-item__rating-star star'></div>";
            break;
    }
}

?>