jQuery(document).ready(function($) {
	
	// Create Group
	$('#wpps_create_group_helper').click(function(e) {
		
		// Check at least one group name has been selected
		checked = $(':checkbox:checked');
		if(checked.length === 0) {
			okay_popup('Empty User Selection', 'Please select at least one user to continue.');
			return false;
		}
		
		// Check if group name has been entered
		group_name = $('#wpps_group_name').val();
		if(group_name === '') {
			okay_popup('Empty Group Name', 'A valid Group Name must be entered to continue.');
			return false;
		}
		
		// Submit button
		$('#wpps_create_group').click();
	});
	
	// Delete Group
	$('.wpps_del_group').click(function(e) {
		
		var this_del_group_button = $(this);
		group_key = $(this).parent().siblings('.wpps_group_key').html();
		$.post(ajaxurl, {'action': 'wpps_del_group', 'group_key': group_key}, function(response) {
			
			if(response === 'success') {
				
				// Notify user
				okay_popup('Success', 'The group has been deleted successfully.');
				// Remove tr row
				$(this_del_group_button).parent().parent().remove();
			}
			else {
				
				okay_popup('Error', 'An error was encountered. Please file a support ticket for assistance.');
			}
		});
	});
	
	// Clear all groups
	$('#wpps_clear_all_groups').click(function(e) {
		
		$('.wpps_group').prop('checked', false);
		$('#wpps_group_name').val('');
	});
	
	// Create Rule
	$('#wpps_submit_rules_helper').click(function(e) {
		
		// Validation
		if($('select[name=wpps_select_group_from]').val() === '...') {
			okay_popup('Empty Selection', 'Please make a selection in Step 1.');
			return false;
		}
		if($('select[name=wpps_select_post_from]').val() === '...') {
			okay_popup('Empty Selection', 'Please make a selection in Step 2.');
			return false;
		}
		if($('select[name=wpps_select_post_to]').val() === '...') {
			okay_popup('Empty Selection', 'Please make a selection in Step 3.');
			return false;
		}
		if($('select[name=wpps_select_group_to]').val() === '...') {
			okay_popup('Empty Selection', 'Please make a selection in Step 4.');
			return false;
		}
		
		// Submit button
		$('#wpps_submit_rules').click();
	});
	
	// Delete Rule
	$('.wpps_del_rule').click(function(e) {
		
		var this_del_rule_button = $(this);
		rule_key = $(this).siblings('.wpps_del_rule_key').val();
		$.post(ajaxurl, {'action': 'wpps_del_rule', 'rule_key': rule_key}, function(response) {
			
			if(response === 'success') {
				
				// Notify user
				okay_popup('Success', 'The rule has been deleted successfully.');
				// Remove tr row
				$(this_del_rule_button).parent().parent().remove();
			}
			else {
				
				okay_popup('Error', 'An error was encountered. Please file a support ticket for assistance.');
			}
		});
	});
	
	// Select Subscribers
	$('#wpps_select_subscriber').click(function(e) {
		
		$('.wpps_group').each(function(i, v) { if($(this).siblings('.wpps_group_roles').val().indexOf('subscriber') >= 0) { $(this).prop('checked', true); } });
	});
	// Select Contributors
	$('#wpps_select_contributor').click(function(e) {
		
		$('.wpps_group').each(function(i, v) { if($(this).siblings('.wpps_group_roles').val().indexOf('contributor') >= 0) { $(this).prop('checked', true); } });
	});
	// Select Author
	$('#wpps_select_author').click(function(e) {
		
		$('.wpps_group').each(function(i, v) { if($(this).siblings('.wpps_group_roles').val().indexOf('author') >= 0) { $(this).prop('checked', true); } });
	});
	// Select Editor
	$('#wpps_select_editor').click(function(e) {
		
		$('.wpps_group').each(function(i, v) { if($(this).siblings('.wpps_group_roles').val().indexOf('editor') >= 0) { $(this).prop('checked', true); } });
	});
	// Select Admin
	$('#wpps_select_admin').click(function(e) {
		
		$('.wpps_group').each(function(i, v) { if($(this).siblings('.wpps_group_roles').val().indexOf('administrator') >= 0) { $(this).prop('checked', true); } });
	});
	
	/*
	****************************************************************
	Okay Popups (template)
	****************************************************************
	*/
	function okay_popup(this_title, this_message) {
			
		var $okay_popup = $('<div title="'+this_title+'"></div>').html(this_message).dialog({
			
			modal: true,
			width: 600,
			height: 200,
			closeOnEscape: true,
			buttons: {
				'Okay': function() {
					$(this).dialog('close');
				}
			}
		});
		$okay_popup.dialog("open");
	}
});