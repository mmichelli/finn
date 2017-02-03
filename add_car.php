<?php

function add_car($finn_url, $apiKey, $orgId, $userAgent){

    global $link;

    //urlencode: "..q=" . urlencode("mjøsa");
    //location=0.20061 == Oslo
    //$apiUrl = "https://cache.api.finn.no/iad/search/realestate-homes?orgId=$orgId&page=1&rows=100&location=0.20061";
    //$apiUrl = "https://cache.api.finn.no/iad/ad/car-used-sale/48983373?orgId=".$orgId;

    $apiUrl = $finn_url."?orgId=".$orgId;

    //fetch using curl (or whatever you prefer, curl is good for setting custom headers)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_URL, utf8_decode($apiUrl));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("x-finn-apikey: $apiKey"));
    $rawData = curl_exec($ch);
    if(curl_error($ch)) {
        die("Fetch problem - add_car.php");
    }
    $curl_getinfo = curl_getinfo($ch);
    curl_close($ch);

    // CHECK IF FILE IS XML
    if($curl_getinfo['content_type'] != 'application/atom+xml; type=entry;charset=utf-8'){ print "ERROR:".$finn_url."\n"; return false; }

    $xmlData = new SimpleXMLElement($rawData);
    $ns = $xmlData->getNamespaces(true);


    $array = $xmlData;
    $data_array = array();

    foreach($array->children($ns['finn'])->adata->children($ns['finn'])->field as $finn_field){

        if($finn_field->attributes()->name == 'description'){

            $data_array['description'] = (string)$finn_field;

        }elseif($finn_field->attributes()->name == 'engine'){

            foreach($finn_field as $engine_field){
                $data_array[(string)$finn_field->attributes()->name.'_'.$engine_field->attributes()->name] = (string)$engine_field->attributes()->value;
            }

        }elseif($finn_field->attributes()->name == 'equipment'){

            $data_array[(string)$finn_field->attributes()->name] = array();

            foreach($finn_field as $finn_value) $data_array[(string)$finn_field->attributes()->name][] = (string)$finn_value;

        }else{

            $data_array[(string)$finn_field->attributes()->name] = (string)$finn_field->attributes()->value;

        }

    }

    //print_r($data_array);

    /* category */
    $category_array = array();
    foreach($array->category as $finn_field){
        $category_array[(string)$finn_field->attributes()->label] = (string)$finn_field->attributes()->term;
    }
    //print_r($category_array);


    /* IMAGES */
    $images_array = array();
    foreach($array->children($ns['media']) as $finn_field){
        $images_array[] = (string)$finn_field->attributes()->url;
    }
    //print_r($images_array);


    /* PRICES */
    $prices_array = array();
    foreach($array->children($ns['finn'])->adata->children($ns['finn'])->price as $finn_field){
        if($finn_field->attributes()->name == 'main'){
            $prices_array[(string)$finn_field->attributes()->name] = (string)$finn_field->attributes()->value;
            $prices_array['road_tax_included'] = (string)$finn_field->children($ns['finn'])->field->attributes()->value;
        }else{
            $prices_array[(string)$finn_field->attributes()->name] = (string)$finn_field->attributes()->value;
        }
    }
    //print_r($prices_array);

    /* CONTACT INFO */

    foreach($array->children($ns['finn'])->contact->children($ns['finn']) as $finn_field){
        if($finn_field->attributes()->type == 'work'){
            $contactphone = $finn_field;
        }
        if($finn_field->attributes()->type == 'mobile'){
            $contactmobile = $finn_field;
        }
        if($finn_field->attributes()->type == 'fax'){
            $contactfax = $finn_field;
        }
    }

    $contactname = $array->children($ns['finn'])->contact->children()->name;
    $contactemail = $array->children($ns['finn'])->contact->children()->email;
    //adresse:

    foreach($array->children($ns['finn'])->location->children($ns['finn']) as $finn_field){
        /*if($finn_field->attributes()->type == 'work'){
            $contactphone = $finn_field;
        }
        if($finn_field->attributes()->type == 'mobile'){
            $contactmobile = $finn_field;
        }
        if($finn_field->attributes()->type == 'fax'){
            $contactfax = $finn_field;
        }*/
        //pre($finn_field);

    }

    $address = $array->children($ns['finn'])->location->children($ns['finn'])->address;
    $postcode = $array->children($ns['finn'])->location->children($ns['finn'])->{'postal-code'}[0];
    $city =  $array->children($ns['finn'])->location->children($ns['finn'])->city;
    $country =  $array->children($ns['finn'])->location->children($ns['finn'])->country;


    /* / */

    /* OTHER */

    $finn_id = $array->children($ns['dc'])->identifier;
    $description =  str_replace("<br/>", "\n", $data_array['description']);
    $car_salesform = $data_array['sales_form'];
    $car_location = $array->children($ns['finn'])->location->city; //$data_array['car_location'];
    $mileage = $data_array['mileage'];
    $year_model = $data_array['year'];
    $body_type = $data_array['body_type'];
    $registration_first = isset($data_array['first_registration'])?$data_array['first_registration']: '';
    $engine_volume = isset($data_array['engine_volume'])?$data_array['engine_volume']: '';
    $engine_effect = isset($data_array['engine_effect'])?$data_array['engine_effect']: '';
    $engine_fuel = isset($data_array['engine_fuel'])?$data_array['engine_fuel']: '';
    $transmission = isset($data_array['transmission'])?$data_array['transmission']: '';
    $transmission_specification = isset($data_array['transmission_specification'])?$data_array['transmission_specification']: '';
    $wheel_drive = isset($data_array['wheel_drive'])?$data_array['wheel_drive']: '' ;
    $exterior_colour_main = isset($data_array['exterior_color'])?$data_array['exterior_color']: '';
    $exterior_colour = isset($data_array['exterior_color_description'])?$data_array['exterior_color_description']: '';
    $interior_colour = isset($data_array['interior_color'])?$data_array['interior_color']: '';
    $no_of_seats = isset($data_array['seats'])?$data_array['seats']:'';
    $no_of_doors = isset($data_array['doors'])?$data_array['doors']:'';
    $no_of_owners = isset($data_array['owners'])?$data_array['owners']:'';
    $registration_class = isset($data_array['registration_class'])?$data_array['registration_class']:'';
    $make =  isset($data_array['make'])?$data_array['make']:'';

    $main_image = $images_array[0];
    $main_image = str_replace('/mmo/', '/dynamic/1600w/', $main_image);

    unset($images);
    $images = '';
    foreach($images_array as $image){

        $images .= str_replace('/mmo/', '/dynamic/1600w/', $image)." ";

    }

    $shop = $array->author->name;

    $disposed = $category_array['Solgt'];
    if(isset($prices_array['main']))
        $motor_price_total = round($prices_array['main']);
    else
        $motor_price_total = 0;

    if(isset($prices_array['road_tax_included']))
        $motor_priceroadtax_included = ($prices_array['road_tax_included'] == 'true' ? 'Ja' : 'Nei');
    else
        $motor_priceroadtax_included = 'Nei';

    if(isset($prices_array['net']))
        $motor_pricereregistration_exemption = round($prices_array['net']);
    else
        $motor_pricereregistration_exemption = 0;

    if(isset($prices_array['registration_tax']))
        $motor_priceregistration = round($prices_array['registration_tax']);
    else
        $motor_priceregistration = 0;

    if(isset($prices_array['lease_price_initial']))
        $lease_price_initial = round($prices_array['lease_price_initial']);
    else
        $lease_price_initial = 0;

    if(isset($prices_array['lease_price_monthly']))
        $lease_price_monthly = round($prices_array['lease_price_monthly']);
    else
        $lease_price_monthly = 0;

    //Utstyr:
    unset($equipment);


    $equipment = '';
    if(isset($data_array['equipment']) && is_array($data_array['equipment'])){
        foreach($data_array['equipment'] as $equipment_name){
            $equipment .= $equipment_name."<br />";
        }
    }

    // Update cars_list
    mysqli_query($link, "UPDATE car_list SET transmission = '".$transmission."', registration_class = '".$registration_class."', body_type = '".$body_type."' WHERE finn_id = '".$finn_id."'");


    //print $equipment;

    mysqli_query($link, "INSERT INTO `cars` (
        `finn_id` ,
        `description` ,
        `car_salesform` ,
        `car_location` ,
        `make` ,
        `mileage` ,
        `year_model` ,
        `body_type` ,
        `registration_first` ,
        `engine_volume` ,
        `engine_effect` ,
        `engine_fuel` ,
        `transmission` ,
        `wheel_drive` ,
        `exterior_colour_main` ,
        `exterior_colour` ,
        `interior_colour` ,
        `no_of_seats` ,
        `no_of_doors` ,
        `no_of_owners` ,
        `main_image` ,
        `images` ,
        `contactname` ,
        `contactphone` ,
        `contactfax` ,
        `contactmobile` ,
        `contactemail` ,
        `address` ,
        `postcode` ,
        `city` ,
        `country` ,
        `disposed` ,
        `motor_price_total` ,
        `motor_priceroadtax_included` ,
        `motor_pricereregistration_exemption` ,
        `motor_priceregistration` ,
        `lease_price_monthly` ,
        `lease_price_initial` ,
        `equipments`,
        `registration_class`,
        `shop`,
        `shop_id`
        )
        VALUES (
        '".$finn_id."',
        '".htmlspecialchars($description, ENT_QUOTES)."',
        '".$car_salesform."',
        '".$car_location."',
        '".$make."',
        '".$mileage."',
        '".$year_model."',
        '".$body_type."',
        '".$registration_first."',
        '".$engine_volume."',
        '".$engine_effect."',
        '".$engine_fuel."',
        '".$transmission."',
        '".$wheel_drive."',
        '".$exterior_colour_main."',
        '".$exterior_colour."',
        '".$interior_colour."',
        '".$no_of_seats."',
        '".$no_of_doors."',
        '".$no_of_owners."',
        '".$main_image."',
        '".$images."',
        '".$contactname."',
        '".$contactphone."',
        '".(isset($contactfax)?$contactfax:'')."',
        '".(isset($contactmobile)?$contactmobile:'')."',
        '".$contactemail."',
        '".$address."',
        '".$postcode."',
        '".$city."',
        '".$country."',
        '".$disposed."',
        '".$motor_price_total."',
        '".$motor_priceroadtax_included."',
        '".$motor_pricereregistration_exemption."',
        '".$motor_priceregistration."',
        '".$lease_price_monthly."',
        '".$lease_price_initial."',
        '".$equipment."',
        '".$registration_class."',
        '".$shop."',
        '".$orgId."'
        )")or die(mysqli_error($link)." - adding car");


    //pre($array); die();

    echo (' | Car:' . $finn_id .' - ' .$car_location.' - ' . $make);
    return true;
}