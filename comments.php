<?php
// 如果需要密码则直接返回
if (post_password_required()) {
    return;
}

// 定义辅助函数生成表情面板
function get_smilies_panel() {
    $smilies_list = iro_opt('smilies_list');
    if (!$smilies_list) {
        return '';
    }
    
    $bilibili_smilies = $tieba_smilies = $menhera_smilies = $custom_smilies = '';
    $bilibili_push_smilies = $tieba_push_smilies = $menhera_push_smilies = $custom_push_smilies = '';

    if (in_array('bilibili', $smilies_list)) {
        $bilibili_smilies = '<th class="bili-bar">bilibili~</th>';
        $bilibili_push_smilies = '<div class="bili-container motion-container" style="display:none;">' . push_bili_smilies() . '</div>';
    }
    if (in_array('tieba', $smilies_list)) {
        $tieba_smilies = '<th class="tieba-bar">Tieba</th>';
        $tieba_push_smilies = '<div class="tieba-container motion-container" style="display:none;">' . push_tieba_smilies() . '</div>';
    }
    if (in_array('yanwenzi', $smilies_list)) {
        $menhera_smilies = '<th class="menhera-bar">(=・ω・=)</th>';
        $menhera_push_smilies = '<div class="menhera-container motion-container" style="display:none;">' . push_emoji_panel() . '</div>';
    }
    if (in_array('custom', $smilies_list)) {
        $custom_smilies = '<th class="custom-bar"> ' . iro_opt('smilies_name') . ' </th>';
        $custom_push_smilies = '<div class="custom-container motion-container" style="display:none;">' . push_custom_smilies() . '</div>';
    }
    // 根据第一个选项设置默认展示
    switch ($smilies_list[0]) {
        case "bilibili":
            $bilibili_smilies = '<th class="bili-bar on-hover">bilibili~</th>';
            $bilibili_push_smilies = '<div class="bili-container motion-container" style="display:block;">' . push_bili_smilies() . '</div>';
            break;
        case "tieba":
            $tieba_smilies = '<th class="tieba-bar on-hover">Tieba</th>';
            $tieba_push_smilies = '<div class="tieba-container motion-container" style="display:block;">' . push_tieba_smilies() . '</div>';
            break;
        case "yanwenzi":
            $menhera_smilies = '<th class="menhera-bar on-hover">(=・ω・=)</th>';
            $menhera_push_smilies = '<div class="menhera-container motion-container" style="display:block;">' . push_emoji_panel() . '</div>';
            break;
        case "custom":
            $custom_smilies = '<th class="custom-bar on-hover"> ' . iro_opt('smilies_name') . ' </th>';
            $custom_push_smilies = '<div class="custom-container motion-container" style="display:block;">' . push_custom_smilies() . '</div>';
            break;
    }
    return '<div class="emotion-box no-select">
                <div class="emotion-header no-select">' . __("Woooooow ヾ(≧∇≦*)ゝ", "sakurairo") . '</div>
                <table class="motion-switcher-table">
                    <tr>' .
                        $bilibili_smilies .
                        $tieba_smilies .
                        $menhera_smilies .
                        $custom_smilies .
                    '</tr>
                </table>' .
                $bilibili_push_smilies .
                $tieba_push_smilies .
                $menhera_push_smilies .
                $custom_push_smilies .
            '</div>';
}
?>

<?php if (comments_open()) : ?>
<section id="comments" class="comments">
    <!-- 评论区域标题及折叠通知 -->
    <div class="commentwrap comments-hidden<?php echo iro_opt('comment_area') == 'fold' ? ' comments-fold' : ''; ?>">
            <div class="notification">
                <i class="fa-regular fa-comment"></i><?php _e('view comments', 'sakurairo'); /*查看评论*/?> -
                <span class="noticom">
                    <?php
                    $approved_comments = get_comments(array(
                        'post_id' => get_the_ID(),
                        'status'  => 'approve',
                        'count'   => true
                    ));
                    if ($approved_comments == 0) {
                        _e('NOTHING', 'sakurairo');
                    } elseif ($approved_comments == 1) {
                        echo '1' . __(" comment", "sakurairo");
                    } else {
                        echo $approved_comments . __(" comments", "sakurairo");
                    }
                    ?>
                </span>
            </div>
    </div>
    
    <div class="comments-main">
        <h3 id="comments-list-title">
            Comments <span class="noticom"><?php comments_number('NOTHING', '1' . __(" comment", "sakurairo"), '%' . __(" comments", "sakurairo")); ?></span>
        </h3>
        <div id="loading-comments"><span></span></div>
        
        <?php if (have_comments()) : ?>
            <ul class="commentwrap">
                <?php wp_list_comments(array(
                    "type" => "comment",
                    "callback" => "akina_comment_format",
                    "reverse_top_level" => get_option('comment_order') == 'asc' ? null : true
                )); ?>
            </ul>
            <nav id="comments-navi">
                <?php paginate_comments_links('prev_text=« Older&next_text=Newer »'); ?>
            </nav>
        <?php else : ?>
            <?php if (comments_open()) : ?>
                <div class="commentwrap">
                    <div class="notification-hidden">
                        <i class="fa-regular fa-comment"></i>
                        <?php _e('no comment', 'sakurairo'); /*暂无评论*/ ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php
        if (comments_open()) {
            // 获取当前登录状态
            $is_logged_in = is_user_logged_in();
            
            // 私密评论选项
            $private_ms = iro_opt('comment_private_message')
                ? '<label class="siren-checkbox-label"><input class="siren-checkbox-radio" type="checkbox" name="is-private"><span class="siren-is-private-checkbox siren-checkbox-radioInput"></span>' . __('Comment in private', 'sakurairo') . '</label>'
                : '';
            // 邮件通知选项
            $mail_notify = iro_opt('mail_notify')
                ? '<label class="siren-checkbox-label"><input class="siren-checkbox-radio" type="checkbox" name="mail-notify"><span class="siren-mail-notify-checkbox siren-checkbox-radioInput"></span>' . __('Comment reply notify', 'sakurairo') . '</label>'
                : '';
                
            // 验证码 - 只有开启且未登录时才显示
            $comment_captcha = '';
            if (iro_opt('pca_captcha') && !$is_logged_in) {
                include_once('inc/classes/Captcha.php');
                $img = new Sakura\API\Captcha;
                $test = $img->create_captcha_img();
                $captcha_placeholder = __("Click here to show captcha", "sakurairo");
                $comment_captcha = '
                    <label for="captcha" class="comment-captcha">
                        <img id="captchaimg" alt="captcha" onclick="refreshCaptcha()" width="120" height="40" style="width: 0px;margin-right: 0px;" src="' . htmlspecialchars($test['data'], ENT_QUOTES, 'UTF-8') . '">
                        <input type="text" onfocus="showCaptcha();" onblur="hideCaptcha()" name="captcha" id="captcha" class="input" value="" size="20" tabindex="4" placeholder="' . $captcha_placeholder . '">
                        <input type="hidden" name="timestamp" value="' . htmlspecialchars($test['time'], ENT_QUOTES, 'UTF-8') . '">
                        <input type="hidden" name="id" value="' . htmlspecialchars($test['id'], ENT_QUOTES, 'UTF-8') . '">
                    </label>
                <script>
                    let captchaHideTimeout = null;
                    const captchaField = document.getElementById("captcha");
                    const captchaImg = document.getElementById("captchaimg");
                    function showCaptcha() {
                        captchaField.setAttribute("placeholder", "");
                        if (captchaHideTimeout) { clearTimeout(captchaHideTimeout); captchaHideTimeout = null; }
                        captchaImg.style.width = "120px";
                        captchaImg.style.marginRight = "10px";
                    }
                    function hideCaptcha() {
                        captchaHideTimeout = setTimeout(function() {
                            captchaImg.style.width = "0";
                            captchaImg.style.marginRight = "0";
                            captchaField.setAttribute("placeholder", "'. $captcha_placeholder .'");
                        }, 5000);
                    }
                    function refreshCaptcha() {
                        fetch(_iro.captcha_endpoint)
                            .then(function(resp) { return resp.json(); })
                            .then(function(json) {
                                captchaImg.src = json["data"];
                                document.querySelector("input[name=\'timestamp\']").value = json["time"];
                                document.querySelector("input[name=\'id\']").value = json["id"];
                            })
                            .catch(function(error) { console.error("获取验证码失败:", error); });
                    }
                </script>';
            }
            
            // 调用辅助函数生成表情面板
            $smilies_box = get_smilies_panel();
            $smilies_button = '';
            if (iro_opt('smilies_list')) {
                $smilies_button = '<div id="emotion-toggle" class="no-select"><i class="fa-regular fa-face-kiss-wink-heart"></i></div>';
            }
            $img_upload = '';
            if (iro_opt('img_upload_api', false) !== 'off') {
                $img_upload = '<label class="insert-image-tips popup">
                                    <i class="fa-regular fa-image"></i>
                                    <span class="insert-img-popuptext" id="uploadTipPopup">上传图片</span>
                                    <input id="upload-img-file" type="file" accept="image/*" multiple="multiple" class="insert-image-button">
                                </label>';
            }

            // 根据登录状态构建不同的评论表单
            if ($is_logged_in) {
                // === 已登录用户的评论表单 ===
                $current_user = wp_get_current_user();
                $user_avatar = get_avatar_url($current_user->ID, ['size' => 80]);
                
                // 登录用户的头像放在底部按钮区域
                $logged_in_avatar = '<div class="comment-user-avatar logged-in-avatar">
                                        <img alt="comment_user_avatar" src="' . esc_url($user_avatar) . '">
                                        <div class="socila-check gravatar-check" style="display:block;"><i class="fa-solid fa-heart fa-xs"></i></div>
                                     </div>';
                
                // 登录用户的选项区域（验证码位置）
                $logged_in_options = '<div class="comment-checks logged-in-checks">' . $private_ms . $mail_notify . '</div>';
                
                $args = array(
                    'id_form'           => 'commentform',
                    'id_submit'         => 'submit',
                    'title_reply'       => '',
                    'title_reply_to'    => '<div class="graybar"><i class="fa-regular fa-comment"></i>' . __('Leave a Reply to', 'sakurairo') . ' %s</div>',
                    'cancel_reply_link' => __('Cancel Reply', 'sakurairo'),
                    'label_submit'      => esc_attr(iro_opt('comment_submit_button_text')),
                    'logged_in_as'      => '', // 清空默认的登录信息
                    'comment_field'     => '<div class="comment-textarea">
                                                <textarea placeholder="' . esc_attr(iro_opt('comment_placeholder_text')) . '" name="comment" class="commentbody" id="comment" rows="5" tabindex="4"></textarea>
                                                <label class="input-label">' . esc_html(iro_opt('comment_placeholder_text')) . '</label>
                                            </div>' . $smilies_box . 
                                            '<div id="upload-img-show"></div>',
                    'submit_button'     => '<div class="cmt-submit-container logged-in-submit">' . $logged_in_avatar . $logged_in_options . 
                                           '<div class="form-submit">
                                                <input name="submit" type="submit" id="submit" class="submit" value=" ' . esc_attr(iro_opt('comment_submit_button_text')) . ' ">' . $smilies_button . $img_upload . '
                                                <label class="markdown-toggle">
                                                    <input type="checkbox" id="enable_markdown" name="enable_markdown">
                                                    <i class="fa-brands fa-markdown fa-sm"></i>
                                                </label>
                                            </div></div>',
                    'comment_notes_after'  => '',
                    'comment_notes_before' => '',
                    'fields'            => array() // 登录用户不需要这些字段
                );
            } else {
                // === 未登录用户的评论表单 ===
                // 构建comment-checks区域的内容
                $checks_content = '<div class="comment-checks">' . $comment_captcha . $private_ms . $mail_notify;
                
                $args = array(
                    'id_form'           => 'commentform',
                    'id_submit'         => 'submit',
                    'title_reply'       => '',
                    'title_reply_to'    => '<div class="graybar"><i class="fa-regular fa-comment"></i>' . __('Leave a Reply to', 'sakurairo') . ' %s</div>',
                    'cancel_reply_link' => __('Cancel Reply', 'sakurairo'),
                    'label_submit'      => esc_attr(iro_opt('comment_submit_button_text')),
                    'comment_field'     => '<div class="comment-textarea">
                                                <textarea placeholder="' . esc_attr(iro_opt('comment_placeholder_text')) . '" name="comment" class="commentbody" id="comment" rows="5" tabindex="4"></textarea>
                                                <label class="input-label">' . esc_html(iro_opt('comment_placeholder_text')) . '</label>
                                            </div>' . $smilies_box . 
                                            '<div id="upload-img-show"></div>',
                    'submit_button'     => '<div class="form-submit">
                                                <input name="submit" type="submit" id="submit" class="submit" value=" ' . esc_attr(iro_opt('comment_submit_button_text')) . ' ">' . $smilies_button . $img_upload . '
                                                <label class="markdown-toggle">
                                                    <input type="checkbox" id="enable_markdown" name="enable_markdown">
                                                    <i class="fa-brands fa-markdown fa-sm"></i>
                                                </label>
                                            </div>',
                    'comment_notes_after'  => '',
                    'comment_notes_before' => '',
                    'fields'            => apply_filters('comment_form_default_fields', array(
                        'avatar' => '<div class="cmt-info-container"><div class="comment-user-avatar">
                                        <img alt="comment_user_avatar" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48IS0tIUZvbnQgQXdlc29tZSBGcmVlIDYuNy4yIGJ5IEBmb250YXdlc29tZSAtIGh0dHBzOi8vZm9udGF3ZXNvbWUuY29tIExpY2Vuc2UgLSBodHRwczovL2ZvbnRhd2Vzb21lLmNvbS9saWNlbnNlL2ZyZWUgQ29weXJpZ2h0IDIwMjUgRm9udGljb25zLCBJbmMuLS0+PHBhdGggZmlsbD0iIzgwODA4MCIgZD0iTTM5OSAzODQuMkMzNzYuOSAzNDUuOCAzMzUuNCAzMjAgMjg4IDMyMGwtNjQgMGMtNDcuNCAwLTg4LjkgMjUuOC0xMTEgNjQuMmMzNS4yIDM5LjIgODYuMiA2My44IDE0MyA2My44czEwNy44LTI0LjcgMTQzLTYzLjh6TTAgMjU2YTI1NiAyNTYgMCAxIDEgNTEyIDBBMjU2IDI1NiAwIDEgMSAwIDI1NnptMjU2IDE2YTcyIDcyIDAgMSAwIDAtMTQ0IDcyIDcyIDAgMSAwIDAgMTQ0eiIvPjwvc3ZnPg==">
                                        <div class="socila-check qq-check"><i class="fa-brands fa-qq fa-xs"></i></div>
                                        <div class="socila-check gravatar-check"><i class="fa-solid fa-heart fa-xs"></i></div>
                                     </div>',
                        'author' => '<div class="popup cmt-popup cmt-author">
                                        <input type="text" placeholder="' . __("Nickname or QQ number", "sakurairo") . ' ' . ($req ? '(' . __("Must* ", "sakurairo") . ')' : '') . '" name="author" id="author" value="' . esc_attr($comment_author) . '" size="22" autocomplete="off" tabindex="1" ' . ($req ? "aria-required='true'" : '') . ' />
                                        <span class="popuptext" style="margin-left: -115px;width: 230px;">' . __("Auto pull nickname and avatar with a QQ num. entered", "sakurairo") . '</span>
                                     </div>',
                        'email'  => '<div class="popup cmt-popup">
                                        <input type="text" placeholder="' . __("email", "sakurairo") . ' ' . ($req ? '(' . __("Must* ", "sakurairo") . ')' : '') . '" name="email" id="email" value="' . esc_attr($comment_author_email) . '" size="22" tabindex="1" autocomplete="off" ' . ($req ? "aria-required='true'" : '') . ' />
                                        <span class="popuptext" style="margin-left: -65px;width: 130px;">' . __("You will receive notification by email", "sakurairo") . '</span>
                                     </div>',
                        'url'    => '<div class="popup cmt-popup">
                                        <input type="text" placeholder="' . __("Site", "sakurairo") . '" name="url" id="url" value="' . esc_attr($comment_author_url) . '" size="22" autocomplete="off" tabindex="1" />
                                        <span class="popuptext" style="margin-left: -55px;width: 110px;">' . __("Advertisement is forbidden 😀", "sakurairo") . '</span>
                                     </div></div>',
                        'qq'     => '<input type="text" placeholder="QQ" name="new_field_qq" id="qq" value="' . esc_attr($comment_author_url) . '" style="display:none" autocomplete="off"/>',
                        'checks' => $checks_content, // 此处不闭合，和保存信息在一层级一起闭合
                    ))
                );
                
                // 未登录用户显示保存信息选项，并关闭checks的div
            }
            
            // 过滤cookies字段（仅对未登录用户生效）
            if (!$is_logged_in) {
                add_filter('comment_form_field_cookies', function($field) {
                    return '<label class="siren-checkbox-label">
                                <input class="siren-checkbox-radio" id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes">
                                <span class="siren-mail-notify-checkbox siren-checkbox-radioInput"></span>
                                ' . __('Save your private info', 'sakurairo') . '
                            </label></div>';
                }, 99);
            }

            comment_form($args);
        }
        ?>

    </div>
    
</section>
<?php endif; ?>
