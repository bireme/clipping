<?php

$hostname = "localhost";
$username = "";
$password = "";
$database = "saudepublica";

include_once(dirname(__FILE__) . '/data.inc.php');
require_once(dirname(__FILE__) . '/functions.php');

mysql_connect($hostname, $username, $password);
mysql_select_db($database);

$terms = array();

// midia
$sql = "SELECT idcategory, category, description FROM categories";
$query = mysql_query($sql) or die(mysql_error());

$terms['midia'] = array();
while($item = mysql_fetch_array($query)) {
	$id = $item['idcategory'];
	$terms['midia'][$id]['wp:term_taxonomy'] = 'midia';
	$terms['midia'][$id]['wp:term_id'] = utf8_encode($item['idcategory']);
	$terms['midia'][$id]['wp:term_name'] = utf8_encode($item['category']);
	$terms['midia'][$id]['wp:term_description'] = utf8_encode($item['description']);
}

// veiculos
$sql = "SELECT idparent_category, title FROM parent_categories";
$query = mysql_query($sql) or die(mysql_error());

$terms['veiculo'] = array();
while($item = mysql_fetch_array($query)) {
	$id = $item['idparent_category'];
	$terms['veiculo'][$id]['wp:term_taxonomy'] = 'veiculo';
	$terms['veiculo'][$id]['wp:term_id'] = $id;
	$terms['veiculo'][$id]['wp:term_name'] = utf8_encode($item['title']);
}

$sql = "SELECT sourcenews, page, author, publicationdate, headline, idnews, idparent_category, idcategory, title, news FROM news";
$sql .= " LIMIT 10";
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
				case 'idcategory': $key = 'midia'; break;
				case 'idparent_category': $key = 'veiculo'; break;
				case 'headline': $key = 'excerpt:encoded'; break;
				case 'publicationdate': $key = 'data-de-publicacao'; break;
				case 'author': $key = 'autor'; break;
				case 'page': $key = 'paginas'; break;
				case 'sourcenews': $key = 'fonte'; break;
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

// author
$author = $dom->createElement('wp:author');

foreach(array('wp:author_login' => 'importer', 'wp:author_email' => 'importer@bvs.com') as $key => $value) {
	$item = $dom->createElement("$key", $value);
	$author->appendChild($item);
}

$channel->appendChild($author);

// terms
foreach($terms as $term_type) {
	foreach($term_type as $term) {

		$item = $dom->createElement('wp:term');

		foreach($term as $key => $value) {
				
			$field = $dom->createElement("$key");
			$cdata = $dom->createCDATASection((trim($value)));
			$field->appendChild($cdata);
			
			$item->appendChild($field);
		}		

		$item = $channel->appendChild($item);
	}
}
	
// rss content
foreach($items as $bvs_item) {
	
	$item = $dom->createElement('item');
	

	foreach($bvs_item as $key => $value) {
			
		// if field is some term
		if(in_array($key, array('midia', 'veiculo'))) {

			$field = $dom->createElement("category");
			$value = $terms[$key][$value]['wp:term_name'];
			
			$domAttribute = $dom->createAttribute('domain');
			$domAttribute->value = $key;
			$field->appendChild($domAttribute);

			$domAttribute = $dom->createAttribute('nicename');
			$domAttribute->value = slugify($value);
			$field->appendChild($domAttribute);

		} else {
			
			$field = $dom->createElement("$key");
		}

		$cdata = $dom->createCDATASection(trim($value));
		$field->appendChild($cdata);
		$item->appendChild($field);

		// if is additional fields
		if(in_array($key, array('fonte', 'paginas', 'autor', 'data-de-publicacao'))) {

			$postmeta = $dom->createElement('wp:postmeta');
			
			$field = $dom->createElement("wp:meta_key");	
			$cdata = $dom->createCDATASection(trim($key));
			$field->appendChild($cdata);
			$postmeta->appendChild($field);

			$field = $dom->createElement("wp:meta_value");	
			$cdata = $dom->createCDATASection(trim($value));
			$field->appendChild($cdata);
			$postmeta->appendChild($field);
			
			$postmeta = $item->appendChild($postmeta);
		}

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
