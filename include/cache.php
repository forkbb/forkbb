<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;


//
// Generate quick jump cache PHP scripts
//
function generate_quickjump_cache($group_id = false)
{
	global $container, $lang_common;

    $db = $container->get('DB');

	$groups = array();

	// If a group_id was supplied, we generate the quick jump cache for that group only
	if ($group_id !== false)
	{
		// Is this group even allowed to read forums?
		$result = $db->query('SELECT g_read_board FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to fetch user group read permission', __FILE__, __LINE__, $db->error());
		$read_board = $db->result($result);

		$groups[$group_id] = $read_board;
	}
	else
	{
		// A group_id was not supplied, so we generate the quick jump cache for all groups
		$result = $db->query('SELECT g_id, g_read_board FROM '.$db->prefix.'groups') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

		while ($row = $db->fetch_row($result))
			$groups[$row[0]] = $row[1];
	}

	// MOD subforums - Visman
	function generate_quickjump_sf_list($sf_array_tree, $id = 0, $space = '')
	{
		if (empty($sf_array_tree[$id])) return '';

		$output = '';
		if (!$id)
			$output .= "\t\t\t\t".'<form id="qjump" method="get" action="viewforum.php">'."\n\t\t\t\t\t".'<div><label><span><?php echo $lang_common[\'Jump to\'] ?>'.'<br /></span>'."\n\t\t\t\t\t".'<select name="id" onchange="window.location=(\'viewforum.php?id=\'+this.options[this.selectedIndex].value)">'."\n";

		$cur_category = 0;
		foreach ($sf_array_tree[$id] as $cur_forum)
		{
			if ($id == 0 && $cur_forum['cid'] != $cur_category) // A new category since last iteration?
			{
				if ($cur_category)
					$output .= "\t\t\t\t\t\t".'</optgroup>'."\n";

				$output .= "\t\t\t\t\t\t".'<optgroup label="'.pun_htmlspecialchars($cur_forum['cat_name']).'">'."\n";
				$cur_category = $cur_forum['cid'];
			}

			$redirect_tag = ($cur_forum['redirect_url'] != '') ? ' &gt;&gt;&gt;' : '';
			$output .= "\t\t\t\t\t\t\t".'<option value="'.$cur_forum['fid'].'"<?php echo ($forum_id == '.$cur_forum['fid'].') ? \' selected="selected"\' : \'\' ?>>'.$space.pun_htmlspecialchars($cur_forum['forum_name']).$redirect_tag.'</option>'."\n";

			$output .= generate_quickjump_sf_list($sf_array_tree, $cur_forum['fid'], $space.'&#160;&#160;&#160;');
		}

		if (!$id)
			$output .= "\t\t\t\t\t\t".'</optgroup>'."\n\t\t\t\t\t".'</select></label>'."\n\t\t\t\t\t".'<input type="submit" value="<?php echo $lang_common[\'Go\'] ?>" accesskey="g" />'."\n\t\t\t\t\t".'</div>'."\n\t\t\t\t".'</form>'."\n";

		return $output;
	}

	// Loop through the groups in $groups and output the cache for each of them
	foreach ($groups as $group_id => $read_board)
	{
		// Output quick jump as PHP code
		$output = '<?php'."\n\n".'if (!defined(\'PUN\')) exit;'."\n".'define(\'PUN_QJ_LOADED\', 1);'."\n".'$forum_id = isset($forum_id) ? $forum_id : 0;'."\n\n".'?>';

		if ($read_board == '1')
		{
			// Load cached subforums - Visman
			if (file_exists($container->getParameter('DIR_CACHE') . 'cache_subforums_'.$group_id.'.php'))
				include $container->getParameter('DIR_CACHE') . 'cache_subforums_'.$group_id.'.php';
			else
			{
				generate_subforums_cache($group_id);
				require $container->getParameter('DIR_CACHE') . 'cache_subforums_'.$group_id.'.php';
			}

			$output .= generate_quickjump_sf_list($sf_array_tree);

		}

		fluxbb_write_cache_file('cache_quickjump_'.$group_id.'.php', $output);
	}
}


//
// Safely write out a cache file.
//
function fluxbb_write_cache_file($file, $content)
{
    global $container;

	$fh = @fopen($container->getParameter('DIR_CACHE') . $file, 'wb');
	if (!$fh)
		error('Unable to write cache file '.pun_htmlspecialchars($file).' to cache directory. Please make sure PHP has write access to the directory \''.pun_htmlspecialchars($container->getParameter('DIR_CACHE')).'\'', __FILE__, __LINE__);

	flock($fh, LOCK_EX);
	ftruncate($fh, 0);

	fwrite($fh, $content);

	flock($fh, LOCK_UN);
	fclose($fh);

	fluxbb_invalidate_cached_file($container->getParameter('DIR_CACHE') . $file);
}


//
// Delete all feed caches
//
function clear_feed_cache()
{
    global $container;

	$d = dir($container->getParameter('DIR_CACHE'));
	while (($entry = $d->read()) !== false)
	{
		if (substr($entry, 0, 10) == 'cache_feed' && substr($entry, -4) == '.php')
		{
			@unlink($container->getParameter('DIR_CACHE') . $entry);
			fluxbb_invalidate_cached_file($container->getParameter('DIR_CACHE') . $entry);
		}
	}
	$d->close();
}


//
// Generate the subforums cache - Visman
//
function generate_subforums_desc(&$list, $tree, $node = 0)
{
	if (!empty($tree[$node]))
	{
		foreach ($tree[$node] as $forum_id => $forum)
		{
			$list[$forum_id] = $node ? array_merge(array($node), $list[$node]) : array();
			$list[$forum_id]['forum_name'] = $forum['forum_name'];
			generate_subforums_desc($list, $tree, $forum_id);
		}
	}
}

function generate_subforums_asc(&$list, $tree, $node = array(0))
{
	$list[$node[0]][] = $node[0];

	if (empty($tree[$node[0]])) return;
	foreach ($tree[$node[0]] as $forum_id => $forum)
	{
		$temp = array($forum_id);
		foreach ($node as $i)
		{
			$list[$i][] = $forum_id;
			$temp[] = $i;
		}
		generate_subforums_asc($list, $tree, $temp);
	}
}

function generate_subforums_cache($group_id = false)
{
	global $container;

    $db = $container->get('DB');

	$groups = array();

	// If a group_id was supplied, we generate the quick jump cache for that group only
	if ($group_id !== false)
	{
		// Is this group even allowed to read forums?
		$result = $db->query('SELECT g_read_board FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to fetch user group read permission', __FILE__, __LINE__, $db->error());
		$read_board = $db->result($result);

		$groups[$group_id] = $read_board;
	}
	else
	{
		// A group_id was not supplied, so we generate the quick jump cache for all groups
		$result = $db->query('SELECT g_id, g_read_board FROM '.$db->prefix.'groups') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

		while ($row = $db->fetch_row($result))
			$groups[$row[0]] = $row[1];
	}

	// Loop through the groups in $groups and output the cache for each of them
	foreach ($groups as $group_id => $read_board)
	{
		$str = '<?php'."\n\n";

		if ($read_board == '1')
		{
			$tree = $desc = $asc = array();

			$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url, f.parent_forum_id, f.disp_position FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$group_id.') WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

			// Generate array of forums/subforums for this group
			while ($f = $db->fetch_assoc($result))
				$tree[$f['parent_forum_id']][$f['fid']] = $f;

			generate_subforums_desc($desc, $tree);
			generate_subforums_asc($asc, $tree);
			$str.= '$sf_array_tree = '.var_export($tree, true).';'."\n\n".'$sf_array_desc = '.var_export($desc, true).';'."\n\n".'$sf_array_asc = '.var_export($asc, true).';';
		}
		else
			$str.= '$sf_array_tree = $sf_array_desc = $sf_array_asc = array();';

		fluxbb_write_cache_file('cache_subforums_'.$group_id.'.php', $str."\n\n".'?>');
	}
}


//
// Invalidate updated php files that are cached by an opcache
//
function fluxbb_invalidate_cached_file($file)
{
	if (function_exists('opcache_invalidate'))
		opcache_invalidate($file, true);
	elseif (function_exists('apc_delete_file'))
		@apc_delete_file($file);
}


define('FORUM_CACHE_FUNCTIONS_LOADED', true);
