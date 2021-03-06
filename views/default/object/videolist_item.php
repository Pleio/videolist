<?php
/**
 * Videolist item renderer.
 *
 * @package ElggVideolist
 */

$full = elgg_extract('full_view', $vars, FALSE);
$entity = elgg_extract('entity', $vars, FALSE);

if (!$entity) {
	return TRUE;
}

$owner = $entity->getOwnerEntity();
$container = $entity->getContainerEntity();
$categories = elgg_view('output/categories', $vars);
$excerpt = elgg_get_excerpt($entity->description);

$body = elgg_view('output/longtext', array('value' => $entity->description));

$owner_link = elgg_view('output/url', array(
	'href' => "videolist/owner/$owner->username",
	'text' => $owner->name,
));
$author_text = elgg_echo('byline', array($owner_link));

$entity_icon = elgg_view_entity_icon($entity, 'medium');
$owner_icon = elgg_view_entity_icon($owner, 'small');

$tags = elgg_view('output/tags', array('tags' => $entity->tags));
$date = elgg_view_friendly_time($entity->time_created);

$comments_count = $entity->countComments();
//only display if there are commments
if ($comments_count != 0) {
	$text = elgg_echo("comments") . " ($comments_count)";
	$comments_link = elgg_view('output/url', array(
		'href' => $entity->getURL() . '#videolist-item-comments',
		'text' => $text,
	));
} else {
	$comments_link = '';
}

$metadata = elgg_view_menu('entity', array(
	'entity' => $vars['entity'],
	'handler' => 'videolist',
	'sort_by' => 'priority',
	'class' => 'elgg-menu-hz',
));

$subtitle = "$author_text $date $categories $comments_link";

// do not show the metadata and controls in widget view
if (elgg_in_context('widgets')) {
	$metadata = '';
	$excerpt = '';
}

if ($full && !elgg_in_context('gallery')) {

    $dimensions = elgg_get_config('videolist_dimensions');
	// allow altering dimensions
	$params = array(
		'entity' => $entity,
		'default_dimensions' => $dimensions,
	);
	$dimensions = elgg_trigger_plugin_hook('videolist:filter_dimensions', $entity->videotype, $params, $dimensions);
	
	$content = elgg_view("videolist/watch/{$entity->videotype}", array(
		'entity' => $entity,
		'width' => '100%',
		'height' => $dimensions['height'],
	));

	$params = array(
		'entity' => $entity,
		'title' => false,
		'metadata' => $metadata,
		'subtitle' => $subtitle,
		'tags' => $tags,
	);
	$params = $params + $vars;
	$list_body = elgg_view('object/elements/summary', $params);

	if (elgg_is_xhr()) {
		echo $content;
	} else {
		$content = "<div class=\"videolist-watch\">$content</div>";
		echo elgg_view('object/elements/full', array(
				'summary' => $list_body,
				'icon' => $owner_icon,
				'body' => $content . $body,
		));		
	}

} elseif (elgg_in_context('gallery')) {
	echo '<div class="videolist-gallery-item">';
	$content = elgg_view('output/url', array(
		'text' => elgg_get_excerpt($entity->title, 25),
		'href' => $entity->getURL(),
	));
	$content .= "<p class='subtitle'>$owner_link $date</p>";
	echo elgg_view_image_block($entity_icon, $content);
	echo '</div>';
} else {
	// brief view

	$params = array(
		'entity' => $entity,
		'metadata' => $metadata,
		'subtitle' => $subtitle,
		'tags' => $tags,
		'content' => $excerpt,
	);
	$params = $params + $vars;
	$list_body = elgg_view('object/elements/summary', $params);

	echo elgg_view_image_block($entity_icon, $list_body);
}
