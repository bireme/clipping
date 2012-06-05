<?php

$hostname = "localhost";
$username = "";
$password = "";
$database = "saudepublica";

include_once(dirname(__FILE__) . '/data.inc.php');

mysql_connect($hostname, $username, $password);
mysql_select_db($database);


$sql = "SELECT idnews, title, news FROM news LIMIT 10";
$query = mysql_query($sql) or die(mysql_error());

$default_fields = array(
	'wp:author' => 'admin',
	'wp:status' => 'draft',
	'wp:post_type' => 'post',
	'wp:post_parent' => 0,
);

$items = array();
while($item = mysql_fetch_array($query)) {
	$tmp = array();
	foreach($item as $key => $value) {

		$value = utf8_encode($value);
		
		if(!is_numeric($key)) {

			switch($key) {
				case 'idnews': $key = 'wp:post_id'; break;
				case 'news': $key = 'content:encoded'; break;
				case 'title': $key = 'title'; break;
			}

			$tmp[$key] = $value;
		}
	}
	$items[$item['idnews']] = $tmp;
}

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;
$dom->preserveWhiteSpace = false;

$root = $dom->createElement('rss');

// rss version
$attr = $dom->createAttribute('version');
$attr->value = '2.0';
$root->appendChild($attr);

// WP attrs
$attr = $dom->createAttribute('xmlns:excerpt');
$attr->value = 'http://wordpress.org/export/1.1/excerpt/';
$root->appendChild($attr);

$attr = $dom->createAttribute('xmlns:content');
$attr->value = "http://purl.org/rss/1.0/modules/content/";
$root->appendChild($attr);

$attr = $dom->createAttribute('xmlns:wfw');
$attr->value = "http://wellformedweb.org/CommentAPI/";
$root->appendChild($attr);

$attr = $dom->createAttribute('xmlns:dc');
$attr->value = "http://purl.org/dc/elements/1.1/";
$root->appendChild($attr);

$attr = $dom->createAttribute('xmlns:wp');
$attr->value = "http://wordpress.org/export/1.1/";
$root->appendChild($attr);
		
$channel = $dom->createElement('channel');

// rss header
$header_items = array('link' => '', 'title' => 'BVS-Site Export', 'description' => '', 'language' => 'pt', 'wp:wxr_version' => '1.1', 'generator' => 'http://bireme.org');
foreach($header_items as $key => $value) {
	$key = $dom->createElement("$key", $value);
	$channel->appendChild($key);
}

$author = $dom->createElement('wp:author');

foreach(array('wp:author_login' => 'importer', 'wp:author_email' => 'importer@bvs.com') as $key => $value) {
	$item = $dom->createElement("$key", $value);
	$author->appendChild($item);
}

$channel->appendChild($author);

// rss content
$count = -1;
foreach($items as $bvs_item) {

	//$count++; if($count < 100) continue;
	
	$item = $dom->createElement('item');

	foreach($bvs_item as $key => $value) {
		
		// itens que sao cDATA
		if(in_array($key, array('content_encoded', 'title'))) {
			
			$field = $dom->createElement("$key");
			$cdata = $dom->createCDATASection(trim($value));
			$field->appendChild($cdata);
			
		} else {

			$field = $dom->createElement("$key", "$value");

		}

		$item->appendChild($field);

		foreach($default_fields as $key => $value) {
			$field = $dom->createElement("$key", "$value");
			$item->appendChild($field);
		}
	}

	$item = $channel->appendChild($item);


}

$root->appendChild($channel);
$dom->appendChild($root);

if(!isset($_REQUEST['debug'])) {
	
	header("Content-Type: text/xml");
	$output = str_replace("<item/>", "", $dom->saveXML());
	$output = str_replace("content>", "content:encoded>", $output);

	print $output;
	
}

?>
