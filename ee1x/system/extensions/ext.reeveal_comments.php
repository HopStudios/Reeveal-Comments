<?php

/*
==========================================================
	This software package is intended for use with 
	ExpressionEngine.	ExpressionEngine is Copyright © 
	2002-2009 EllisLab, Inc. 
	http://ellislab.com/
==========================================================
	THIS IS COPYRIGHTED SOFTWARE, All RIGHTS RESERVED.
	Written by: Justin Crawford
	Copyright (c) 2009 Hop Studios
	http://www.hopstudios.com/software/
--------------------------------------------------------
	Please do not distribute this software without written
	consent from the author.
==========================================================
	Files:
	- ext.reeveal_comments.php
----------------------------------------------------------
	Purpose: 
	- Puts a comments tab on entry edit screens
----------------------------------------------------------
	Notes: 
	- Must have "can_moderate_comments" privileges to use this tab
	- jQuery for the Control Panel extension is recommended
==========================================================
*/


if (! defined('EXT'))
{
	exit('Invalid file request');
}

class Reeveal_comments
{
	var $settings = array();
	var $name = "Reeveal Comments";
	var $version = '1.0.4'; 
	var $description = 'Reeveal Comments tab in Entry Editing Page';
	var $settings_exist = 'y';
	var $docs_url = 'http://www.hopstudios.com/software/reeveal_comments';

	// these are the hooks we'll register to be notified about
	var $hook_methods = array(
		"publish_form_new_tabs" 				=> "build_tab",
		"publish_form_new_tabs_block" 		=> "build_tab_content",
		"show_full_control_panel_start" 		=> "handle_request",
	);
	
	//----------------------------------------------------------------------
	// constructor
	//----------------------------------------------------------------------
	function Reeveal_comments($settings='')
	{
		if (! (isset($settings['length']) && is_numeric($settings['length'])))
		{
			$settings['length'] = 3;
		}
		if (! isset($settings['paid']))
		{
			$settings['paid'] = 'no';
		}
		$this->settings = $settings; 
	}

	//----------------------------------------------------------------------
	// settings
	//----------------------------------------------------------------------
	function settings()
	{
		$settings = array();
		$settings['paid'] = array('r', array('yes' => 'yes', 'no' => 'no'), 'no');
		$settings['length'] = '3';
		return $settings;
	}

	//----------------------------------------------------------------------
	// build tab
	//----------------------------------------------------------------------
	function build_tab($tabs, $weblog_id, $entry_id = '')
	{

		// note: $entry_id is always blank. (reported on bug forum.)
		global $IN, $EXT;
		$entry_id = $IN->GBL('entry_id');

		// get tabs returned by previous extensions, if any
		if($EXT->last_call !== false)
		{
			$tabs = $EXT->last_call;
		}

		// we only add this tab if we're editing an existing entry
		if ($entry_id != '')
		{
			// add our tab
			$tabs['reeveal_comments'] = 'Comments';
		}

		return $tabs;
	}

	//----------------------------------------------------------------------
	// build tab content
	//----------------------------------------------------------------------
	function build_tab_content($weblog_id)
	{
		// note: $entry_id is always blank. (reported on bug forum.)
		global $IN, $EXT;
		$entry_id = $IN->GBL('entry_id');

		$tab_content = "";

		// get content returned by previous extensions, if any
		if($EXT->last_call !== false)
		{
			$tab_content = $EXT->last_call;
		}

		// we only add this tab if we're editing an existing entry
		if ($entry_id != '')
		{
			// check for presence of JQuery
			if (isset($EXT->version_numbers['Cp_jquery']) === FALSE && empty($SESS->cache['scripts']['jquery']) === TRUE)
			{
				$tab_content .= '<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.1/jquery.min.js"></script>';
				$SESS->cache['scripts']['jquery']['1.2.6'] = TRUE;
			}

			// the URL of the comments tab
			$base_url = str_replace(AMP, '&', BASE) . "&C=reeveal_comments&entry_id=$entry_id";

			// the javascript:
			// 	- get comments, add them to tab, and bind their links to add'l functions.
			$tab_content .= <<<EOT
		<script>

			jQuery(document).ready(function(){
				var empty = true;

				function bind_links() {
					jQuery('.alt_status,.reeveal_page').click(function() {
						jQuery.get(this, function(data) {
							jQuery('#reeveal_comments_content').replaceWith('<div id="reeveal_comments_content" class="publishBox">' + data + '</div>');	
							bind_links();
							jQuery('#reeveal_comments_content .updated').css({'fontSize' : '18px'}); 
							jQuery('#reeveal_comments_content .updated').animate({'fontSize' : '12px'}, 500); 
						});
						return false;
					});
				}

				jQuery('#reeveal_comments').parent('a').click(function() {
					jQuery.get('$base_url', function(data) {
						if (empty) {
							jQuery('#reeveal_comments_content').append(data);	
							empty = false;

							bind_links();
						}
					});
				});	

			});
		</script>
EOT;

			// tab content containers -- will be filled by javascript
			$tab_content .= <<<EOT
		<div id="blockreeveal_comments" style="display: none; padding:0; margin:0;">
			<div class="publishTabWrapper">
				<div class="publishBox" id="reeveal_comments_content">
				</div>
			</div>
		</div>
EOT;
		}

		return $tab_content;
	}

	//----------------------------------------------------------------------
	// handle_request
	//	overrides the control panel display
	//----------------------------------------------------------------------
	function handle_request()
	{
		if ($this->is_reeveal_comments_request())
		{
			global $IN;

			switch ($IN->GBL('M', 'GET'))
			{
				case "close"	: $this->close_comment();
					break;
				case "open"		: $this->open_comment();
					break;
				default				: $this->print_tab();
					break;
			}
			exit();
		}
	}

	//----------------------------------------------------------------------
	// open_comment
	//	opens a single comment and then calls print_tab()
	//----------------------------------------------------------------------
	function open_comment()
	{
		global $IN, $PREFS, $DB, $DSP, $FNS;

		if (is_numeric($comment_id = $IN->GBL('comment_id', 'GET')) && $DSP->allowed_group('can_moderate_comments'))
		{
			$update_comments_sql = "UPDATE exp_comments wc" 
				. " SET wc.status = 'o'"
				. " WHERE wc.comment_id = " . $DB->escape_str($comment_id) 
				. " AND wc.site_id =	" . $DB->escape_str($PREFS->ini('site_id'));

			$DB->query($update_comments_sql);

			$FNS->clear_caching('all');

			$this->print_tab('', $comment_id);
		}
	}

	//----------------------------------------------------------------------
	// close_comment
	//	closes a single comment and then calls print_tab()
	//----------------------------------------------------------------------
	function close_comment()
	{
		global $IN, $PREFS, $DB, $DSP, $FNS;
	
		if (is_numeric($comment_id = $IN->GBL('comment_id', 'GET')) && $DSP->allowed_group('can_moderate_comments'))
		{
			$update_comments_sql = "UPDATE exp_comments wc" 
				. " SET wc.status = 'c'"
				. " WHERE wc.comment_id = " . $DB->escape_str($comment_id) 
				. " AND wc.site_id =	" . $DB->escape_str($PREFS->ini('site_id'));

			$DB->query($update_comments_sql);

			$FNS->clear_caching('all');

			$this->print_tab('', $comment_id);
		}
	}

	//----------------------------------------------------------------------
	// print_tab
	//	collects and outputs comments for the tab
	//----------------------------------------------------------------------
	function print_tab($msg = '', $changed = '')
	{
		global $EXT, $IN, $DSP, $PREFS, $DB, $LANG, $FNS, $LOC;

		// don't let standard processing continue when we're done
		$EXT->end_script = TRUE;

		$LANG->fetch_language_file('publish');
		$LANG->fetch_language_file('reeveal_comments');

		$entry_id = $IN->GBL('entry_id', 'GET');
		$offset = (is_numeric($IN->GBL('P', 'GET'))) ? $IN->GBL('P', 'GET') : '0';

		if (! $DSP->allowed_group('can_moderate_comments'))
		{
			print '<div style="text-align: center">' . $LANG->line('unauthorized') . '</div>';
			return;
		}		 

		// get all comments so we can count them 
		$num_comments = $DB->query("SELECT wc.comment_id 
			FROM exp_comments wc 
			WHERE wc.entry_id = " . $DB->escape_str($entry_id) . "
			AND wc.site_id =	" . $DB->escape_str($PREFS->ini('site_id')) . "
			ORDER BY comment_id ASC");

		if ($num_comments->num_rows < 1)
		{
			print '<div style="text-align: center">' . $LANG->line('no_comments') . '</div>';
			return;
		}

		// get some comments for display
		$get_comments_sql = "SELECT wc.* 
			FROM exp_comments wc 
			WHERE wc.entry_id = " . $DB->escape_str($entry_id) . "
			AND wc.site_id =	" . $DB->escape_str($PREFS->ini('site_id')) . "
			ORDER BY comment_id ASC
			LIMIT $offset, " . $this->settings['length'];

		$comments_data = $DB->query($get_comments_sql);

		// hey, let's create a page, shall we?
		$page = '<div>';

		// add any message to the page
		if (($msg != '') || ($msg = $IN->GBL('msg','GET')) != '')
		{
			//$page .= $DSP->qdiv('success', $LANG->line($msg)) . BR;
			$page .= $DSP->qdiv('success', $msg) . BR;
		}

		// start table
		$page .= $DSP->table_open(array('class' => 'tableBorder', 'width' => '100%', 'id' => 'reeveal_comments_table'));
							
		$style = 'tableHeadingAlt';

		// table columns
		$page .= $DSP->table_row(array(
			array('class' => $style, 'text' => $LANG->line('comment')),
			array('class' => $style, 'text' => $LANG->line('author')),
			array('class' => $style, 'text' => $LANG->line('email')),
			array('class' => $style, 'text' => $LANG->line('date')),
			array('class' => $style, 'text' => $LANG->line('status')),
		));

		// table contents
		$i = 0;
		foreach($comments_data->result as $row)
		{
			$style = 'tableCellTwo';
			$status_style = ($changed == $row['comment_id']) ? "$style updated" : $style;

			// build a colored status message
			if ($row['status'] == 'o')
			{
				$status = '<span style="color:#009933;">' . $LANG->line('open') . '</span>';
				$alt_status = '(<a class="alt_status" href="' . BASE . AMP . 'C=reeveal_comments' 
					. AMP . 'M=close' . AMP . "entry_id=$entry_id" . AMP . 'comment_id=' . $row['comment_id']
					. AMP . 'P=' . $offset . '">Close?</a>)';
			}
			else {
				$status = "<span style='color:#990000;'>" . $LANG->line('closed') . "</span>";
				$alt_status = '(<a class="alt_status" href="' . BASE . AMP . 'C=reeveal_comments' 
					. AMP . 'M=open' . AMP . "entry_id=$entry_id" . AMP . 'comment_id=' . $row['comment_id']
					. AMP . 'P=' . $offset . '">Open?</a>)';
			}	

			$page .= "<tr>"
				. '<td class="' . $style .'" width="30%">'
				.	$DSP->anchor(BASE . AMP . 'C=edit'	
					.	AMP . 'M=edit_comment' . AMP . 'weblog_id=' . $row['weblog_id'] 
					.	AMP . 'entry_id=' . $row['entry_id'] 
					.	AMP . 'comment_id=' . $row['comment_id'], $FNS->word_limiter($row['comment'], 20), '', TRUE)
				. '</td><td class="' . $style .'" width="20%">' . $row['name'] . '</td>'
				. '<td class="' . $style .'" width="15%">' . $row['email'] . '</td>'
				. '<td class="' . $style .'" width="15%">' . $LOC->set_human_time($row['comment_date']) . '</td>'
				. '<td class="' . $status_style .'" width="20%">' . $status . ' ' . $alt_status . '</td></tr>';

		}

	// finish table
	$page .= $DSP->table_c();

	// create a little table at the bottom for pagination and promotion
	$page .= '<table width="100%" style="text-align: center;"><tr><td style="text-align: left; width: 250px;">';

	// if we're not on the first page, create prev links
	if ($offset > 0)
	{ 
		$bottom = (($offset - $this->settings['length']) < 0) ? 0 : $offset - $this->settings['length'];
		$prev_msg = '&lt;&lt; ' . $LANG->line('comments') . ' ' . ($bottom + 1) . '-' . $offset;
		$prev_offset = $bottom;
		$page .= '<a class="reeveal_page" href="' . BASE . AMP . 'C=reeveal_comments'
					. AMP . "entry_id=$entry_id" . AMP . 'P=' . $prev_offset . '">' . $prev_msg . '</a>';
	}

	$page .= '</td><td>';

	// if we haven't paid, or at least clicked the "yes" button, show a message
	if ($this->settings['paid'] != 'yes')
	{
		$page .= $LANG->line('pitch');
	}

	$page .= '</td><td style="text-align: right; width: 250px;">';

	// if we're not on the last page, create next links
	if ($num_comments->num_rows > ($offset + $this->settings['length']))
	{ 
		$top = ($num_comments->num_rows < ($offset + (2 * $this->settings['length']))) ? $num_comments->num_rows : $offset + (2 * $this->settings['length']);
		$next_msg = $LANG->line('comments') . ' ' . ($offset + $this->settings['length'] + 1) . '-' . $top . ' &gt;&gt;';
		$next_offset = ($offset + $this->settings['length']);
		$page .= '<a class="reeveal_page" href="' . BASE . AMP . 'C=reeveal_comments'
					. AMP . "entry_id=$entry_id" . AMP . 'P=' . $next_offset . '">' . $next_msg . '</a>';
	}
	
	$page .= "</td></tr></table>";

	// finish page
	$page .= "</div>";	

	print $page;
	}

	//----------------------------------------------------------------------
	// is_reeveal_comments_request
	//	determines whether this extension should respond
	//----------------------------------------------------------------------
	function is_reeveal_comments_request()
	{
		global $IN;
		if (($IN->GBL('C', 'GET') == 'reeveal_comments') && is_numeric($IN->GBL('entry_id', 'GET')))
		{
			return TRUE;
		}
		return FALSE;
	}

	//----------------------------------------------------------------------
	// activate_extension
	//	an EE standard	
	//----------------------------------------------------------------------
	function activate_extension()
	{
		global $DB;

		// register for notification by hooks configured above
		foreach ($this->hook_methods as $hook => $method)
		{
			$DB->query($DB->insert_string("exp_extensions", array(
				'extension_id'	=> '',
				'class'			=> "Reeveal_comments",
				'method'		=> $method,
				'hook'			=> $hook,
				'settings'		=> "",
				'priority'		=> 10,
				'version'		=> $this->version,
				'enabled'		=> "y"
			))
			);
		}
	}
		
	//----------------------------------------------------------------------
	// disable_extension
	//	gets rid of *all* info related to this extension: settings and data
	//----------------------------------------------------------------------
	function disable_extension()
	{
		global $DB;
		$DB->query("DELETE FROM exp_extensions WHERE class = 'Reeveal_comments'");
	}

	//----------------------------------------------------------------------
	// update_extension
	//	to be used when/if we update this code
	//----------------------------------------------------------------------
	function update_extension($current='')
	{
		global $DB;
		
		if ($current == '' OR $current == $this->version)
		{
				return FALSE;
		}
		
		/*
		if ($current < '1.0.1')
		{
				// no special operations required for this update
		}

		if ($current < '1.0.2')
		{
				// no special operations required for this update
		}
		*/
		
		// increment extension version number
		$DB->query("UPDATE exp_extensions 
								SET version = '".$DB->escape_str($this->version)."' 
								WHERE class = 'Reeveal_comments'");
	}
}
?>
