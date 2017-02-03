<?php
error_reporting(E_ALL);


function pre($var){
	print "<pre>";
	print_r($var);
	print "</pre>";
}

include("add_car.php");

// Connecting, selecting database
$database = "wp_autostrada";
$username = "autostrada";
$password = "r9BCeBVxcd!y";
$address = "ce193f54a5ac2e0aa3ce86ec36d5dc2464462148.rackspaceclouddb.com";

$link = mysqli_connect($address,$username,$password) or die('Could not connect: ' . mysqli_error($link));
mysqli_select_db($link, $database) or die('Could not select database');


function finn_curl_fetch($apikey, $orgId, $user_agent){
	
	$apiurl = "https://cache.api.finn.no/iad/search/car-norway?orgId=".$orgId."&rows=500";
	
	// Fetch using curl 
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
	curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
	curl_setopt($ch, CURLOPT_URL, utf8_decode($apiurl)); 
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("x-finn-apikey: $apikey"));
	$rawData=curl_exec($ch);
	//echo $rawData;
	if (curl_error($ch)){
		die("Fetch problem - cars");
	}

	// Parse the xml and get namespaces (needed later to extract attributes and values)
	$xmlData = new SimpleXMLElement($rawData);
	$ns = $xmlData->getNamespaces(true);

	curl_close($ch);

	

	$entries = array();
	foreach ($xmlData->entry as $entry) {
		array_push($entries, $entry);
	}
	
	return array('entries' => $entries, 'ns' => $ns);
	
}
//
// Get navigation links into assoc arr
//
/*$links = array();
foreach ($xmlData->link as $link) {
	$rel = $link->attributes()->rel;
	$ref = $link->attributes()->href;
	$links["$rel"] = "$ref";
}*/

/* autostrada */
$autostrada_apikey = "8zwfOOgcDzApVIcL";
$autostrada_orgId = "637401754";
$autostrada_user_agent = "www.autostrada.com";
$entries = finn_curl_fetch($autostrada_apikey, $autostrada_orgId, $autostrada_user_agent)['entries'];
/* / */

/* billa */
$billa_orgId = '1623002272';
$billa_apikey = "oByTfCbsdpa1q5kM";
$billa_user_agent = "www.billa.no";
$entries = array_merge($entries, finn_curl_fetch($billa_apikey, $billa_orgId, $billa_user_agent)['entries']);
/* / */

/* jorkjend */
$jorkjend_orgId = "1930791876";
$jorkjend_apikey = "Nia0e3Xowsvec1wM";
$jorkjend_user_agent = "www.jorkjend.com";
$entries = array_merge($entries, finn_curl_fetch($jorkjend_apikey, $jorkjend_orgId, $jorkjend_user_agent)['entries']);
/* / */

/* jorkjend porsgrunn */
/*$jorkjend_porsgrunn_orgId = "506150697";
$jorkjend_porsgrunn_apikey = "L0U2q5lmYyxRyR3s";
$jorkjend_porsgrunn_user_agent = "www.jorkjend.com";
$entries = array_merge($entries, finn_curl_fetch($jorkjend_porsgrunn_apikey, $jorkjend_porsgrunn_orgId, $jorkjend_porsgrunn_user_agent)['entries']);*/
/* / */

//pre($entries);

$ns = finn_curl_fetch($autostrada_apikey, $autostrada_orgId, $autostrada_user_agent)['ns'];

mysqli_query($link, "TRUNCATE TABLE `car_list`");

//print_r($xmlData);

foreach ($entries as $entry) {
	//pre($entry);
	$finn_url = (string)$entry->link->attributes()->href;
	
	// heading
	$title = $entry->title;
	
	// adid
	$id = $entry->children($ns['dc'])->identifier;
	
	// location
	$city  = $entry->children($ns['finn'])->location->children($ns['finn'])->city;
	
	// image url
	unset($image);
	if ($entry->children($ns['media']) && $entry->children($ns['media'])->content->attributes()) {
		$image = $entry->children($ns['media'])->content->attributes()->url;
	}
	
	// price is more complex, as the API is utterly detailed with a dozen entries...
	$adata = $entry->children($ns['finn'])->adata;
	$price = 0;
	$lease_price_monthly = 0;
	$lease_price_initial = 0;
	
	foreach($adata->children($ns['finn'])->price as $myPrice) {
		if (isset($myPrice->attributes()->name) && $myPrice->attributes()->name == 'main') {
			$price = $myPrice->attributes()->value;
		}
		if (isset($myPrice->attributes()->name) && $myPrice->attributes()->name == 'lease_price_monthly') {
			$lease_price_monthly = $myPrice->attributes()->value;
		}
		if (isset($myPrice->attributes()->name) && $myPrice->attributes()->name == 'lease_price_initial') {
			$lease_price_initial = $myPrice->attributes()->value;
		}
	}
	
	foreach($entry->children($ns['finn'])->adata->children($ns['finn'])->field as $field){
		
		if($field->attributes()->name == 'mileage') $km = $field->attributes()->value;
		
		if($field->attributes()->name == 'year') $year = $field->attributes()->value;
		
		if($field->attributes()->name == 'make') $make = $field->attributes()->value;
		
		if($field->attributes()->name == 'model') $model = $field->attributes()->value;
		
		if($field->attributes()->name == 'model_spec') $model_spec = $field->attributes()->value;
		
	}
	
	$location = $entry->children($ns['finn'])->location->children($ns['finn'])->city;
	
	
	foreach($entry->category as $category){
		if($category->attributes()->scheme == 'urn:finn:ad:type') $used = ($category->attributes()->term == 'car-used-sale' ? 'yes' : 'no');
	}
	
	$author_uri = $entry->author->uri;
    $author_uri = explode('/',$author_uri);
    $shop_id = end($author_uri);
	
	
	
	// Sold
	$sold = ($entry->category[1]->attributes()->term == 'true' ? "yes" : "no");
	
	mysqli_query($link, "INSERT INTO car_list
		(`finn_id`, `heading`, `year_model`, `price`, `lease_price_monthly`, `lease_price_initial`, `disposed`, `mileage`, `location`, `make`, `model`, `model_spec`, `used`, `image`, `finn_url`, `shop`, `shop_id`) VALUES 
		('".$id."', '".$title."', '".$year."', '".$price."', '".$lease_price_monthly."', '".$lease_price_initial."', '".$sold."', '".$km."', '".$location."', '".$make."', '".$model."', '".$model_spec."', '".$used."', '".$image."', '".$finn_url."', '".$entry->author->name."', '".$shop_id."')") or die(mysqli_error($link));
	
	//die();
	
}

//ADD CARS
$query = mysqli_query($link, "SELECT * FROM car_list")or die(mysqli_error($link));

while($get = mysqli_fetch_array($query)){
	
	$query_car = mysqli_query($link, "SELECT id FROM cars WHERE finn_id=".$get['finn_id']);
	$get_car = mysqli_fetch_array($query_car);

	
	if($get['shop_id'] == "637401754"){
		$add_car = add_car($get['finn_url'], $autostrada_apikey, $autostrada_orgId, $autostrada_user_agent);
	}elseif($get['shop_id'] == "1623002272"){
		$add_car = add_car($get['finn_url'], $billa_apikey, $billa_orgId, $billa_user_agent);
	}elseif($get['shop_id'] == "1930791876"){
		$add_car = add_car($get['finn_url'], $jorkjend_apikey, $jorkjend_orgId, $jorkjend_user_agent);
	}
	// JORKJEND PORSGRUNN MANGLER HER !
	
	if($add_car && $get_car['id']) mysqli_query($link, "DELETE FROM cars WHERE id=".$get_car['id'])or die(mysqli_error($link)." - deleteing old car");
	
	//break;
	
}
echo ("\n------updated -------");

mysqli_close($link);

?>