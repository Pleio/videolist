<?php
/**
 * Elgg Video Plugin
 * This plugin allows users to create a library of videos
 *
 * @package Elgg
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
 * @author Prateek Choudhary <synapticfield@gmail.com>
 * @copyright Prateek Choudhary
 */

elgg_register_event_handler('init', 'system', 'videolist_init');

function videolist_init() {

	elgg_register_library('elgg:videolist', elgg_get_plugins_path() . 'videolist/lib/videolist.php');

	if (!class_exists('Videolist_PlatformInterface')) {
		// ./classes autoloading failed (pre 1.9)
		spl_autoload_register('videolist_load_class');
	}

	// add a site navigation item
	$item = new ElggMenuItem('videolist', elgg_echo('videolist'), 'videolist/all');
	elgg_register_menu_item('site', $item);

	// Extend system CSS with our own styles
	elgg_extend_view('css/elgg','videolist/css');

	// register the js
	$js = elgg_get_simplecache_url('js', 'videolist/videolist');
	elgg_register_simplecache_view('js/videolist/videolist');
	elgg_register_js('elgg.videolist', $js);

	$js = elgg_get_simplecache_url('js', 'videolist/json2');
	elgg_register_simplecache_view('js/videolist/json2');
	elgg_register_js('elgg.videolist.json2', $js);

	// Register a page handler, so we can have nice URLs
	elgg_register_page_handler('videolist', 'videolist_page_handler');

	// Language short codes must be of the form "videolist:key"
	// where key is the array key below
	elgg_set_config('videolist', array(
		'video_url' => 'url',
		'title' => 'text',
		'description' => 'longtext',
		'tags' => 'tags',
		'access_id' => 'access',
	));

	elgg_set_config('videolist_dimensions', array(
		'width'  => 600,
		'height' => 400,
	));

	// add to groups
	add_group_tool_option('videolist', elgg_echo('groups:enablevideolist'), true);
	elgg_extend_view('groups/tool_latest', 'videolist/group_module');

	//add a widget
	elgg_register_widget_type('videolist', elgg_echo('videolist'), elgg_echo('videolist:widget:description'), "profile,dashboard,groups", true);
	
	// Register granular notification for this type
	register_notification_object('object', 'videolist_item', elgg_echo('videolist:notification:subject'));
	elgg_register_plugin_hook_handler('notify:entity:message','object','videolist_notify_message');

	// Register entity type for search
	elgg_register_entity_type('object', 'videolist_item');

	// add a file link to owner blocks
	elgg_register_plugin_hook_handler('register', 'menu:owner_block', 'videolist_owner_block_menu');

	//register entity url handler
	elgg_register_entity_url_handler('object', 'videolist_item', 'videolist_url');
	elgg_register_plugin_hook_handler('entity:icon:url', 'object', 'videolist_icon_url_override');

	// register for embed
	elgg_register_plugin_hook_handler('embed_get_sections', 'all', 'videolist_embed_get_sections');
	elgg_register_plugin_hook_handler('embed_get_items', 'videolist', 'videolist_embed_get_items');

    // handle URLs without scheme
    elgg_register_plugin_hook_handler('videolist:preprocess', 'url', 'videolist_preprocess_url');

	// Register actions
	$actions_path = elgg_get_plugins_path() . "videolist/actions/videolist";
	elgg_register_action("videolist/add", "$actions_path/add.php");
	elgg_register_action("videolist/edit", "$actions_path/edit.php");
	elgg_register_action("videolist/delete", "$actions_path/delete.php");
	elgg_register_action("videolist/get_metadata_from_url", "$actions_path/get_metadata_from_url.php");

	elgg_register_event_handler('upgrade', 'system', 'videolist_run_upgrades');
}

/**
 * Dispatches blog pages.
 * URLs take the form of
 *  All videos:       videolist/all
 *  User's videos:    videolist/owner/<username>
 *  Friends' videos:  videolist/friends/<username>
 *  Video watch:      videolist/watch/<guid>/<title>
 *  Video browse:     videolist/browse
 *  New video:        videolist/add/<guid>
 *  Edit video:       videolist/edit/<guid>
 *  Group videos:     videolist/group/<guid>/all
 *
 * Title is ignored
 *
 * @param array $page
 * @return NULL
 */
function videolist_page_handler($page) {

	if (!isset($page[0])) {
		$page[0] = 'all';
	}

	$videolist_dir = elgg_get_plugins_path() . 'videolist/pages/videolist';

	$page_type = $page[0];
	switch ($page_type) {
		case 'owner':
			include "$videolist_dir/owner.php";
			break;
		case 'friends':
			include "$videolist_dir/friends.php";
			break;
		case 'watch':
			set_input('guid', $page[1]);
			include "$videolist_dir/watch.php";
			break;
		case 'add':
			include "$videolist_dir/add.php";
			break;
		case 'edit':
			set_input('guid', $page[1]);
			include "$videolist_dir/edit.php";
			break;
		case 'group':
			include "$videolist_dir/owner.php";
			break;
		case 'all':
		default:
			include "$videolist_dir/all.php";
			break;
	}
	return true;
}

/**
 * Add a menu item to the user ownerblock
 *
 * @param string $hook
 * @param string $type
 * @param array $return
 * @param array $params
 * @return array
 */
function videolist_owner_block_menu($hook, $type, $return, $params) {
	if (elgg_instanceof($params['entity'], 'user')) {
		$url = "videolist/owner/{$params['entity']->username}";
		$item = new ElggMenuItem('videolist', elgg_echo('videolist'), $url);
		$return[] = $item;
	} else {
		if ($params['entity']->videolist_enable != "no") {
			$url = "videolist/group/{$params['entity']->guid}/all";
			$item = new ElggMenuItem('videolist', elgg_echo('videolist:group'), $url);
			$return[] = $item;
		}
	}

	return $return;
}

/**
 * @param ElggObject $videolist_item
 * @return string
 */
function videolist_url($videolist_item) {
	$guid = $videolist_item->guid;
	$title = elgg_get_friendly_title($videolist_item->title);
	return elgg_get_site_url() . "videolist/watch/$guid/$title";
}

/**
 * Modify the message send out on a new video upload
 *
 * @param string $hook
 * @param string $entity_type
 * @param array $returnvalue
 * @param array $params
 * @return string
 */
function videolist_notify_message($hook, $entity_type, $returnvalue, $params) {

	if (!empty($params) && is_array($params)) {
		$entity = elgg_extract("entity", $params);

		if (!empty($entity) && elgg_instanceof($entity, "object", "videolist_item")) {
			$owner = $entity->getOwnerEntity();

			return elgg_echo("videolist:notification", array(
				$owner->name,
				$entity->title,
				elgg_get_excerpt($entity->description),
				$entity->getURL()
			));
		}
	}
}


/**
 * Register videolist as an embed type.
 *
 * @param string $hook
 * @param string $type
 * @param array $value
 * @param array $params
 * @return array
 */
function videolist_embed_get_sections($hook, $type, $value, $params) {
	$value['videolist'] = array(
		'name' => elgg_echo('videolist'),
		'layout' => 'list',
		'icon_size' => 'medium',
	);

	return $value;
}

/**
 * Return a list of videos for embedding
 *
 * @param string $hook
 * @param string $type
 * @param array $value
 * @param array $params
 * @return array
 */
function videolist_embed_get_items($hook, $type, $value, $params) {
	$options = array(
		'owner_guid' => elgg_get_logged_in_user_guid(),
		'type_subtype_pair' => array('object' => 'videolist_item'),
		'count' => true,
	);

	$count = elgg_get_entities($options);
	$value['count'] += $count;

	unset($options['count']);
	$options['offset'] = $params['offset'];
	$options['limit'] = $params['limit'];

	$items = elgg_get_entities($options);

	$value['items'] = array_merge($items, $value['items']);

	return $value;
}

/**
 * Override the default entity icon for videoslist items
 *
 * @param string $hook
 * @param string $type
 * @param string $returnvalue
 * @param array $params
 * @return string Relative URL
 */
function videolist_icon_url_override($hook, $type, $returnvalue, $params) {
	$videolist_item = $params['entity'];
    /* @var ElggObject $videolist_item */

    $size = $params['size'];

    if ($videolist_item->getSubtype() != 'videolist_item') {
		return $returnvalue;
	}

	// tiny thumbnails are too small to be useful, so give a generic video icon
	if ($size != 'tiny' && !empty($videolist_item->thumbnail)) {
		return elgg_get_site_url() . "mod/videolist/thumbnail.php?guid=" . $videolist_item->guid;
	}

	if (in_array($size, array('tiny', 'small', 'medium'))) {
		return "mod/videolist/graphics/videolist_icon_{$size}.png";
	}
}

/**
 * Prepend HTTP scheme if missing
 * @param string $hook
 * @param string $type
 * @param string $returnvalue
 * @param array $params
 * @return string
 */
function videolist_preprocess_url($hook, $type, $returnvalue, $params) {
    $parsed = parse_url($returnvalue);
    if (empty($parsed['host']) && ! empty($parsed['path']) && $parsed['path'][0] !== '/') {
        // user probably forgot scheme
        $returnvalue = 'http://' . $returnvalue;
    }
    return $returnvalue;
}

/**
 * Process upgrades for the videolist plugin
 */
function videolist_run_upgrades() {
	$path = elgg_get_plugins_path() . 'videolist/upgrades/';
	$files = elgg_get_upgrade_files($path);
	foreach ($files as $file) {
		include "$path{$file}";
	}
}

/**
 * @param string $class
 */
function videolist_load_class($class) {
	if (0 === strpos($class, 'Videolist_')) {
		$file = dirname(__FILE__) . '/classes/' . strtr($class, '_\\', '//') . '.php';
		is_file($file) && (require $file);
	}
}

/**
 * Fetch and validate XML from URL
 * @param string $url
 * @return SimpleXMLElement|false
 */
function videolist_fetch_xml($url) {
	if (!preg_match('~^https?\\://~', $url)) {
		return false;
	}
	@$buffer = file_get_contents($url);
	if (!$buffer) {
		return false;
	}
	$uie = libxml_use_internal_errors(true);
	$el = libxml_disable_entity_loader(true);
	try {
		$xml = new SimpleXMLElement($buffer);
	} catch (Exception $e) {
		$xml = false;
	}
	if (libxml_get_errors()) {
		$xml = false;
		libxml_clear_errors();
	}
	libxml_use_internal_errors($uie);
	libxml_disable_entity_loader($el);
	return $xml;
}
