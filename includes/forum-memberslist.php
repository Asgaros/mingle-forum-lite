<?php

if (!defined('ABSPATH')) exit;

class AsgarosForumMembersList {
    private $asgarosforum = null;
    public $filter_type = 'role';
    public $filter_name = 'all';

    public function __construct($object) {
        $this->asgarosforum = $object;

        // Set filter based on URL parameters.
        $this->set_filter();
    }

    public function functionalityEnabled() {
        if (!$this->asgarosforum->options['enable_memberslist'] || ($this->asgarosforum->options['memberslist_loggedin_only'] && !is_user_logged_in())) {
            return false;
        } else {
            return true;
        }
    }

    public function set_filter() {
        if ($this->functionalityEnabled()) {
            if (!empty($_GET['filter_type']) && !empty($_GET['filter_name'])) {
                switch ($_GET['filter_type']) {
                    case 'role':
                        switch ($_GET['filter_name']) {
                            case 'all':
                            case 'normal':
                            case 'moderator':
                            case 'administrator':
                            case 'banned':
                                $this->filter_type = 'role';
                                $this->filter_name = $_GET['filter_name'];
                            break;
                        }
                    break;

                    case 'group':
                        $this->filter_type = 'group';
                        $this->filter_name = $_GET['filter_name'];
                    break;
                }
            }
        }
    }

    public function renderMembersListLink() {
        if ($this->functionalityEnabled()) {
            $membersLink = $this->asgarosforum->get_link('members');
            $membersLink = apply_filters('asgarosforum_filter_members_link', $membersLink);

            echo '<a class="members-link" href="'.$membersLink.'">'.__('Members', 'asgaros-forum').'</a>';
        }
    }

    public function show_filters() {
        $filter_toggle_text = __('Show Filters', 'asgaros-forum');
        $filter_toggle_class = 'dashicons-arrow-down-alt2';
        $filter_toggle_hidden = 'style="display: none;"';

        if (!empty($_GET['filter_type']) && !empty($_GET['filter_name'])) {
            $filter_toggle_text = __('Hide Filters', 'asgaros-forum');
            $filter_toggle_class = 'dashicons-arrow-up-alt2';
            $filter_toggle_hidden = '';
        }

        echo '<div class="title-element dashicons-before '.$filter_toggle_class.'" id="memberslist-filter-toggle">'.$filter_toggle_text.'</div>';
        echo '<div id="memberslist-filter" data-value-show-filters="'.__('Show Filters', 'asgaros-forum').'" data-value-hide-filters="'.__('Hide Filters', 'asgaros-forum').'" '.$filter_toggle_hidden.'>';
            echo '<div id="roles-filter">';
                echo __('Roles:', 'asgaros-forum');
                echo '&nbsp;';
                echo $this->render_filter_option('role', 'all', 'All Users');
                echo '&nbsp;&middot;&nbsp;';
                echo $this->render_filter_option('role', 'normal', 'Normal');
                echo '&nbsp;&middot;&nbsp;';
                echo $this->render_filter_option('role', 'moderator', 'Moderators');
                echo '&nbsp;&middot;&nbsp;';
                echo $this->render_filter_option('role', 'administrator', 'Administrators');
                echo '&nbsp;&middot;&nbsp;';
                echo $this->render_filter_option('role', 'banned', 'Banned');
            echo '</div>';

            $usergroups = AsgarosForumUserGroups::getUserGroups(array(), true);

            if (!empty($usergroups)) {
                $first_usergroup = true;
                $usergroups_filter_output = '';

                foreach ($usergroups as $usergroup) {
                    $users_counter = AsgarosForumUserGroups::countUsersOfUserGroup($usergroup->term_id);

                    // Only list usergroups with users in it.
                    if ($users_counter > 0) {
                        if ($first_usergroup) {
                            $first_usergroup = false;
                        } else {
                            $usergroups_filter_output .= '&nbsp;&middot;&nbsp;';
                        }

                        $usergroups_filter_output .= $this->render_filter_option('group', $usergroup->term_id, $usergroup->name);
                    }
                }

                if (!empty($usergroups_filter_output)) {
                    echo '<div id="roles-filter">';
                    echo __('Usergroups:', 'asgaros-forum');
                    echo '&nbsp;';
                    echo $usergroups_filter_output;
                    echo '</div>';
                }
            }

        echo '</div>';
    }

    public function render_filter_option($filter_type, $filter_name, $title) {
        $output = '<a href="'.$this->asgarosforum->rewrite->get_link('members', false, array('filter_type' => $filter_type, 'filter_name' => $filter_name)).'">'.$title.'</a>';

        if ($filter_type === $this->filter_type && $filter_name == $this->filter_name) {
            return '<b>'.$output.'</b>';
        }

        return $output;
    }

    public function showMembersList() {
        $pagination_rendering = $this->asgarosforum->pagination->renderPagination('members');
        $paginationRendering = ($pagination_rendering) ? '<div class="pages-and-menu">'.$pagination_rendering.'<div class="clear"></div></div>' : '';
        echo $paginationRendering;

        $this->show_filters();

        echo '<div class="content-element">';

        $showAvatars = get_option('show_avatars');

        $data = $this->getMembers();

        if (empty($data)) {
            echo '<div class="notice">'.__('No users found!', 'asgaros-forum').'</div>';
        } else {
            $start = $this->asgarosforum->current_page * $this->asgarosforum->options['members_per_page'];
            $end = $this->asgarosforum->options['members_per_page'];

            $dataSliced = array_slice($data, $start, $end);

            foreach ($dataSliced as $element) {
                $userOnline = ($this->asgarosforum->online->is_user_online($element->ID)) ? ' user-online' : '';

                echo '<div class="member'.$userOnline.'">';
                    if ($showAvatars) {
                        echo '<div class="member-avatar">';
                        echo get_avatar($element->ID, 60);
                        echo '</div>';
                    }

                    echo '<div class="member-name">';
                        echo $this->asgarosforum->getUsername($element->ID);
                        echo '<small>';
                            echo $this->asgarosforum->permissions->getForumRole($element->ID);
                        echo '</small>';
                    echo '</div>';

                    echo '<div class="member-posts">';
                        $member_posts_i18n = number_format_i18n($element->forum_posts);
                        echo sprintf(_n('%s Post', '%s Posts', $element->forum_posts, 'asgaros-forum'), $member_posts_i18n);
                    echo '</div>';

                    if ($this->asgarosforum->online->functionality_enabled) {
                        echo '<div class="member-last-seen">';
                            echo __('Last seen:', 'asgaros-forum').' <i>'.$this->asgarosforum->online->last_seen($element->ID).'</i>';
                        echo '</div>';
                    }
                echo '</div>';
            }
        }

        echo '</div>';

        echo $paginationRendering;
    }

    public function getMembers() {
        $allUsers = false;

        if ($this->filter_type === 'role') {
            $allUsers = $this->asgarosforum->permissions->get_users_by_role($this->filter_name);
        } else if ($this->filter_type === 'group') {
            $allUsers = AsgarosForumUserGroups::get_users_in_usergroup($this->filter_name);
        }

        if ($allUsers) {
            // Now get the amount of forum posts for all users.
            $postsCounter = $this->asgarosforum->db->get_results("SELECT author_id, COUNT(id) AS counter FROM {$this->asgarosforum->tables->posts} GROUP BY author_id ORDER BY COUNT(id) DESC;");

            // Change the structure of the results for better searchability.
            $postsCounterSearchable = array();

            foreach ($postsCounter as $postCounter) {
                $postsCounterSearchable[$postCounter->author_id] = $postCounter->counter;
            }

            // Now add the numbers of posts to the users array when they are listed in the post counter.
            foreach ($allUsers as $key => $user) {
                if (isset($postsCounterSearchable[$user->ID])) {
                    $allUsers[$key]->forum_posts = $postsCounterSearchable[$user->ID];
                } else {
                    $allUsers[$key]->forum_posts = 0;
                }
            }

            // Obtain a list of columns for array_multisort().
            $columnForumPosts = array();
            $columnDisplayName = array();

            foreach ($allUsers as $key => $user) {
                $columnForumPosts[$key] = $user->forum_posts;
                $columnDisplayName[$key] = $user->display_name;
            }

            // Ensure case insensitive sorting.
            $columnDisplayName = array_map('strtolower', $columnDisplayName);

            // Now sort the array based on the columns.
            array_multisort($columnForumPosts, SORT_NUMERIC, SORT_DESC, $columnDisplayName, SORT_STRING, SORT_ASC, $allUsers);
        }

        return $allUsers;
    }
}
