<?php

/** bbPress =====================================================**/

// user profile
add_action('bbp_template_after_user_profile', 'bbpm_profile_parse_contact_button');

function bbpm_profile_parse_contact_button() {
    $user_id = bbp_get_user_id( 0, true, false );
    
    // check permissions
    if ( !bbpm_can_contact($user_id) )
        return;

    print '<p>';
    // parse
    bbpm_load_template('button.php', array('user_id' => $user_id));
    print '</p>';
}

// user forums
add_action( 'bbp_theme_after_reply_author_details', 'bbpm_forums_parse_contact_button' );

function bbpm_forums_parse_contact_button() {
    $user_id = bbp_get_reply_author_id();

    // check permissions
    if ( !bbpm_can_contact($user_id) )
        return;

    print '<p>';
    // parse
    bbpm_load_template('button.php', array('user_id' => $user_id));
    print '</p>';
}

// profile field
add_action('bbp_user_edit_after_contact', 'bbpm_bbp_profile_edit_preferences_field');

function bbpm_bbp_profile_edit_preferences_field() {
    $user_id = bbp_get_displayed_user_id();

    ?>
    <div class="bbpm-notify">
        <label for="bbpm_notify">
            <input type="checkbox" name="bbpm_notify" id="bbpm_notify" <?php checked(get_user_meta($user_id, 'bbpm_mute_notifications', true), false); ?> />
            <span style="vertical-align:top"><?php _e('Allow email notifications of new messages', 'bbp-messages'); ?></span>
        </label>
    </div>
    <?php
}

// when updating our profile
add_action('personal_options_update', 'bbpm_bbp_profile_edit_preferences');
// when updating other users' profiles
add_action('edit_user_profile_update', 'bbpm_bbp_profile_edit_preferences');
 
function bbpm_bbp_profile_edit_preferences( $user_id ) {
    // exclude profile.php/user-edit update
    if ( is_admin() ) return;
    // update preference
    if ( isset( $_POST['bbpm_notify'] ) ) {
        return delete_user_meta($user_id, 'bbpm_mute_notifications');
    } else {
        return update_user_meta($user_id, 'bbpm_mute_notifications', time());
    }
}