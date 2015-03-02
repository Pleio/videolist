<?php
/**
 * Elgg video list widget
 *
 * @package ElggVideolist
 */

$widget = elgg_extract("entity", $vars);
$owner = $widget->getOwnerEntity();

$num = (int) $widget->videos_num;
if($num < 1){
	$num = 4;
}

$options = array(
	'type' => 'object',
	'subtype' => 'videolist_item',
	'limit' => $num,
	'full_view' => FALSE,
	'pagination' => FALSE,
);

if (!$owner instanceof ElggSite) {
	$options['container_guid'] = $widget->getOwnerGUID();
}

if ($content = elgg_list_entities($options)) {
	echo $content;
	
	$widget_owner = $widget->getOwnerEntity();
	if(elgg_instanceof($widget_owner, "group")){
		$url = "videolist/group/" . $widget_owner->getGUID() . "/all";
	} else {
		$url = "videolist/owner/" . $widget_owner->username;
	}
	
	$more_link = elgg_view('output/url', array(
		'href' => $url,
		'text' => elgg_echo('videolist_item:more'),
		'is_trusted' => true,
	));
	
	echo "<span class=\"elgg-widget-more\">$more_link</span>";
} else {
	echo elgg_echo('videolist_item:none');
}
