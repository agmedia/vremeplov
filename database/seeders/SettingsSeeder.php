<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        DB::insert(
            "INSERT INTO `settings` (`user_id`, `code`, `key`, `value`, `json`, `created_at`, `updated_at`) VALUES
              (null, 'action', 'group_list', '" . '[{"id":"product","title":"Artikl"},{"id":"category","title":"kategorija"},{"id":"publisher","title":"Nakladnik"},{"id":"author","title":"Autor"}]' . "', 1, NOW(), NOW()),
              (null, 'action', 'type_list', '" . '[{"id":"P","title":"Postotak"},{"id":"F","title":"Fiksni"}]' . "', 1, NOW(), NOW()),
              (null, 'currency', 'list', '" . '[{"id":1,"title":"Hrvatska kuna","code":"HRK","symbol_left":null,"symbol_right":"kn","value":"7.53450","decimal_places":"2","status":true,"main":false},{"id":2,"title":"Euro","code":"EUR","symbol_left":null,"symbol_right":"\u20ac","value":"1","decimal_places":"2","status":true,"main":true}]' . "', 1, NOW(), NOW()),
              (null, 'geo_zone', 'list', '" . '[{"status":true,"title":"Hrvatska","description":null,"state":{"2":"Croatia"},"id":1},{"title":"Europa","description":null,"id":2,"status":true,"state":{"2":"Austria","3":"Belgium","4":"Bulgaria","5":"Cyprus","6":"Czech Republic","7":"Denmark","8":"Estonia","9":"Finland","10":"France, Metropolitan","11":"Greece","12":"Germany","13":"Hungary","14":"Ireland","15":"Italy","16":"Latvia","17":"Lithuania","18":"Luxembourg","19":"Malta","20":"Netherlands","21":"Poland","22":"Portugal","23":"Romania","24":"Slovak Republic","25":"Slovenia","26":"Spain","27":"Sweden","28":"Bosnia and Herzegovina","29":"Serbia"}},{"status":true,"title":"Europa + HR","description":null,"state":{"2":"Austria","3":"Belgium","4":"Bulgaria","5":"Croatia","6":"Cyprus","7":"Denmark","8":"Estonia","9":"Germany","10":"Hungary","11":"Ireland","12":"Lithuania","13":"Latvia","14":"Luxembourg","15":"Malta","16":"Portugal","17":"Poland","18":"Romania","19":"Slovak Republic","20":"Slovenia","21":"Spain","22":"Swaziland","23":"Sweden"},"id":3},{"status":true,"title":"Sve","description":null,"id":4}]' . "', 1, NOW(), NOW()),
              (null, 'order', 'statuses', '" . '[{"id":1,"title":"Novo","sort_order":"0","color":"info"},{"id":2,"title":"\u010ceka uplatu","sort_order":"1","color":"warning"},{"id":3,"title":"Pla\u0107eno","sort_order":"3","color":"success"},{"id":4,"title":"Poslano","sort_order":"4","color":"secondary"},{"id":5,"title":"Otkazano","sort_order":"5","color":"danger"},{"id":6,"title":"Vra\u0107eno","sort_order":"6","color":"dark"},{"id":7,"title":"Odbijeno","sort_order":"2","color":"danger"},{"id":8,"title":"Nedovr\u0161ena","sort_order":"7","color":"light"},{"id":9,"title":"Zavr\u0161eno","sort_order":"9","color":"primary"},{"id":10,"title":"va\u0161a knjiga je spremna za preuzimanje","sort_order":"10","color":"dark"}]' . "', 1, NOW(), NOW()),
              (null, 'payment', 'list.cod', '" . '[{"title":"Gotovinom prilikom pouze\u0107a","code":"cod","min":"10","data":{"price":null,"short_description":"Pla\u0107anje gotovinom prilikom preuzimanja.","description":null},"geo_zone":"1","status":true,"sort_order":"2"}]' . "', 1, NOW(), NOW()),
              (null, 'payment', 'list.bank', '" . '[{"title":"Op\u0107om uplatnicom \/ Virmanom \/ Internet bankarstvom","code":"bank","min":null,"data":{"price":"0","short_description":"Uplatite direktno na na\u0161 bankovni ra\u010dun. Uputstva i uplatnice vam sti\u017ee putem maila.","description":null},"geo_zone":"4","status":true,"sort_order":"1"}]' . "', 1, NOW(), NOW()),
              (null, 'payment', 'list.pickup', '" . '[{"title":"Platite prilikom preuzimanja","code":"pickup","min":null,"data":{"price":"0","short_description":"Platiti mo\u017eete gotovinom ili karticama na POS ure\u0111ajima","description":null},"geo_zone":"3","status":true,"sort_order":"0"}]' . "', 1, NOW(), NOW()),
              (null, 'payment', 'list.corvus', '" . '[{"title":"Corvus Pay","code":"corvus","min":null,"data":{"price":null,"short_description":"Pla\u0107anje karticama","description":null,"shop_id":"10451","secret_key":"Hq2ASR08JK4wz9Ru5YXYoMiOS","callback":null,"test":"0"},"geo_zone":"4","status":true,"sort_order":"0"}]' . "', 1, NOW(), NOW()),
              (null, 'product', 'condition_styles', '" . '["Odli\u010dno","Dobro",null,"Vrlo dobro","Nova knjiga","Dob : 4-8, Nova knjiga","Rabljena, o\u010duvana knjiga","PO\u0160ARANE KORICE!!!","POTPIS PRO\u0160LE VLASNICE!!"]' . "', 1, NOW(), NOW()),
              (null, 'product', 'binding_styles', '" . '["Tvrdi","Meki",null,"Tvrdi s ovitkom","Meki s ovitkom"]' . "', 1, NOW(), NOW()),
              (null, 'product', 'letter_styles', '" . '["Latinica",null,"\u0106irilica","Gotica","Arapsko","Glagoljica"]' . "', 1, NOW(), NOW()),
              (null, 'shipping', 'list.flat', '" . '[{"title":"Isporuka pouze\u0107em","code":"flat","data":{"price":"45","time":"3-4 dana","short_description":"Pla\u0107anje se vr\u0161i prilikom isporuke.","description":null},"geo_zone":"1","status":false,"sort_order":"1"}]' . "', 1, NOW(), NOW()),
              (null, 'shipping', 'list.pickup', '" . '[{"title":"Osobno preuzimanje","code":"pickup","data":{"price":"0","time":null,"short_description":"Antuna \u0160oljana 33, 10000 Zagreb","description":null},"geo_zone":"1","status":true,"sort_order":"2"}]' . "', 1, NOW(), NOW()),
              (null, 'shipping', 'list.gls', '" . '[{"title":"Dostava","code":"gls","data":{"price":"5","time":"1-2 radna dana (RH)","short_description":"Dostava se vr\u0161i putem GLS dostavne slu\u017ebe.","description":null},"geo_zone":"1","status":true,"sort_order":"0"}]' . "', 1, NOW(), NOW()),
              (null, 'shipping', 'list.gls_eu', '" . '[{"title":"Tisak dostava","code":"gls_eu","data":{"price":"3","time":"1-2 radnih dana","short_description":"Dostava se vr\u0161i putem Tisak dostavne slu\u017ebe.","description":null},"geo_zone":"1","status":true,"sort_order":"0"}]' . "', 1, NOW(), NOW()),
              (null, 'shipping', 'list.gls_world', '" . '[{"title":"Dostava EU","code":"gls_world","data":{"price":"14","time":null,"short_description":null,"description":null},"geo_zone":"2","status":true,"sort_order":"0"}]' . "', 1, NOW(), NOW()),
              (null, 'tax', 'list', '" . '{"1":{"id":1,"geo_zone":"1","title":"PDV 5%","rate":"5","sort_order":"0","status":true},"2":{"id":2,"geo_zone":null,"title":"PDV 25%","rate":"25","sort_order":"1","status":true}}' . "', 1, NOW(), NOW())"
        );
    }
}
