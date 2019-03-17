<?php

if (!defined('ABSPATH')) exit;

class AsgarosForumPolls {
    private $asgarosforum = null;

    public function __construct($object) {
        $this->asgarosforum = $object;

        add_action('asgarosforum_editor_custom_content_bottom', array($this, 'render_poll_form'), 10, 1);
        add_action('asgarosforum_after_add_topic_submit', array($this, 'save_poll_form'), 10, 6);
        add_action('asgarosforum_prepare_topic', array($this, 'save_vote'));
    }

    public function render_poll_form($editor_view) {
        // Cancel if poll-functionality is disabled.
        if (!$this->asgarosforum->options['enable_polls']) {
            return;
        }

        // Cancel if we are not in the addtopic editor-view.
        if ($editor_view !== 'addtopic') {
            return;
        }

        echo '<div class="editor-row">';
            echo '<span class="row-title poll-toggle dashicons-before dashicons-chart-pie">'.__('Add Poll', 'asgaros-forum').'</span>';

            echo '<div id="poll-form">';
                echo '<div id="poll-question">';
                    echo '<input class="editor-subject-input" type="text" maxlength="255" name="poll-title" placeholder="'.__('Enter your question here.', 'asgaros-forum').'" value="">';
                echo '</div>';

                echo '<div id="poll-options">';
                    echo '<div class="poll-option-container">';
                        echo '<div class="poll-option-input">';
                            echo '<input class="editor-subject-input" type="text" maxlength="255" name="poll-option[]" placeholder="'.__('An answer ...', 'asgaros-forum').'" value="">';
                        echo '</div>';
                        echo '<div class="poll-option-delete">';
                            echo '<span class="dashicons-before dashicons-trash"></span>';
                        echo '</div>';
                    echo '</div>';

                    echo '<div class="poll-option-container">';
                        echo '<div class="poll-option-input">';
                            echo '<input class="editor-subject-input" type="text" maxlength="255" name="poll-option[]" placeholder="'.__('Another answer ...', 'asgaros-forum').'" value="">';
                        echo '</div>';
                        echo '<div class="poll-option-delete">';
                            echo '<span class="dashicons-before dashicons-trash"></span>';
                        echo '</div>';
                    echo '</div>';
                echo '</div>';

                echo '<label class="checkbox-label">';
                    echo '<input type="checkbox" name="poll-multiple"><span>'.__('Allow multiple answers', 'asgaros-forum').'</span>';
                echo '</label>';
            echo '</div>';
        echo '</div>';
    }

    public function reder_poll_option_container() {
        S
    }

    public function save_poll_form($post_id, $topic_id, $topic_subject, $topic_content, $topic_link, $author_id) {
        // Cancel if poll-functionality is disabled.
        if (!$this->asgarosforum->options['enable_polls']) {
            return;
        }

        // Prepare variables.
        $poll_title = '';
        $poll_options = array();
        $poll_multiple = 0;

        // Cancel if no poll-title is set.
        if (empty($_POST['poll-title'])) {
            return;
        }

        // Trim poll-title and remove tags.
        $poll_title = trim(strip_tags($_POST['poll-title']));

        // Cancel if poll-title is empty.
        if (empty($poll_title)) {
            return;
        }

        // Cancel if no poll-options are set.
        if (empty($_POST['poll-option'])) {
            return;
        }

        // Assign not-empty poll-options to array.
        foreach ($_POST['poll-option'] as $option) {
            $poll_option = trim(strip_tags($option));

            if (!empty($poll_option)) {
                $poll_options[] = $poll_option;
            }
        }

        // Cancel if poll-options are empty.
        if (empty($poll_options)) {
            return;
        }

        // Set multiple-option.
        if (isset($_POST['poll-multiple'])) {
            $poll_multiple = 1;
        }

        // Insert poll.
        $this->asgarosforum->db->insert(
            $this->asgarosforum->tables->polls,
            array('topic_id' => $topic_id, 'title' => $poll_title, 'multiple' => $poll_multiple),
            array('%d', '%s', '%d')
        );

        // Get poll-id.
        $poll_id = $this->asgarosforum->db->insert_id;

        // Insert poll options.
        foreach ($poll_options as $option) {
            $this->asgarosforum->db->insert(
                $this->asgarosforum->tables->polls_options,
                array('poll_id' => $poll_id, 'option' => $option),
                array('%d', '%s')
            );
        }
    }

    public function save_vote() {
        // Cancel if poll-functionality is disabled.
        if (!$this->asgarosforum->options['enable_polls']) {
            return;
        }

        // Ensure that a vote happened.
        if (empty($_POST['poll_action']) || $_POST['poll_action'] !== 'vote') {
            return;
        }

        // Ensure that there is a poll.
        $poll = $this->get_poll($this->asgarosforum->current_topic);


        if ($poll === false) {
            return;
        }

        // Ensure that the user can vote.
        $user_id = get_current_user_id();

        if (!$this->can_vote($user_id, $poll->id)) {
            return;
        }

        // Ensure that an option got selected.
        $votes = (!empty($_POST['poll-option'])) ? $_POST['poll-option'] : false;

        if ($votes === false) {
            return;
        }

        // Ensure that amount of votes represents poll-settings.
        if ($poll->multiple == 0 && count($votes) > 1) {
            return;
        }

        // Ensure that voted options belongs to the poll.
        foreach ($votes as $vote) {
            if (!isset($poll->options[$vote])) {
                return;
            }
        }

        // Save votes in database.
        foreach ($votes as $vote) {
            $this->asgarosforum->db->insert(
                $this->asgarosforum->tables->polls_votes,
                array('option_id' => $vote, 'user_id' => $user_id),
                array('%d', '%d')
            );
        }
    }

    // Checks if a given user voted for a specific poll.
    public function has_voted($user_id, $poll_id) {
        $has_voted = $this->asgarosforum->db->get_var("SELECT COUNT(*) FROM {$this->asgarosforum->tables->polls_options} AS po, {$this->asgarosforum->tables->polls_votes} AS pv WHERE po.poll_id = {$poll_id} AND po.id = pv.option_id AND pv.user_id = {$user_id};");

        if ($has_voted > 0) {
            return true;
        }

        return false;
    }

    // Checks if an user can vote.
    public function can_vote($user_id, $poll_id) {
        // Logged-out users cant vote.
        if ($user_id === 0) {
            return false;
        }

        // Ensure that user has not already voted.
        if ($this->has_voted($user_id, $poll_id)) {
            return false;
        }

        return true;
    }

    public function render_poll($topic_id) {
        // Cancel if poll-functionality is disabled.
        if (!$this->asgarosforum->options['enable_polls']) {
            return;
        }

        // Get poll.
        $poll = $this->get_poll($topic_id);

        // Cancel if there is no poll.
        if ($poll === false) {
            return;
        }

        echo '<div id="poll-panel">';
            echo '<form method="post" action="'.$this->asgarosforum->get_link('topic', $topic_id).'">';
                echo '<div id="poll-headline" class="dashicons-before dashicons-chart-pie">'.esc_html(stripslashes($poll->title)).'</div>';

                // Get id of the current user.
                $user_id = get_current_user_id();

                // Show vote-panel when user can vote.
                if ($this->can_vote($user_id, $poll->id)) {
                    echo '<div id="poll-vote">';
                        foreach ($poll->options as $option) {
                            echo '<label class="checkbox-label">';

                            if ($poll->multiple == 1) {
                                echo '<input type="checkbox" name="poll-option[]" value="'.$option->id.'"><span>'.$option->option.'</span>';
                            } else {
                                echo '<input type="radio" name="poll-option[]" value="'.$option->id.'"><span>'.$option->option.'</span>';
                            }

                            echo '</label>';
                        }
                    echo '</div>';
                } else {
                    echo '<div id="poll-results">';
                        foreach ($poll->options as $key => $option) {
                            $percentage = ($option->votes / $poll->total_votes) * 100;
                            $percentage_css = number_format($percentage, 2);

                            echo '<div class="poll-result-row">';
                                echo '<div class="poll-result-name">';
                                    echo $option->option;
                                    echo '<span class="poll-result-numbers">';
                                        echo '<small class="poll-result-votes">'.number_format_i18n($option->votes).'</small>';
                                        echo '<small class="poll-result-percentage">'.number_format_i18n($percentage, 2).'%</small>';
                                    echo '</span>';
                                echo '</div>';

                                echo '<div class="poll-result-bar">';
                                    echo '<div class="poll-result-filling" style="width: '.$percentage_css.'%; background-color: '.$this->get_bar_color().';"></div>';
                                echo '</div>';

                            echo '</div>';
                        }
                    echo '</div>';
                }

                echo '<div class="actions">';
                    echo '<input type="hidden" name="poll_action" value="vote">';
                    echo '<input type="submit" value="Vote">';
                echo '</div>';
            echo '</form>';
        echo '</div>';
    }

    public function get_poll($topic_id) {
        // Try to get the poll for the given topic first.
        $poll = $this->asgarosforum->db->get_row("SELECT * FROM {$this->asgarosforum->tables->polls} WHERE topic_id = {$topic_id};");

        // Cancel if there is no poll for the given topic.
        if (!$poll) {
            return false;
        }

        // Get options and votes for the poll.
        $poll->options = $this->asgarosforum->db->get_results("SELECT po.id, po.option, (SELECT COUNT(*) FROM {$this->asgarosforum->tables->polls_votes} AS pv WHERE pv.option_id = po.id) AS votes FROM {$this->asgarosforum->tables->polls_options} AS po WHERE po.poll_id = {$poll->id};", 'OBJECT_K');

        // Get total votes.
        // TODO: Wront total votes value. Group by users.
        $poll->total_votes = $this->asgarosforum->db->get_var("SELECT COUNT(*) FROM {$this->asgarosforum->tables->polls_options} AS po, {$this->asgarosforum->tables->polls_votes} AS pv WHERE po.poll_id = {$poll->id} AND po.id = pv.option_id;");

        return $poll;
    }

    private $get_bar_color_counter = 0;
    public function get_bar_color() {
        $this->get_bar_color_counter++;

        $colors = array();
        $colors[] = '#4661EE';
        $colors[] = '#EC5657';
        $colors[] = '#1BCDD1';
        $colors[] = '#8FAABB';
        $colors[] = '#B08BEB';
        $colors[] = '#3EA0DD';
        $colors[] = '#F5A52A';
        $colors[] = '#23BFAA';
        $colors[] = '#FAA586';
        $colors[] = '#EB8CC6';

        return $colors[$this->get_bar_color_counter % 10];
    }
}
