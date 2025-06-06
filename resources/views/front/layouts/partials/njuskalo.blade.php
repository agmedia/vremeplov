<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<ad_list>
    @foreach ($items as $item)
        <ad_item class="ad_simple">
            <user_id>{{ config('settings.njuskalo.user_id') }}</user_id>
            <original_id>{{ $item['id'] }}</original_id>
            <category_id>{{ $item['group'] }}</category_id>
            <title>{{ $item['name'] }}</title>
            <currency_id>2</currency_id>
            <price>{{ $item['price'] }}</price>
            <description>{{ $item['description'] }}</description>
            <conditionId>20</conditionId>
            <phone_list>
                <phone>
                    <calling_code>385</calling_code>
                    <area_code>91</area_code>
                    <phone_number>7627441</phone_number>
                </phone>
            </phone_list>
            <youtubeUrl></youtubeUrl>
            <location_id>2656</location_id>
            <gmap_lng>15.989029329152421</gmap_lng>
            <gmap_lat>45.812110541331315</gmap_lat>
            <isOnlinePaymentEnabled>1</isOnlinePaymentEnabled>
            <availableParcelShops>
                <item>boxNow</item>
            </availableParcelShops>
            <deliveryPackageWeight>1</deliveryPackageWeight>
            <videoCallOption>0</videoCallOption>
            <webshopLink>https://www.antikvarijat-vremeplov.hr/{{ $item['slug'] }}</webshopLink>
            <image_list>
                <image>{{ $item['image'] }}</image>
            </image_list>
        </ad_item>
    @endforeach
</ad_list>
