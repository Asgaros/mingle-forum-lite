<?php
$this->editor_settings['textarea_rows'] = 12;
$thread = "";
$post = "";
$t = "";
$q = "";

if ($_GET['forumaction'] == "addtopic") {
    if (!$this->forum_exists($_GET['forum'])) {
        wp_die(__("Sorry, this forum does not exist.", "asgarosforum"));
    }

    if (!$user_ID) {
        wp_die(__("Sorry, you don't have permission to post.", "asgarosforum"));
    }
}

if ($_GET['forumaction'] == "postreply") {
    if (!$user_ID) {
        wp_die(__("Sorry, you don't have permission to post.", "asgarosforum"));
    }

    $thread = $this->check_parms($_GET['thread']);

    if (isset($_GET['quote'])) {
        $quote_id = $this->check_parms($_GET['quote']);
        $text = $wpdb->get_row($wpdb->prepare("SELECT text, author_id, date FROM {$this->table_posts} WHERE id = %d", $quote_id));
        $display_name = $this->get_userdata($text->author_id, $this->options['forum_display_name']);
        $q = "<blockquote><div class='quotetitle'>" . __("Quote from", "asgarosforum") . " " . $display_name . " " . __("on", "asgarosforum") . " " . $this->format_date($text->date) . "</div>" . $text->text . "</blockquote><br />";
    }
}

if ($_GET['forumaction'] == "editpost") {
    if (!$user_ID) {
        wp_die(__("Sorry, you don't have permission to post.", "asgarosforum"));
    }

    $id = (isset($_GET['id']) && !empty($_GET['id'])) ? (int)$_GET['id'] : 0;
    $thread = $this->check_parms($_GET['t']);
    $t = $wpdb->get_row($wpdb->prepare("SELECT subject FROM {$this->table_threads} WHERE id = %d", $thread));
    $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_posts} WHERE id = %d", $id));

    if (!($user_ID == $post->author_id && $user_ID) && !$this->is_moderator($user_ID)) {
        wp_die("Sorry, you are not allowed to edit this post.", "asgarosforum");
    }
}

?>

<form name='addform' method='post' enctype='multipart/form-data'>
    <div class='title-element'>
        <?php
        if ($_GET['forumaction'] == "addtopic") {
            _e("Post new Topic", "asgarosforum");
        } else if ($_GET['forumaction'] == "postreply") {
            echo __("Post Reply:", "asgarosforum") . ' ' . $this->get_subject($thread);
        } else if ($_GET['forumaction'] == "editpost") {
            echo __("Edit Post:", "asgarosforum") . ' ' . stripslashes($t->subject);
        }
        ?>
    </div>
    <div class='content-element editor'>
        <table>
            <?php if ($_GET['forumaction'] == "addtopic") { ?>
            <tr>
                <td><?php _e("Subject:", "asgarosforum"); ?></td>
                <td><input type='text' name='add_topic_subject' /></td>
            </tr>
            <?php } ?>
            <?php
            /*if(false) //Need to enable this eventually if we're editing the first post in the thread
            echo "<tr>
            <td>" . __("Subject:", "asgarosforum") . "</td>
            <td><input size='50%' type='text' name='edit_post_subject' value='" . stripslashes($t->subject) . "'/></td>
            </tr>";*/
            ?>
            <tr>
                <td><?php _e("Message:", "asgarosforum"); ?></td>
                <td>
                    <?php
                    if ($_GET['forumaction'] == "editpost") {
                        wp_editor(stripslashes($post->text), 'message', $this->editor_settings);
                    } else {
                        wp_editor($q, 'message', $this->editor_settings);
                    }
                    ?>
                </td>
            </tr>
            <?php
            if ($_GET['forumaction'] != "editpost" && $this->options['forum_allow_image_uploads']) { ?>
    		<tr>
    			<td><?php _e("Images:", "asgarosforum"); ?></td>
    			<td>
    				<input type='file' name='mfimage1' /><br/>
    				<input type='file' name='mfimage2' /><br/>
    				<input type='file' name='mfimage3' />
    			</td>
    		</tr>
            <?php } ?>
            <tr>
                <td></td>
                <?php if ($_GET['forumaction'] == "addtopic") { ?>
                    <td>
                        <input type='submit' name='add_topic_submit' value='<?php _e("Submit", "asgarosforum"); ?>' />
                        <input type='hidden' name='add_topic_forumid' value='<?php echo $this->check_parms($_GET['forum']); ?>' />
                    </td>
                <?php } else if ($_GET['forumaction'] == "postreply") { ?>
                    <td>
                        <input type='submit' name='add_post_submit' value='<?php _e("Submit", "asgarosforum"); ?>' />
                        <input type='hidden' name='add_post_forumid' value='<?php echo $thread; ?>' />
                    </td>
                <?php } else if ($_GET['forumaction'] == "editpost") { ?>
                    <td>
                        <input type='submit' name='edit_post_submit' value='<?php _e("Submit", "asgarosforum"); ?>' />
                        <input type='hidden' name='edit_post_id' value='<?php echo $post->id; ?>' />
                        <input type='hidden' name='thread_id' value='<?php echo $thread; ?>' />
                        <input type='hidden' name='page_id' value='<?php echo $this->current_page; ?>' />
                    </td>
                <?php } ?>
            </tr>
        </table>
    </div>
</form>
