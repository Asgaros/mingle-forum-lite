<?php

if (!defined('ABSPATH')) {
    exit;
}

class AsgarosForumProfile {
    private $asgarosforum = null;

    public function __construct($asgarosForumObject) {
        $this->asgarosforum = $asgarosForumObject;

        add_action('asgarosforum_breadcrumbs_profile', array($this, 'add_breadcrumbs_profile'));
        add_action('asgarosforum_breadcrumbs_history', array($this, 'add_breadcrumbs_history'));
    }

    // Checks if the profile functionality is enabled.
    public function functionalityEnabled() {
        return $this->asgarosforum->options['enable_profiles'];
    }

    // Checks if profile links should be hidden for the current user.
    public function hideProfileLink() {
        if (!is_user_logged_in() && $this->asgarosforum->options['hide_profiles_from_guests']) {
            return true;
        } else {
            return false;
        }
    }

    public function get_user_data($user_id) {
        return get_user_by('id', $user_id);
    }

    // Gets the current title.
    public function get_profile_title() {
        $currentTitle = __('Profile', 'asgaros-forum').$this->get_title_suffix();

        return $currentTitle;
    }

    public function get_history_title() {
        $currentTitle = __('Post History', 'asgaros-forum').$this->get_title_suffix();

        return $currentTitle;
    }

    public function get_editprofile_title() {
        $currentTitle = __('Edit Profile', 'asgaros-forum').$this->get_title_suffix();

        return $currentTitle;
    }

    private function get_title_suffix() {
        $suffix   = '';
        $userData = $this->get_user_data($this->asgarosforum->current_element);

        if ($userData) {
            $user_name = apply_filters('asgarosforum_filter_username', $userData->display_name, $userData);
            $suffix    = ': '.$user_name;
        }

        return $suffix;
    }

    // Sets the breadcrumbs.
    public function add_breadcrumbs_profile() {
        $elementLink  = $this->asgarosforum->get_link('current');
        $elementTitle = __('Profile', 'asgaros-forum').$this->get_title_suffix();
        $this->asgarosforum->breadcrumbs->add_breadcrumb($elementLink, $elementTitle);
    }

    public function add_breadcrumbs_history() {
        $elementLink  = $this->asgarosforum->get_link('current');
        $elementTitle = __('Post History', 'asgaros-forum').$this->get_title_suffix();
        $this->asgarosforum->breadcrumbs->add_breadcrumb($elementLink, $elementTitle);
    }

    public function show_profile_header($user_data) {
        $userOnline       = ($this->asgarosforum->online->is_user_online($user_data->ID)) ? 'user-online' : 'user-offline';
        $background_style = '';
        $user_id          = $user_data->ID;

        echo '<div id="profile-header" class="'.esc_attr($userOnline).'">';
            if ($this->asgarosforum->options['enable_avatars']) {

                $url = get_avatar_url($user_id, 480);

                // Add filter for custom profile header
                $url = apply_filters('asgarosforum_filter_profile_header_image', $url, $user_id);

                $background_style = 'style="background-image: url(\''.$url.'\');"';
            }

            echo '<div class="background-avatar" '.wp_kses_post($background_style).'></div>';
            echo '<div class="background-contrast"></div>';

            // Show avatar.
            if ($this->asgarosforum->options['enable_avatars']) {
                echo get_avatar($user_data->ID, 160, '', '', array('force_display' => true));
            }

            echo '<div class="user-info">';
                $user_name = apply_filters('asgarosforum_filter_username', $user_data->display_name, $user_data);
                echo '<div class="profile-display-name">'.esc_html($user_name).'</div>';

                echo '<div class="profile-forum-role">';
                $count_posts = $this->asgarosforum->countPostsByUser($user_id);
                $this->asgarosforum->render_reputation_badges($count_posts);

				$role = $this->asgarosforum->permissions->getForumRole($user_id);

                // Special styling for banned users.
                if ($this->asgarosforum->permissions->get_forum_role($user_id) === 'banned') {
                    echo '<span class="af-usergroup-tag banned"><i class="fa-solid fa-ban"></i>'.esc_html($role).'</span>';
                } else {
					echo esc_html($role);
				}

                echo '</div>';
            echo '</div>';
        echo '</div>';
    }

    public function show_profile_navigation($user_data) {
        echo '<div id="profile-navigation">';
            $profile_link = $this->getProfileLink($user_data);
            $history_link = $this->get_history_link($user_data);
			$edit_link = $this->get_editprofile_link($user_data); 

            // Profile link.
            if ($this->asgarosforum->current_view === 'profile') {
                echo '<a class="active" href="'.esc_url($profile_link).'">'.esc_html__('Profile', 'asgaros-forum').'</a>';
            } else {
                echo '<a href="'.esc_url($profile_link).'">'.esc_html__('Profile', 'asgaros-forum').'</a>';
            }

            // History link.
            if ($this->asgarosforum->current_view === 'history') {
                echo '<a class="active" href="'.esc_url($history_link).'">'.esc_html__('Post History', 'asgaros-forum').'</a>';
            } else {
                echo '<a href="'.esc_url($history_link).'">'.esc_html__('Post History', 'asgaros-forum').'</a>';
            }

            // Add Edit Profile tab, if user_data belong to current user
            $current_user_id = get_current_user_id();
			if ($user_data->ID == $current_user_id) {
				if ($this->asgarosforum->current_view === 'editprofile') {
					echo '<a class="active" href="'.esc_url($edit_link).'">'.esc_html__('Edit Profile', 'asgaros-forum').'</a>';
				} else {
					echo '<a href="'.esc_url($edit_link).'">'.esc_html__('Edit Profile', 'asgaros-forum').'</a>';
				}
			}

            do_action('asgarosforum_custom_profile_menu');
        echo '</div>';
    }

    public function count_post_history_by_user($user_id) {
        return count($this->get_post_history_by_user($user_id));
    }

    public function get_post_history_by_user($user_id, $limit = false) {
        // Get accessible categories for the current user first.
        $accessible_categories = $this->asgarosforum->content->get_categories_ids();

        if (empty($accessible_categories)) {
            // Cancel if the user cant access any categories.
            return false;
        } else {
            // Now load history-data based for an user based on the categories which are accessible for the current user.
            $accessible_categories = implode(',', $accessible_categories);

			$query       = '';
            $query_limit = '';

            if ($limit) {
                $elements_maximum = 50;
                $elements_start   = $this->asgarosforum->current_page * $elements_maximum;

                $query_limit = "LIMIT {$elements_start}, {$elements_maximum}";
            }

			if ($this->asgarosforum->permissions->isModerator('current') || $user_id === get_current_user_id()) {
				// Full data if the user is at least a moderator or the current profile belongs to the current user.
            	$query = "SELECT p.id, p.text, p.date, p.parent_id, t.name FROM {$this->asgarosforum->tables->posts} AS p, {$this->asgarosforum->tables->topics} AS t WHERE p.parent_id = t.id AND p.author_id = %d AND EXISTS (SELECT f.id FROM {$this->asgarosforum->tables->forums} AS f WHERE f.id = t.parent_id AND f.parent_id IN ({$accessible_categories})) AND t.approved = 1 ORDER BY p.id DESC {$query_limit};";
			} else {
				// Hide topics of private forums from everyone else.
            	$query = "SELECT p.id, p.text, p.date, p.parent_id, t.name FROM {$this->asgarosforum->tables->posts} AS p, {$this->asgarosforum->tables->topics} AS t, {$this->asgarosforum->tables->forums} AS f WHERE p.parent_id = t.id AND t.parent_id = f.id AND f.forum_status <> 'private' AND p.author_id = %d AND EXISTS (SELECT f.id FROM {$this->asgarosforum->tables->forums} AS f WHERE f.id = t.parent_id AND f.parent_id IN ({$accessible_categories})) AND t.approved = 1 ORDER BY p.id DESC {$query_limit};";
			}

			return $this->asgarosforum->db->get_results($this->asgarosforum->db->prepare($query, $user_id));
        }
    }

    public function show_history() {
        $user_id = $this->asgarosforum->current_element;

		// If no user ID is given but the current user is logged in, then use the ID of the current logged in user.
        if (!$user_id && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }

        $userData = $this->get_user_data($user_id);

        if ($userData) {
            if ($this->hideProfileLink()) {
                esc_html_e('You need to login to have access to profiles.', 'asgaros-forum');
            } else {
                $this->show_profile_header($userData);
                $this->show_profile_navigation($userData);

                echo '<div id="profile-layer">';
                    $posts = $this->get_post_history_by_user($user_id, true);

                    if (empty($posts)) {
                        esc_html_e('No posts made by this user.', 'asgaros-forum');
                    } else {
                        $pagination = $this->asgarosforum->pagination->renderPagination('history', $user_id);

                        if ($pagination) {
                            echo '<div class="pages-and-menu">'.$pagination.'</div>';
                        }

                        foreach ($posts as $post) {
                            echo '<div class="history-element">';
                                echo '<div class="history-name">';
                                    $link = $this->asgarosforum->rewrite->get_post_link($post->id, $post->parent_id);

                                    echo '<a class="history-title" href="'.esc_url($link).'">';
									echo esc_html($this->asgarosforum->cut_string(esc_html(stripslashes(wp_strip_all_tags($post->text))), 100));
									echo '</a>';

                                    $topic_link = $this->asgarosforum->rewrite->get_link('topic', $post->parent_id);
                                    $topic_time = $this->asgarosforum->get_activity_timestamp($post->date);

                                    echo '<span class="history-topic">'.esc_html__('In:', 'asgaros-forum').' <a href="'.esc_url($topic_link).'">';
									echo esc_html(stripslashes($post->name));
									echo '</a></span>';
                                echo '</div>';

                                echo '<div class="history-time">'.esc_html($topic_time).'</div>';
                            echo '</div>';
                        }

                        if ($pagination) {
                            echo '<div class="pages-and-menu">'.$pagination.'</div>';
                        }
                    }
                echo '</div>';
            }
        } else {
            esc_html_e('This user does not exist.', 'asgaros-forum');
        }
    }

/*
 *
 *  Edit profile
 *  Shows user's own profile for editing
 *
 */
    
    public function showEditProfile() {
        $user_id = $this->asgarosforum->current_element;
        $userData = $this->get_user_data($user_id);
        if ($userData) {
            if ($this->hideProfileLink()) {
                esc_html_e('You need to login to have access to profiles.', 'asgaros-forum');
            } else {
                $this->show_profile_header($userData);
                $this->show_profile_navigation($userData);

/**************************************/
/* Update Profile When User Saves It  */
/**************************************/

$error = array();   
if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && $_POST['action'] == 'update-user' ) {

    /*
     * Check for errors
     * Inputs with 'required' attribute are prechecked 
     * for presence so we don't need to do that.
     */

    /* Email errors */
    $email_exists = email_exists(esc_attr( $_POST['email'] ));
    if (!is_email(esc_attr( $_POST['email'] ))) {
        $error[] = __('The email you entered is not valid.  Please try again.', 'asgaros-forum');
    } else {
        if ( $email_exists && $email_exists != $user_id )
        	$error[] = __('This email is already used by another user.  Try a different one.', 'asgaros-forum'); 
	}

    /* Password error */
    if ( !( $_POST['pass1'] == $_POST['pass2'] ) )
        $error[] = __('The passwords you entered do not match.  No data were updated.', 'asgaros-forum');
        
    /*
     * Update if no errors, else show them
     * The little reload script fixes edited display_name not updating when page reloads.
     */
	if ( count($error) == 0 ) {
		update_user_meta( $user_id, 'first_name', esc_attr( $_POST['first-name'] ) );
		update_user_meta( $user_id, 'last_name', esc_attr( $_POST['last-name'] ) );
		update_user_meta( $user_id, 'description', esc_attr( $_POST['bio-info'] ) );

		if ($this->asgarosforum->options['enable_mentioning']) {
            if (isset($_POST['asgarosforum_mention_notify'])) {
                update_user_meta($user_id, 'asgarosforum_mention_notify', 'yes');
            } else {
                update_user_meta($user_id, 'asgarosforum_mention_notify', 'no');
            }
        }

        if ($this->asgarosforum->options['allow_signatures']) {
            // Ensure that the user has permission to use a signature.
            if ($this->asgarosforum->permissions->can_use_signature($user_id)) {
                if (isset($_POST['asgarosforum_signature'])) {
                    if ($this->asgarosforum->options['signatures_html_allowed']) {
						// Parse signature before saving.
						$allowed_signature_html_tags = array();

						if (!empty($this->asgarosforum->options['signatures_html_tags'])) {
							$tags = $this->asgarosforum->options['signatures_html_tags'];
							$tags = str_replace('><', ',', $tags);
							$tags = str_replace('<', '', $tags);
							$tags = str_replace('>', '', $tags);
							$tags = explode(',', $tags);

							foreach ($tags as $tag) {
								$allowed_signature_html_tags[$tag] = array();
							}
						}
                        update_user_meta($user_id, 'asgarosforum_signature', trim(wp_kses($_POST['asgarosforum_signature'], $allowed_signature_html_tags)));
                    } else {
                        update_user_meta($user_id, 'asgarosforum_signature', sanitize_textarea_field($_POST['asgarosforum_signature']));
                    }
                } else {
                    delete_user_meta($user_id, 'asgarosforum_signature');
                }
            }
        }

		wp_update_user( array( 'ID' => $user_id, 'display_name' => esc_attr( $_POST['display-name'] ) ) );
		wp_update_user( array( 'ID' => $user_id, 'user_url' => esc_url( $_POST['url'] ) ) );
		if ( !empty($_POST['pass1'] ) && !empty( $_POST['pass2'] ) )
			wp_update_user( array( 'ID' => $user_id, 'user_pass' => esc_attr( $_POST['pass1'] ) ) );
		if ( !$email_exists )
			wp_update_user( array ('ID' => $user_id, 'user_email' => esc_attr( $_POST['email'] )));
	
	# I had added following command to reload after saving edits
	# because display name was not getting updated if not default.
	# Reloading the page fixed that, but then later it just goes
	# into a reload loop.  So deleting.  Display name issue not serious.	
	#	echo '<script>location.reload();</script>';
  	} else {
  		echo '<p class="error">' . implode("<br />", $error) . '</p>';
	}
}

/**************************************/
/* Edit Profile Form */
/**************************************/
	if ( !is_user_logged_in() ) : ?>
		<p class="warning">
			<?php _e('You must be logged in to edit your profile.', 'asgaros-forum'); ?>
		</p>
    <?php else : ?>

		<form method="post">
                <ul id="profile-content" class="af-edit-profile">
                
                	<li class="profile-row"><h4>Name</h4></li>
                	
                	<!-- User name (wp_users) -->
                    <li class="profile-row">
                        <label for="username"><?php _e('User Name *', 'asgaros-forum'); ?></label>
                        <input class="text-input" name="username" type="text" id="username" value="<?php the_author_meta( 'user_login', $user_id ); ?>" disabled required />
                        <span class="edit-profile-description">Cannot be changed</span>
                    </li>
                	
                	<!-- First name (wp_usermeta) -->
                    <li class="profile-row">
                        <label for="first-name"><?php _e('First Name *', 'asgaros-forum'); ?></label>
                        <input class="text-input" name="first-name" type="text" id="first-name" value="<?php the_author_meta( 'first_name', $user_id ); ?>" required />
                        <span class="edit-profile-description">Given name</span>
                    </li>
                    
                	<!-- Last name (wp_usermeta) -->
                    <li class="profile-row">
                        <label for="last-name"><?php _e('Last Name *', 'asgaros-forum'); ?></label>
                        <input class="text-input" name="last-name" type="text" id="last-name" value="<?php the_author_meta( 'last_name', $user_id ); ?>" required />
                        <span class="edit-profile-description">Surname</span>
                    </li> 
                  <?php 
        /*
         * Display_name (wp_users) drop-down.  First create the options as
         * done in user-edit.php of WordPress core
         */
        $public_display = array();
        $public_display['displayname']  = $userData->display_name;
        $public_display['nickname']  = $userData->nickname;
        if ( !empty($userData->first_name) )        
            $public_display['firstname'] = $userData->first_name;
        if ( !empty($userData->last_name) )
            $public_display['lastname'] = $userData->last_name;
        if ( !empty($userData->first_name) && !empty($userData->last_name) ) {
            $public_display['firstlast'] = $userData->first_name . ' ' . $userData->last_name;
            $public_display['lastfirst'] = $userData->last_name . ' ' . $userData->first_name;
        }
        $public_display = array_map( 'trim', $public_display );
        $public_display = array_unique( $public_display );

		$output = '<label for="display_name">' . __("Display name publicly as", "asgaros-forum") . '</label><select class="text-input" name="display-name" id="display-name">';

        foreach( $public_display as $display_name_option ) {
            $output .= '<option ' . selected( $userData->display_name, $display_name_option, false ) . '>' . $display_name_option . '</option>';
        }
        $output .= '</select><span class="edit-profile-description">How your name will appear</span>';
		?>
                    <li class="profile-row">
                        <?php echo $output; ?>
                    </li>

                 	<li class="profile-row"><h4>Contact Info</h4></li>
                 	
                	<!-- Email (wp_users) -->
                    <li class="profile-row">
                        <label for="email"><?php _e('E-mail *', 'asgaros-forum'); ?></label>
                        <input class="text-input" name="email" type="text" id="email" value="<?php the_author_meta( 'user_email', $user_id ); ?>" required />
                        <span class="edit-profile-description">If changed, notice will be sent to old email.</span>
                    </li>
                    
                	<!-- Website (wp_users) -->
                    <li class="profile-row">
                        <label for="url"><?php _e('Website', 'asgaros-forum'); ?></label>
                        <input class="text-input" name="url" type="text" id="url" value="<?php the_author_meta( 'user_url', $user_id ); ?>" />
                        <span class="edit-profile-description"></span>
                    </li>
                    
                	 <li class="profile-row"><h4>About Yourself</h4></li>
                	 
                	<!-- Biographical info/description (wp_usermeta) -->
                    <li class="profile-row">
                        <label for="bio-info"><?php _e('Biographical Info', 'asgaros-forum') ?></label>
                        <textarea name="bio-info" id="bio-info" rows="3" cols="50"><?php the_author_meta( 'description', $user_id ); ?></textarea>
                        <span class="edit-profile-description"></span>
                    </li>
                    
                 	<!-- Gravatar info -->
                    <li class="profile-row">
                        <label><?php _e('Profile Picture/Avatar', 'asgaros-forum') ?></label>
                        <p><?php 
					_e( 'Add or change profile picture at <a href="https://en.gravatar.com/">Gravatar</a>.', 'asgarosforum' ) ?></p>
                    </li>

                 	<?php if ($this->asgarosforum->options['enable_mentioning'] || $this->asgarosforum->options['allow_signatures'] ) {
                 		echo '<li class="profile-row"><h4>Forum</h4></li>'; 
                 	} ?> 
                 	
                	<!-- Mention notification? (wp_usermeta) -->
                	<?php if ($this->asgarosforum->options['enable_mentioning']) { ?>
						<li class="profile-row">
							<label for="asgarosforum_mention_notify"> <?php echo __('Notify me when I get mentioned', 'asgaros-forum'); ?> </label>
							<input type="checkbox" name="asgarosforum_mention_notify" id="asgarosforum_mention_notify" value="1" <?php checked($this->asgarosforum->mentioning->user_wants_notification($user_id) ); ?> />
						</li>
					<?php } ?>

                	<!-- Forum signature (wp_usermeta) -->  <?php
					if ($this->asgarosforum->options['allow_signatures']) {
						// Ensure that the user has permission to use a signature.
						if ($this->asgarosforum->permissions->can_use_signature($user_id)) {
							$output = '<li class="profile-row">';
							$output .= '<label for="signature">' . __('Forum Signature', 'asgaros-forum') . '</label>';
							$signature = $this->asgarosforum->get_signature($user_id);
							$output .= '<textarea name="asgarosforum_signature" id="asgarosforum_signature" rows="3" cols="50">' . $signature . '</textarea>';

							// Description with info about allowed HTML tags.
							$description = '<span class="edit-profile-description">' . __('Appears under forum posts.', 'asgaros-forum');
							if ($this->asgarosforum->options['signatures_html_allowed']) {
								$description .= '&nbsp;' . __('You can use these HTML tags in signatures:', 'asgaros-forum');
								$description .= '&nbsp;<code>'.esc_html($this->asgarosforum->options['signatures_html_tags']).'</code></span>';
							} else {
								$description .= '&nbsp;' . __('HTML tags are not allowed in signatures.', 'asgaros-forum').'</span>';
							}
							echo $output . $description;
						}
					} ?>
                    
                	<!-- Password (wp_users) -->
                 	<li class="profile-row"><h4>Change Password</h4></li>
                    <li class="profile-row">
                        <label for="pass1"><?php _e('Password', 'asgarosforum'); ?> </label>
                        <input class="text-input" name="pass1" type="password" id="pass1" autocomplete="new-password" />
                    </li>
                    <li class="profile-row">
                        <label for="pass2"><?php _e('Repeat Password', 'asgaros-forum'); ?></label>
                        <input class="text-input" name="pass2" type="password" id="pass2" autocomplete="new-password" />
                        <span class="edit-profile-description">Enter your new password twice</span>
                    </li>

                	<!-- Update/submit button -->
                    <li class="profile-row form-submit">
						<input type="hidden" name="description" value="update_user" />
						<input type="hidden" name="action" value="update-user" />
						<input type="submit" value="<?php _e('Update Profile', 'asgaros-forums'); ?>" class="submit button" name="submit" />    
					</li>

                  	<!-- action hook for plugin or extra fields, 
                  		though extra fields may need to go before submit button, 
                  		so maybe need two action hooks? -->
                    <li class="profile-row">
						<?php do_action('asgarosforum_after_edit_profile', $userData); ?>
					</li>
                </ul> <!-- #profile-content -->
			</form>
          
            <?php
			endif; // !is_user_logged_in()

            }   // end if ($this->hideProfileLink()) ... else
        } else {   // if $userData
            esc_html_e('This user does not exist.', 'asgaros-forum');
        }   // end if $userData
    }   // end showEditProfile()

/*
 *
 *  Show a profile
 *
 *
 */
    // Shows the profile of a user.
    public function show_profile() {
        $user_id = $this->asgarosforum->current_element;

		// If no user ID is given but the current user is logged in, then use the ID of the current logged in user.
        if (!$user_id && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }

        $userData = $this->get_user_data($user_id);

        if ($userData) {
            if ($this->hideProfileLink()) {
                esc_html_e('You need to login to have access to profiles.', 'asgaros-forum');
            } else {
                $this->show_profile_header($userData);
                $this->show_profile_navigation($userData);

                echo '<div id="profile-content">';
                    // Defines an array for profile rows.
                    $profileRows = array();

                    // Show first name.
                    if (!empty($userData->first_name)) {
                        $profileRows['first_name'] = array(
                            'title' => __('First Name:', 'asgaros-forum'),
                            'value' => $userData->first_name,
                        );
                    }

                    // Show usergroups.
                    $userGroups = AsgarosForumUserGroups::getUserGroupsOfUser($userData->ID, 'all', true);

                    if (!empty($userGroups)) {
                        $profileRows['usergroup'] = array(
                            'title' => __('Usergroups:', 'asgaros-forum'),
                            'value' => $userGroups,
                            'type'  => 'usergroups',
                        );
                    }

                    // Show website.
                    if (!empty($userData->user_url)) {
                        $profileRows['website'] = array(
                            'title' => __('Website:', 'asgaros-forum'),
                            'value' => '<a href="'.$userData->user_url.'" rel="nofollow" target="_blank">'.$userData->user_url.'</a>',
                        );
                    }

                    // Show last seen.
                    if ($this->asgarosforum->online->functionality_enabled && $this->asgarosforum->options['show_last_seen']) {
                        $profileRows['last_seen'] = array(
                            'title' => __('Last seen:', 'asgaros-forum'),
                            'value' => $this->asgarosforum->online->last_seen($userData->ID),
                        );
                    }

                    // Show member since.
                    $profileRows['member_since'] = array(
                        'title' => __('Member Since:', 'asgaros-forum'),
                        'value' => $this->asgarosforum->format_date($userData->user_registered, false),
                    );

                    // Show biographical info.
                    if (!empty($userData->description)) {
                        $profileRows['bio'] = array(
                            'title' => __('Biographical Info:', 'asgaros-forum'),
                            'value' => trim(wpautop(esc_html($userData->description))),
                        );
                    }

                    // Show signature.
                    $signature = $this->asgarosforum->get_signature($userData->ID);

                    if ($signature !== false) {
                        $profileRows['signature'] = array(
                            'title' => __('Signature:', 'asgaros-forum'),
                            'value' => $signature,
                        );
                    }

                    $profileRows = apply_filters('asgarosforum_filter_profile_row', $profileRows, $userData);

                    foreach ($profileRows as $profileRow) {
                        if (!empty($profileRow['type'])) {
                            $this->renderProfileRow($profileRow['title'], $profileRow['value'], $profileRow['type']);
                        } else {
                            $this->renderProfileRow($profileRow['title'], $profileRow['value']);
                        }
                    }

                    do_action('asgarosforum_profile_row', $userData);

                    echo '<div class="profile-section-header">';
                        echo '<span class="profile-section-header-icon fas fa-address-card"></span>';
                        echo esc_html__('Member Activity', 'asgaros-forum');
                    echo '</div>';

                    echo '<div class="profile-section-content">';
                        // Topics started.
                        $count_topics = $this->asgarosforum->countTopicsByUser($userData->ID);
                        AsgarosForumStatistics::renderStatisticsElement(__('Topics Started', 'asgaros-forum'), $count_topics, 'far fa-comments');

                        // Replies created.
                        $count_posts = $this->asgarosforum->countPostsByUser($userData->ID);
                        $count_posts = $count_posts - $count_topics;
                        AsgarosForumStatistics::renderStatisticsElement(__('Replies Created', 'asgaros-forum'), $count_posts, 'far fa-comment');

                        // Likes Received.
                        if ($this->asgarosforum->options['enable_reactions']) {
                            $count_likes = $this->asgarosforum->reactions->get_reactions_received($userData->ID, 'up');
                            AsgarosForumStatistics::renderStatisticsElement(__('Likes Received', 'asgaros-forum'), $count_likes, 'fas fa-thumbs-up');
                        }
                    echo '</div>';

                    do_action('asgarosforum_custom_profile_content', $userData);

                    $current_user_id = get_current_user_id();

                    // Check if the current user can ban this user.
                    if ($this->asgarosforum->permissions->can_ban_user($current_user_id, $userData->ID)) {
                        if ($this->asgarosforum->permissions->isBanned($userData->ID)) {
                            $url       = $this->getProfileLink($userData, array('unban_user' => $userData->ID));
                            $nonce_url = wp_nonce_url($url, 'unban_user_'.$userData->ID);
                            echo '<a class="danger-link" href="'.esc_url($nonce_url).'">'.esc_html__('Unban User', 'asgaros-forum').'</a>';
                        } else {
                            $url       = $this->getProfileLink($userData, array('ban_user' => $userData->ID));
                            $nonce_url = wp_nonce_url($url, 'ban_user_'.$userData->ID);
                            echo '<a class="danger-link" href="'.esc_url($nonce_url).'">'.esc_html__('Ban User', 'asgaros-forum').'</a>';
                        }
                    }
                echo '</div>';
            }
        } else {
            esc_html_e('This user does not exist.', 'asgaros-forum');
        }
    }

    public function renderProfileRow($cellTitle, $cellValue, $type = 'default') {
        echo '<div class="profile-row profile-row-'.esc_attr($type).'">';
            echo '<div class="profile-row-title">'.esc_html($cellTitle).'</div>';
            echo '<div class="profile-row-value">';

            if (is_array($cellValue)) {
                foreach ($cellValue as $value) {
                    if ($type == 'usergroups') {
                        echo wp_kses_post(AsgarosForumUserGroups::render_usergroup_tag($value));
                    } else {
                        echo wp_kses_post($value).'<br>';
                    }
                }
            } else {
                echo wp_kses_post($cellValue);
            }

            echo '</div>';
        echo '</div>';
    }

    public function getProfileLink($userObject, $additional_parameters = false) {
        $profileLink = false;

        if ($this->functionalityEnabled() && !$this->hideProfileLink()) {
            $profileLink = $this->asgarosforum->get_link('profile', $userObject->ID, $additional_parameters, '', false);
        }

        return apply_filters('asgarosforum_filter_profile_link', $profileLink, $userObject);
    }

    public function get_history_link($userObject) {
        if ($this->hideProfileLink() || !$this->functionalityEnabled()) {
            return false;
        } else {
            $profileLink = $this->asgarosforum->get_link('history', $userObject->ID);
            $profileLink = apply_filters('asgarosforum_filter_history_link', $profileLink, $userObject);

            return $profileLink;
        }
    }

	// Link for editing own profile
    public function get_editprofile_link($userObject) {
        if ($this->hideProfileLink() || !$this->functionalityEnabled()) {
            return false;
        } else {
            $editLink = $this->asgarosforum->get_link('editprofile', $userObject->ID);
// Not sure if a new filter is wanted here
//          $editLink = apply_filters('asgarosforum_filter_editprofile_link', $editLink, $userObject);
            return $editLink;
        }
    }

    // Renders a link to the own profile. The own profile is always available, even when the profile functionality is disabled.
    public function myProfileLink() {
        // First check if the user is logged in.
        if ($this->functionalityEnabled()) {

            $profileLink = '';

            // Only continue if the current user is logged in.
            if (is_user_logged_in()) {
                // Get current user.
                $currentUserObject = wp_get_current_user();

                // Get and build profile link.
                $profileLink = $this->getProfileLink($currentUserObject);

                return array(
                    'menu_class'        => 'profile-link',
                    'menu_link_text'    => esc_html__('Profile', 'asgaros-forum'),
                    'menu_url'          => $profileLink,
                    'menu_login_status' => 1,
                    'menu_new_tab'      => false,
                );
            }
        }
    }
}
