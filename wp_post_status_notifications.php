<?php
/**
* Plugin Name: WP Post Status Notifications
* Plugin URI: https://wpeditpro.com
* Description: Configure email notifications for post/page status changes.
* Version: 1.0
* Author: Josh Lobe
* Author URI: https://wpeditpro.com
* Text Domain: wp_post_status_notifications
* Domain Path: 
* License: GPL2
*/
 

// Begin plugin class
class wpps_notifications {
	
	// Define static variables
	var $version;
	var $post_status_array;
		
	var $wpps_post_types = array();
	var $wpps_groups = array();
	var $wpps_rules = array();
	var $wpps_email = array(
		'include_title' => 'on',
		'include_author' => 'on',
		'include_old_status' => 'on',
		'include_new_status' => 'on',
		'include_changed_by' => 'on',
		'include_post_content' => 'on',
		'email_subject' => '',
		'email_from' => '',
		'email_reply_to' => ''
	);
	
	// Construct function
	public function __construct() {
		
		// Activation hook
		register_activation_hook( __FILE__, array($this, 'plugin_activate'));
		// Plugin settings links
		add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'settings_link'));
		// Localization
		add_action('plugins_loaded', array($this, 'load_language_domain'));
		
		// Admin init
		add_action('admin_init', array($this, 'admin_init'));
		// Admin menu
		add_action('admin_menu', array($this, 'admin_menu'));
		// Ajax delete group
		add_action('wp_ajax_wpps_del_group', array($this, 'wpps_del_group_ajax'));
		// Ajax delete rule
		add_action('wp_ajax_wpps_del_rule', array($this, 'wpps_del_rule_ajax'));
		// Post status transition
		add_action('transition_post_status', array($this, 'transition_post_status'), 10, 3);
	}
	
	public function plugin_activate() {
		
		// Get current db values
		$get_post_types_opts = get_option('wp_post_status_post_types');
		$get_groups_opts = get_option('wp_post_status_groups');
		$get_rules_opts = get_option('wp_post_status_rules');
		$get_email_opts = get_option('wp_post_status_email');
		
		// Check if db values are set; otherwise use plugin defaults
		$post_types_opts = isset($get_post_types_opts) && !empty($get_post_types_opts) ? $get_post_types_opts : $this->wpps_post_types;
		$groups_opts = isset($get_groups_opts) && !empty($get_groups_opts) ? $get_groups_opts : $this->wpps_groups;
		$rules_opts = isset($get_rules_opts) && !empty($get_rules_opts) ? $get_rules_opts : $this->wpps_rules;
		$email_opts = isset($get_email_opts) && !empty($get_email_opts) ? $get_email_opts : $this->wpps_email;
		
		// Update options
		update_option('wp_post_status_post_types', $post_types_opts);
		update_option('wp_post_status_groups', $groups_opts);
		update_option('wp_post_status_rules', $rules_opts);
		update_option('wp_post_status_email', $email_opts);
	}
	
	public function settings_link($links) {
		
		// Define admin settings page link
		$settings_link = '<a href="admin.php?page=wpps_admin">'.__('Settings', 'wp_post_status_notifications').'</a>';
		
		// Return merged array
		return array_merge($links, array('settings' => $settings_link));
	}
	
	public function load_language_domain() {
		
		load_plugin_textdomain('wp_post_status_notifications', false, dirname(plugin_basename(__FILE__)).'/langs');
	}
	
	public function admin_init() {
		
		// Set plugin version variable
		$plugin_data = get_plugin_data(__FILE__);
		$this->version = $plugin_data['Version'];
		
		// Set plugin stati variable
		$stati = get_post_stati();
		unset($stati['auto-draft']);
		unset($stati['inherit']);
		$this->post_status_array = $stati;
	}
	
	public function admin_menu() {
		
		// Create menu page
		$wpps_admin = add_menu_page(__('Notifications', 'wp_post_status_notifications'), __('Notifications', 'wp_post_status_notifications'), 'manage_options', 'wpps_admin', array($this, 'admin_menu_page'));
		// Conditionally add scripts, styles and load functions
		if(isset($wpps_admin)) {
			add_action('admin_print_scripts-'.$wpps_admin, array($this, 'admin_scripts'));
			add_action('admin_print_styles-'.$wpps_admin, array($this, 'admin_styles'));
			add_action('load-'.$wpps_admin, array($this, 'save_admin_menu_page'));
		}
	}
	
	public function admin_menu_page() {
		
		$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'welcome';
		?>
		<div class="wrap">
            <h2><?php _e('WP Post Status Notifications', 'wp_post_status_notifications'); ?></h2>
            
            <div id="wppse_tabbed_content">
                <h3 class="nav-tab-wrapper">  
                    <a href="?page=wpps_admin&tab=welcome" class="nav-tab <?php echo $active_tab == 'welcome' ? 'nav-tab-active' : ''; ?>"><?php _e('Welcome', 'wp_post_status_notifications'); ?></a>
                    <a href="?page=wpps_admin&tab=post_types" class="nav-tab <?php echo $active_tab == 'post_types' ? 'nav-tab-active' : ''; ?>"><?php _e('Post Types', 'wp_post_status_notifications'); ?></a>
                    <a href="?page=wpps_admin&tab=groups" class="nav-tab <?php echo $active_tab == 'groups' ? 'nav-tab-active' : ''; ?>"><?php _e('Groups', 'wp_post_status_notifications'); ?></a>
                    <a href="?page=wpps_admin&tab=rules" class="nav-tab <?php echo $active_tab == 'rules' ? 'nav-tab-active' : ''; ?>"><?php _e('Rules', 'wp_post_status_notifications'); ?></a>
                    <a href="?page=wpps_admin&tab=email" class="nav-tab <?php echo $active_tab == 'email' ? 'nav-tab-active' : ''; ?>"><?php _e('Email', 'wp_post_status_notifications'); ?></a>
                </h3>
            </div>
            
            <?php
			if($active_tab === 'welcome') {
				
				?>
				<h3><?php _e('Welcome', 'wp_post_status_notifications'); ?></h3>
                <p>
					<?php _e('This plugin will send email notifications when a post, page or custom post type status is changed.', 'wp_post_status_notifications'); ?>
                </p>
                
                <h4><?php _e('Step 1: Select Post Types', 'wp_post_status_notifications'); ?></h4>
                <p>
					<?php _e('The first step is to select which post types will be used for the email notifications.', 'wp_post_status_notifications'); ?><br />
					<?php _e('Click the "Post Types" tab; and select which post types will be used.', 'wp_post_status_notifications'); ?>
                </p>
                
                <h4><?php _e('Step 2: Create User Groups', 'wp_post_status_notifications'); ?></h4>
                <p>
					<?php _e('The next step is to create user groups.', 'wp_post_status_notifications'); ?><br />
					<?php _e('At minimum, one "From" group and one "To" group should be created.', 'wp_post_status_notifications'); ?><br />
					<?php _e('Think of this as what group is being "watched"; and what group should be "alerted".', 'wp_post_status_notifications'); ?><br />
					<?php _e('Click the "Groups" tab to get started.', 'wp_post_status_notifications'); ?>
                </p>
                
                <h4><?php _e('Step 3: Create Mailing Rules', 'wp_post_status_notifications'); ?></h4>
                <p>
					<?php _e('Next, the mailing rules need to be created.', 'wp_post_status_notifications'); ?><br />
					<?php _e('These will determine when an email will be sent.', 'wp_post_status_notifications'); ?><br />
					<?php _e('Click the "Rules" tab to get started.', 'wp_post_status_notifications'); ?>
                </p>
                
                <h4><?php _e('Step 4: Set Email Options', 'wp_post_status_notifications'); ?></h4>
                <p>
					<?php _e('Lastly, the email options can be configured.', 'wp_post_status_notifications'); ?><br />
					<?php _e('Use these options to display what information will be shown in the email.', 'wp_post_status_notifications'); ?><br />
					<?php _e('Click the "Email" tab to get started.', 'wp_post_status_notifications'); ?>
                </p>
                <?php
			}
			
            if($active_tab === 'post_types'){
                
                ?>
                <h3><?php _e('Post Types', 'wp_post_status_notifications'); ?></h3>
                <p>
					<?php _e('Include these post types.', 'wp_post_status_notifications'); ?>
                </p>
                
                <form method="post" action="">
                <table class="wppse-table">
                <thead>
                    <tr><th>
                        <?php _e('Post Type', 'wp_post_status_notifications'); ?>
                    </th><th>
                        <?php _e('Yes/No', 'wp_post_status_notifications'); ?>
                    </th></tr>
                </thead>
                <tbody>
                    <?php
					$valid_post_types = get_option('wp_post_status_post_types');
                    $post_types = $this->get_post_types();
					$checked = '';
					
                    foreach($post_types as $type) {
						
						if(is_array($valid_post_types))
							$checked = in_array($type, $valid_post_types) ? 'checked' : '';
							
                        echo '<tr><td>'.$type.'</td><td><input type="checkbox" name="post_types_'.$type.'" '.$checked.' /></td></tr>';
                    }
                    ?>
                </tbody>
                </table>
                <br /><br />
                <input type="submit" class="button button-primary" name="wpps_submit_types" value="<?php _e('Save Options', 'wp_post_status_notifications'); ?>" />
                </form>
                <?php
			}
			
			if($active_tab === 'groups'){
                
				$users = get_users();
                ?>
                <h3><?php _e('Users', 'wp_post_status_notifications'); ?></h3>
                <p>
                	<?php _e('Select which users will be assigned to this group.', 'wp_post_status_notifications'); ?>
                </p>
                
				<form method="post" action="">
                    <input type="button" class="button button-secondary" id="wpps_select_subscriber" value="<?php _e('Subscribers', 'wp_post_status_notifications'); ?>" />
                    <input type="button" class="button button-secondary" id="wpps_select_contributor" value="<?php _e('Contributors', 'wp_post_status_notifications'); ?>" />
                    <input type="button" class="button button-secondary" id="wpps_select_author" value="<?php _e('Authors', 'wp_post_status_notifications'); ?>" />
                    <input type="button" class="button button-secondary" id="wpps_select_editor" value="<?php _e('Editors', 'wp_post_status_notifications'); ?>" />
                    <input type="button" class="button button-secondary" id="wpps_select_admin" value="<?php _e('Admins', 'wp_post_status_notifications'); ?>" />
                    <table cellspacing="10">
                    <tbody>
                    <tr>
					<?php 
                    $i = 0;
                    foreach ($users as $user) {
                    	if ($i % 6 === 0) {
                    		echo '</tr><tr>';
                    	}
                    	echo '<td><input type="hidden" class="wpps_group_roles" value="'.implode(',', $user->roles).'" /><input type="checkbox" name="wpps_group[]" class="wpps_group" value="'.$user->ID.'" />' . $user->user_nicename . '</td>';
                    	$i++;
                    }
                    ?>
                    </tr>
                    </tbody>
                    </table>
                    <br /><br />
                    <?php _e('Enter a name for this group.', 'wp_post_status_notifications'); ?><br />
                    <input type="text" name="wpps_group_name" id="wpps_group_name" /><br />
                    <br />
                	<input type="submit" class="button button-primary" name="wpps_create_group" id="wpps_create_group" value="<?php _e('Create Group', 'wp_post_status_notifications'); ?>" style="display:none;" />
                	<input type="button" class="button button-primary" name="wpps_create_group_helper" id="wpps_create_group_helper" value="<?php _e('Create Group', 'wp_post_status_notifications'); ?>" />
                	<input type="button" class="button button-secondary" name="wpps_clear_all_groups" id="wpps_clear_all_groups" value="<?php _e('Clear All', 'wp_post_status_notifications'); ?>" />
                </form>
                
                <hr />
                <h3><?php _e('Created Groups', 'wp_post_status_notifications'); ?></h3>
                
                <?php
				$groups = get_option('wp_post_status_groups');
				
				?>
                <table class="wppse-table">
				<thead>
					<tr><th><?php _e('Group Name', 'wp_post_status_notifications'); ?></th><th><?php _e('Group Users', 'wp_post_status_notifications'); ?></th><th><?php _e('Action', 'wp_post_status_notifications'); ?></th></tr>
				</thead>
                <tbody>
                <?php
				if(is_array($groups)) {
					foreach($groups as $key=>$value) {
						if(is_array($value)) {
							$users = '';
							foreach($value as $val) {
								// Get each user data
								$user = get_userdata($val);
								// Comma separated list of user nicenames
								$users .= $user->user_nicename.', ';
							}
						}
						// Trim final comma and space
						$users = rtrim($users, ', ');
						echo '<tr><td class="wpps_group_key">'.$key.'</td><td>'.$users.'</td><td><input type="button" class="button button-secondary wpps_del_group" value="'.__('Delete', 'wp_post_status_notifications').'" /></td></tr>';
					}
				}
				?>
                </tbody>
                </table>
                <?php
			}
			
			if($active_tab === 'rules') {
				
				// Get post stati
				$stati = $this->post_status_array;
				
				// Get created groups
				$groups = get_option('wp_post_status_groups');
				$group_names = array();
				if(is_array($groups)) {
					foreach($groups as $group=>$val) {
						$group_names[] = $group;
					}
				}
				?>
                <h3><?php _e('Rules', 'wp_post_status_notifications'); ?></h3>
                <p>
                	<?php _e('Create mailing rules; which determine when an email will be sent.', 'wp_post_status_notifications'); ?>
                </p>
                
                <?php
				if(empty($groups)) {
					
					echo '<div class="error"><p>';
						_e('No groups have yet been created. Please create a group to continue.', 'wp_post_status_notifications');
					echo '</p></div>';
				}
				?>
                <form method="post" action="">
                <table class="wppse-table">
                <thead>
                </thead>
                <tbody>
                <tr>
                	<td>
                    <strong>(<?php _e('Step 1', 'wp_post_status_notifications'); ?>)</strong> <?php _e('When (this group) ', 'wp_post_status_notifications'); ?>
                    </td>
                    <td>
                    	<select name="wpps_select_group_from">
							<option value="..."><?php _e('...', 'wp_post_status_notifications'); ?></option>
                            <?php
							foreach ($group_names as $name) {
								echo '<option value="'.$name.'">'.$name.'</option>';
							}
							?>
						</select>
                    </td>
                </tr>
                <tr>
                	<td>
                    <strong>(<?php _e('Step 2', 'wp_post_status_notifications'); ?>)</strong> <?php _e('Changes a post status from ', 'wp_post_status_notifications'); ?>
                    </td>
                    <td>
                    	<select name="wpps_select_post_from">
							<option value="..."><?php _e('...', 'wp_post_status_notifications'); ?></option>
							<option value="any"><?php _e('Any Status', 'wp_post_status_notifications'); ?></option>
                            <?php
							foreach ($stati as $status) {
								echo '<option value="'.$status.'">'.$status.'</option>';
							}
							?>
						</select>
                    </td>
                </tr>
                <tr>
                	<td>
                    <strong>(<?php _e('Step 3', 'wp_post_status_notifications'); ?>)</strong> <?php _e('To a post status of ', 'wp_post_status_notifications'); ?>
                    </td>
                    <td>
                    	<select name="wpps_select_post_to">
							<option value="..."><?php _e('...', 'wp_post_status_notifications'); ?></option>
							<option value="any"><?php _e('Any Status', 'wp_post_status_notifications'); ?></option>
                            <?php
							foreach ($stati as $status) {
								echo '<option value="'.$status.'">'.$status.'</option>';
							}
							?>
						</select>
                    </td>
                </tr>
                <tr>
                	<td>
                    <strong>(<?php _e('Step 4', 'wp_post_status_notifications'); ?>)</strong> <?php _e('Notify (this group) ', 'wp_post_status_notifications'); ?>
                    </td>
                    <td>
                    	<select name="wpps_select_group_to">
							<option value="..."><?php _e('...', 'wp_post_status_notifications'); ?></option>
                            <?php
							foreach ($group_names as $name) {
								echo '<option value="'.$name.'">'.$name.'</option>';
							}
							?>
						</select>
                    </td>
                </tr>
                </tbody>
                </table>
                <input type="submit" class="button-primary" name="wpps_submit_rules" id="wpps_submit_rules" value="<?php _e('Submit Rule', 'wp_post_status_notifications'); ?>" style="display:none;" />
                <input type="button" class="button-primary" name="wpps_submit_rules_helper" id="wpps_submit_rules_helper" value="<?php _e('Submit Rule', 'wp_post_status_notifications'); ?>" />
                </form>
                
                <hr />
                <h3><?php _e('Created Rules', 'wp_post_status_notifications'); ?></h3>
                <?php
				$get_rules = get_option('wp_post_status_rules');
				echo '<table class="wppse-table" cellspacing="0"><thead>';
					echo '<tr><th>'.__('Group From', 'wp_post_status_notifications').'</th><th>'.__('Post From', 'wp_post_status_notifications').'</th><th>'.__('Post To', 'wp_post_status_notifications').'</th><th>'.__('Group To', 'wp_post_status_notifications').'</th><th>'.__('Action', '').'</th></tr>';
				echo '</thead><tbody>';
					if(is_array($get_rules)) {
						foreach($get_rules as $rule => $value) {
							echo '<tr><td>'.$value['from_group'].'</td><td>'.$value['from_post'].'</td><td>'.$value['to_post'].'</td><td>'.$value['to_group'].'</td><td><input type="button" class="button button-secondary wpps_del_rule" value="'.__('Delete', '').'" /><input type="hidden" class="wpps_del_rule_key" value="'.$rule.'" /></td></tr>';
						}
					}
				echo '</tbody></table>';
			}
			
			if($active_tab === 'email') {
				
				$get_email_opt = get_option('wp_post_status_email');
				$include_title = isset($get_email_opt['include_title']) && $get_email_opt['include_title'] === 'on' ? 'checked' : '';
				$include_author = isset($get_email_opt['include_author']) && $get_email_opt['include_author'] === 'on' ? 'checked' : '';
				$include_old_status = isset($get_email_opt['include_old_status']) && $get_email_opt['include_old_status'] === 'on' ? 'checked' : '';
				$include_new_status = isset($get_email_opt['include_new_status']) && $get_email_opt['include_new_status'] === 'on' ? 'checked' : '';
				$include_changed_by = isset($get_email_opt['include_changed_by']) && $get_email_opt['include_changed_by'] === 'on' ? 'checked' : '';
				$include_post_content = isset($get_email_opt['include_post_content']) && $get_email_opt['include_post_content'] === 'on' ? 'checked' : '';
				
				$email_subject = isset($get_email_opt['email_subject']) ? $get_email_opt['email_subject'] : __('WordPress Post Status Notification', 'wp_post_status_notifications');
				$email_from = isset($get_email_opt['email_from']) ? $get_email_opt['email_from'] : '';
				$email_reply_to = isset($get_email_opt['email_reply_to']) ? $get_email_opt['email_reply_to'] : '';
				?>
                
                <h3><?php _e('Email', 'wp_post_status_notifications'); ?></h3>
                <p>
					<?php _e('Select which information will display in the email.', 'wp_post_status_notifications'); ?>
                </p>
                
                <form method="post" action="">
                <table class="wppse-table" cellspacing="0">
                	<thead>
                    	<tr><th>
                        	<?php _e('Include in Email', 'wp_post_status_notifications'); ?>
                        </th><th>
                        	<?php _e('Yes/No', 'wp_post_status_notifications'); ?>
                        </th></tr>
                    </thead>
                    <tbody>
                    	<tr><td>
                        	<?php _e('Post Title', 'wp_post_status_notifications'); ?>
                        </td><td>
                        	<input type="checkbox" name="include_title" <?php echo $include_title; ?> />
                        </td></tr>
                    	<tr><td>
                        	<?php _e('Post Author', 'wp_post_status_notifications'); ?>
                        </td><td>
                        	<input type="checkbox" name="include_author" <?php echo $include_author; ?> />
                        </td></tr>
                    	<tr><td>
                        	<?php _e('Old Status', 'wp_post_status_notifications'); ?>
                        </td><td>
                        	<input type="checkbox" name="include_old_status" <?php echo $include_old_status; ?> />
                        </td></tr>
                    	<tr><td>
                        	<?php _e('New Status', 'wp_post_status_notifications'); ?>
                        </td><td>
                        	<input type="checkbox" name="include_new_status" <?php echo $include_new_status; ?> />
                        </td></tr>
                    	<tr><td>
                        	<?php _e('Changed By', 'wp_post_status_notifications'); ?>
                        </td><td>
                        	<input type="checkbox" name="include_changed_by" <?php echo $include_changed_by; ?> />
                        </td></tr>
                    	<tr><td>
                        	<?php _e('Post Content', 'wp_post_status_notifications'); ?>
                        </td><td>
                        	<input type="checkbox" name="include_post_content" <?php echo $include_post_content; ?> />
                        </td></tr>
                   </tbody>
                </table>
                
                <table class="wppse-table">
                <thead>
               	</thead>
                <tbody>
                	<tr>
                    	<td><?php _e('Email Subject', 'wp_post_status_notifications'); ?></td>
                        <td><input type="textbox" name="email_subject" value="<?php echo $email_subject; ?>" /></td>
                    </tr>
                	<tr>
                    	<td><?php _e('From Address', 'wp_post_status_notifications'); ?></td>
                        <td>
                        	<input type="textbox" name="email_from" value="<?php echo $email_from; ?>" /><br />
							<?php _e('If blank; admin email will be used.', 'wp_post_status_notifications'); ?>
                        </td>
                    </tr>
                	<tr>
                    	<td><?php _e('Reply To Address', 'wp_post_status_notifications'); ?></td>
                        <td>
                        	<input type="textbox" name="email_reply_to" value="<?php echo $email_reply_to; ?>" /><br />
							<?php _e('If blank; admin email will be used.', 'wp_post_status_notifications'); ?>
                        </td>
                    </tr>
                </tbody>
                </table>
                <input type="submit" class="button-primary" name="wpps_submit_email" value="<?php _e('Save Email Options', 'wp_post_status_notifications'); ?>" />
                </form>   
                <?php
			}
		?>
        </div>
        <?php
	}
	
	public function save_admin_menu_page() {
		
		if(isset($_POST['wpps_submit_types'])) {
			
			// Set vars
			$post_types = $this->get_post_types();
			$options = array();
			
			// Loop each post type and if set; add to options array
			foreach($post_types as $type) {
				if(isset($_POST['post_types_'.$type])) {
					$options[] = $type;
				}
			}
			
			// Update option
			update_option('wp_post_status_post_types', $options);
			
			// Alert user
			function wpps_save_types_success() {
				echo '<div class="updated"><p>';
				_e('Post Types Options successfully updated.', 'wp_post_status_notifications');
				echo '</p></div>';
			}
			add_action('admin_notices', 'wpps_save_types_success');
		}
		
		if(isset($_POST['wpps_create_group'])) {
				
			// Get current db values
			$old_opts = get_option('wp_post_status_groups');
			
			// Check if group name already exists
			if(array_key_exists(sanitize_text_field($_POST['wpps_group_name']), $old_opts)) {
				
				// Alert user
				function wpps_create_group_error_name_exists() {
					echo '<div class="error"><p>';
					_e('This Group Name already exists. Group Names must be unique.', 'wp_post_status_notifications');
					echo '</p></div>';
				}
				add_action('admin_notices', 'wpps_create_group_error_name_exists');
			}
			// Else insert group
			else {
			
				// Merge arrays
				$old_opts[sanitize_text_field($_POST['wpps_group_name'])] = sanitize_text_field($_POST['wpps_group']);
				// Update option
				update_option('wp_post_status_groups', $old_opts);
				
				// Alert user
				function wpps_create_group_success() {
					echo '<div class="updated"><p>';
					_e('Group has been created successfully.', 'wp_post_status_notifications');
					echo '</p></div>';
				}
				add_action('admin_notices', 'wpps_create_group_success');
			}
		}
		
		if(isset($_POST['wpps_submit_rules'])) {
			
			// Get form vars
			$from_group = $_POST['wpps_select_group_from'];
			$from_post = $_POST['wpps_select_post_from'];
			$to_post = $_POST['wpps_select_post_to'];
			$to_group = $_POST['wpps_select_group_to'];
			
			// TODO: Add check to see if table entry already exists
			
			// Insert into database
			$get_rules = get_option('wp_post_status_rules');
			$get_rules_array = isset($get_rules) ? $get_rules : array();
			$new_array = array(array('from_group' => $from_group, 'from_post' => $from_post, 'to_post' => $to_post, 'to_group' => $to_group));
			$merge_array = array_merge($get_rules_array, $new_array);
			update_option('wp_post_status_rules', $merge_array);
				
			// Alert user
			function wpps_insert_db_success() {
				echo '<div class="updated"><p>';
				_e('The new Rule was added successfully.', 'wp_post_status_notifications');
				echo '</p></div>';
			}
			add_action('admin_notices', 'wpps_insert_db_success');
		}
		
		if(isset($_POST['wpps_submit_email'])) {
			
			// Get old option
			$get_email = get_option('wp_post_status_email');
			$get_email_array = isset($get_email) ? $get_email : array();
			
			// Update page options
			$get_email_array['include_title'] = isset($_POST['include_title']) ? $_POST['include_title'] : 'off';
			$get_email_array['include_author'] = isset($_POST['include_author']) ? $_POST['include_author'] : 'off';
			$get_email_array['include_old_status'] = isset($_POST['include_old_status']) ? $_POST['include_old_status'] : 'off';
			$get_email_array['include_new_status'] = isset($_POST['include_new_status']) ? $_POST['include_new_status'] : 'off';
			$get_email_array['include_changed_by'] = isset($_POST['include_changed_by']) ? $_POST['include_changed_by'] : 'off';
			$get_email_array['include_post_content'] = isset($_POST['include_post_content']) ? $_POST['include_post_content'] : 'off';
			
			$get_email_array['email_subject'] = isset($_POST['email_subject']) ? sanitize_text_field($_POST['email_subject']) : '';
			$get_email_array['email_from'] = isset($_POST['email_from']) ? sanitize_text_field($_POST['email_from']) : '';
			$get_email_array['email_reply_to'] = isset($_POST['email_reply_to']) ? sanitize_text_field($_POST['email_reply_to']) : '';
			
			// Update database option
			update_option('wp_post_status_email', $get_email_array);
			
			// Alert user
			function wpps_update_email_success() {
				echo '<div class="updated"><p>';
				_e('Email options updated successfully.', 'wp_post_status_notifications');
				echo '</p></div>';
			}
			add_action('admin_notices', 'wpps_update_email_success');
		}
	}
	
	public function admin_scripts() {
		
		// Enqueue admin page scripts
		wp_enqueue_script('jquery-ui-dialog');
		wp_register_script( 'wp_ps_admin_page_script', plugins_url('js/admin_page.js', __FILE__), array('jquery', 'jquery-ui-dialog'), $this->version, true );
		wp_enqueue_script( 'wp_ps_admin_page_script' );
	}
	
	public function admin_styles() {
		
		// Enqueue admin page styles
		wp_enqueue_style('wp-jquery-ui-dialog');  // WP jquery dialog
		wp_register_style('wp_ps_admin_page_css', plugins_url('css/admin_page.css', __FILE__));
		wp_enqueue_style('wp_ps_admin_page_css');  // Admin page css
	}
	
	public function wpps_del_group_ajax() {
		
		// Get group key from page
		$group_key = sanitize_text_field($_POST['group_key']);
		// Get main option
		$main_opt = get_option('wp_post_status_groups');
		// Unset group key
		unset($main_opt[$group_key]);
		// Update option
		update_option('wp_post_status_groups', $main_opt);
		
		echo 'success';
		wp_die();
	}
	
	public function wpps_del_rule_ajax() {
		
		// Get rule key from page
		$rule_key = sanitize_text_field($_POST['rule_key']);
		// Get main option
		$main_opt = get_option('wp_post_status_rules');
		// Unset rule key
		unset($main_opt[$rule_key]);
		// Update option
		update_option('wp_post_status_rules', $main_opt);
		
		echo 'success';
		wp_die();
	}
	
	public function transition_post_status($new, $old, $post) {
		
		// Don't do anything if nothing changed
		if($old != $new) {
			
			// Get db post types
			$get_post_types = get_option('wp_post_status_post_types');
			// Make sure this post type exists in our "watched" post types
			if(in_array($post->post_type, $get_post_types)) {
				
				// Get current user
				$user = wp_get_current_user();
				
				// Get rules
				$get_rules = get_option('wp_post_status_rules');
				foreach($get_rules as $rule => $value) {
					
					// Check custom rule post_from and post_to values
					if(($value['from_post'] == $old || $value['from_post'] === 'all') && ($value['to_post'] == $new || $value['to_post'] === 'all')) {
						
						// Get current user ID
						$cur_user_id = $user->ID;
					
						// Get from_group
						$get_group_opts = get_option('wp_post_status_groups');
						$get_group_from = $get_group_opts[$value['from_group']];
						
						// Compare current user against from_group
						if(in_array($cur_user_id, $get_group_from)) {
							
							// Get to_group
							$get_group_to = $get_group_opts[$value['to_group']];
							$to_emails = '';
							
							// Create list of to_group emails
							if(is_array($get_group_to)) {
								foreach($get_group_to as $group=>$value) {
									
									// Get user email by user ID ($value)
									$user_data = get_userdata($value);
									$to_emails .= $user_data->user_email.', ';
								}
							}
							
							// Trim final comma and space
							$to_emails = rtrim($to_emails, ', ');
							
							// Define email vars
							$post_type = $post->post_type;
							$post_id = $post->ID;
							$post_title = $post->post_title;
							$author_id = $post->post_author;
							$post_content = $post->post_content;
							$post_guid = $post->guid;
							$body = '';
							$admin_email = get_option('admin_email');
							
							// Get plugin email settings
							$email_opts = get_option('wp_post_status_email');
							$options_include_title = $email_opts['include_title'];
							$options_include_author = $email_opts['include_author'];
							$options_include_old_status = $email_opts['include_old_status'];
							$options_include_new_status = $email_opts['include_new_status'];
							$options_include_changed_by = $email_opts['include_changed_by'];
							$options_include_post_content = $email_opts['include_post_content'];
							
							$email_subject = isset($email_opts['email_subject']) ? $email_opts['email_subject'] : __('WordPress Post Status Notification', '');
							$email_from = isset($email_opts['email_from']) && $email_opts['email_from'] !== '' ? $email_opts['email_from'] : $admin_email;
							$email_reply_to = isset($email_opts['email_reply_to']) && $email_opts['email_reply_to'] !== '' ? $email_opts['email_reply_to'] : $admin_email;
						
							//TODO: Add buttons to view/edit/trash post in email
							
							// Build html email
							$to = $to_emails;
							$subject = $email_subject;
							
							$headers = 'From: '.$email_from."\r\n" .
								'Reply-To: '.$email_reply_to."\r\n" .
								'Content-type: text/html; charset=iso-8859-1' . "\r\n" . 
								'X-Mailer: PHP/'.phpversion();
								
							$body = '<div id="email_container">
										<div style="width:570px; padding:0 0 0 20px; margin:50px auto 12px auto" id="email_header">
											<span style="background:#585858; color:#fff; padding:12px;font-family:trebuchet ms; letter-spacing:1px; 
												-moz-border-radius-topleft:5px; -webkit-border-top-left-radius:5px; 
												border-top-left-radius:5px;moz-border-radius-topright:5px; -webkit-border-top-right-radius:5px; 
												border-top-right-radius:5px;">
												'.get_bloginfo('name').'
											</div>
										</div>
									
										<div style="width:550px; padding:0 20px 20px 20px; background:#fff; margin:0 auto; border:3px #000 solid;
											moz-border-radius:5px; -webkit-border-radius:5px; border-radius:5px; color:#454545;line-height:1.5em; " id="email_content">
											
											<h1 style="padding:5px 0 0 0; font-family:georgia;font-weight:500;font-size:24px;color:#000;border-bottom:1px solid #bbb">
												'.$email_subject.'
											</h1>';
											
											// Conditionally add post update data
											if(isset($options_include_title) && $options_include_title === 'on')
												$body .= '<strong>'.__('Post Title:', 'wp_post_status_notifications').'</strong><br />'.$post_title.' <em>(Post ID: '.$post_id.')</em><br /><br />';
											
											if(isset($options_include_author) && $options_include_author === 'on')
												$body .= '<strong>'.__('Post Author:', 'wp_post_status_notifications').'</strong><br />'.get_the_author_meta('display_name', $author_id).'<br /><br />';
												
											if(isset($options_include_old_status) && $options_include_old_status === 'on')
												$body .= '<strong>'.__('Old Status:', 'wp_post_status_notifications').'</strong><br />'.$old.'<br /><br />';
											
											if(isset($options_include_new_status) && $options_include_new_status === 'on')
												$body .= '<strong>'.__('New Status:', 'wp_post_status_notifications').'</strong><br />'.$new.'<br /><br />';
											
											if(isset($options_include_changed_by) && $options_include_changed_by === 'on')
												$body .= '<strong>'.__('Changed By:', 'wp_post_status_notifications').'</strong><br />'.$user->user_nicename.'<br /><br />';
												
											if(isset($options_include_post_content) && $options_include_post_content === 'on')
												$body .= '<strong>'.__('Post Content:', 'wp_post_status_notifications').'</strong><br />'.$post_content.'<br /><br />';
											
											
											$body .= '<div style="text-align:center; border-top:1px solid #eee;padding:5px 0 0 0;" id="email_footer"> 
														<small style="font-size:11px; color:#999; line-height:14px;">
															'.__('You have received this email from ', 'wp_post_status_notifications').get_bloginfo('name').__('; WP Post Status Notifications plugin.', 'wp_post_status_notifications').'
														</small>
													</div>
											
										</div>
									</div>';
							
							// Send email
							mail($to, $subject, $body, $headers);
						}
					}
				}
			}
		}
	}
	
	public function get_post_types() {
		
		// Define array of post types
		$default = array('post', 'page');
		$args = array('_builtin' => false);
		$post_types = get_post_types($args);
		$post_types = array_merge($default, $post_types);
		return $post_types;
	}
}

// Instantiate plugin class
$new_wpps_notifications = new wpps_notifications();

?>