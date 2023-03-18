<?php namespace BBP_MESSAGES\Inc\Core;

class Init
{
    public $profile_tab, $rewrite_rules, $bases, $loop_chats, $widgets;

    public function __construct()
    {
        $this->bases = array(
            'rewrite_base' => '{bbp_user_slug}/([^/]+)/',
            'messages_base' => 'messages',
            'page_base' => 'page',
            'settings_base' => 'settings',
            'new' => 'new',
            'with' => 'w'
        );

        $this->rewrite_rules = array(
            // index
            '{rewrite_base}{messages_base}/?$' => 'index.php?bbp_user=$matches[1]&BPT_tab=messages&bbpm_chats=1&bbpm_page=$matches[3]',
            '{rewrite_base}{messages_base}/{page_base}/([0-9]+)/?$' => 'index.php?bbp_user=$matches[1]&BPT_tab=messages&bbpm_chats=1&bbpm_page=$matches[2]',
            '{rewrite_base}{messages_base}/send/?$' => 'index.php?bbp_user=$matches[1]&BPT_tab=messages&bbpm_chats=1&bbpm_send=1',
            // new
            '{rewrite_base}{messages_base}/{new}/?$' => 'index.php?bbp_user=$matches[1]&BPT_tab=messages&bbpm_new=1',
            // with redirect
            '{rewrite_base}{messages_base}/{with}/([^/]+)/?$' => 'index.php?bbp_user=$matches[1]&BPT_tab=messages&bbpm_with=$matches[2]',
            // single chat
            '{rewrite_base}{messages_base}/([^/]+)/?$' => 'index.php?bbp_user=$matches[1]&BPT_tab=messages&bbpm_chat=$matches[2]',
            '{rewrite_base}{messages_base}/([^/]+)/{settings_base}/?$' => 'index.php?bbp_user=$matches[1]&BPT_tab=messages&bbpm_chat=$matches[2]&bbpm_settings=1',
            '{rewrite_base}{messages_base}/([^/]+)/actions/?$' => 'index.php?bbp_user=$matches[1]&BPT_tab=messages&bbpm_chat=$matches[2]&bbpm_actions=1',
            '{rewrite_base}{messages_base}/([^/]+)/{page_base}/([0-9]+)/?$' => 'index.php?bbp_user=$matches[1]&BPT_tab=messages&bbpm_chat=$matches[2]&bbpm_page=$matches[3]',
        );

        $this->query_vars = array(
            'bbpm_chat',
            'bbpm_page',
            'bbpm_chats',
            'bbpm_settings',
            'bbpm_new',
            'bbpm_with',
            'bbpm_send',
            'bbpm_actions'
        );

        $this->loop_chats = array(
            'chats' => array(),
            'current_index' => -1,
            'current_chat' => null
        );

        $this->widgets = array(
            '\BBP_MESSAGES\Inc\Core\Widgets\Welcome',
            '\BBP_MESSAGES\Inc\Core\Widgets\NewMessage',
            '\BBP_MESSAGES\Inc\Core\Widgets\MyChats',
            '\BBP_MESSAGES\Inc\Core\Widgets\MyMessages',
            '\BBP_MESSAGES\Inc\Core\Widgets\Search',
            '\BBP_MESSAGES\Inc\Core\Widgets\MyContacts'
        );
    }

    public function init()
    {
        global $bbpm_bases;
        $bbpm_bases = $this->bases;

        // process /messages/send request
        if ( !is_admin() && $_POST ) {
            add_action('init', array($this, 'parseSend'));
            add_action('init', array($this, 'parseSingleActions'));
        }
        // setup profile tab
        $this->setupProfileTab();
        // add rewrite rules
        add_action('init', array($this, 'rewriteRules'));
        // start buffer
        add_action('init', array($this, 'obStart'));
        // register scripts
        add_action('wp_enqueue_scripts', array($this, 'registerScripts'));
        // query vars
        add_filter('query_vars', array($this, 'queryVars'));
        // parse query
        add_action('wp', array($this, 'parseQuery'));
        // enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'));
        // load template
        add_action('BPT_content-messages', array($this, 'loadTemplate'));
        // parse errors
        add_action('bbpm_template_head', array($this, 'parseTempalteErrors'));
        // filter message data 
        add_filter('bbpm_message_data', array($this, 'filterMessageData'));
        // filter message text
        add_filter('bbpm_message', 'esc_html', 10);
        add_filter('bbpm_message', 'esc_attr', 11);
        add_filter('bbpm_message', 'bbpm_parse_images_bb', 12, 2);
        add_filter('bbpm_message', 'bbpm_parse_images', 12, 2);
        add_filter('bbpm_message', 'bbpm_parse_youtube', 12, 2);
        add_filter('bbpm_message', 'make_clickable', 13);
        add_filter('bbpm_message', 'links_add_target', 13);
        add_filter('bbpm_message', 'wpautop', 14);
        add_filter('bbpm_message', 'bbpm_pending_delete', 14, 2);
        add_filter('bbpm_message', 'convert_smilies', 15);
        add_filter('bbpm_message', 'force_balance_tags', 15);
        add_filter('bbpm_message', 'convert_chars', 15);
        add_filter('bbpm_message', 'wptexturize', 15);
        add_filter('bbpm_message', 'wp_unslash', 15);
        // filter excerpt text
        add_filter('bbpm_excerpt', 'esc_html', 10);
        add_filter('bbpm_excerpt', 'esc_attr', 11);
        add_filter('bbpm_excerpt', 'convert_smilies', 12);
        add_filter('bbpm_excerpt', 'force_balance_tags', 12);
        add_filter('bbpm_excerpt', 'convert_chars', 12);
        add_filter('bbpm_excerpt', 'wptexturize', 12);
        add_filter('bbpm_excerpt', 'bbpm_pending_delete_excerpt', 14, 2);
        add_filter('bbpm_excerpt', 'wp_unslash', 14);
        // unslash search term
        add_filter('bbpm_search_query', 'wp_unslash', 11);
        add_filter('bbpm_search_query', 'esc_attr', 12);
        // check can contact
        add_action('bbpm_init_pre_send_message', array($this, 'checkCanContact'));
        /* filter title in modern themes that omit the use of wp_title */
        add_filter('pre_get_document_title', 'bbpm_get_dynamic_page_title', 98);
        /* filter title in themes that use wp_title */
        add_filter('wp_title', 'bbpm_get_dynamic_page_title', 98);
        /* filter title in modern themes that omit the use of wp_title */
        add_filter('pre_get_document_title', 'bbpm_get_dynamic_page_title_add_count', 99);
        /* filter title in themes that use wp_title */
        add_filter('wp_title', 'bbpm_get_dynamic_page_title_add_count', 99);
        // unslash mail body
        add_filter('bbpm_notification_body', 'wp_unslash');

        // load integrations
        $this->loadIntegrate();

        // initiate widgets
        add_action('widgets_init', array($this, 'widgetsInit'));
        // enqueue main style.css for widgets
        add_action('bbpm_widget_start_output', array($this, 'enqueueStyleCSS'));
        // widget errors
        add_action('bbpm_widget_new_message_start_output', array($this, 'parseNewMessageWidgetErrors'));
        // unslash
        add_filter('bbpm_old_string', 'wp_unslash');
        // filter menu items HTML
        add_filter('wp_nav_menu_items', array($this, 'fitlerMenuItems'), 10, 2);

        do_action('bbpm_loaded', $this);
    }

    public function parseSend()
    {
        global $bbpm_bases;


        if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
            $pattern = sprintf(
                '/%s\/([^(*|\s|\/)]*)\/%s\/send[\/]?$/i',
                str_replace('/', '\/', bbp_get_user_slug()),
                $bbpm_bases['messages_base']
            );
        } else {
            $pattern = sprintf(
                '/%s\/([^(\z|\s|\/)]*)\/%s\/send[\/]?$/i',
                str_replace('/', '\/', bbp_get_user_slug()),
                $bbpm_bases['messages_base']
            );
        }

        $uri = $_SERVER['REQUEST_URI'];

        $parsed = parse_url($uri);

        if ( isset($parsed['path']) ) {
            $uri = $parsed['path'];
        }

        if ( !preg_match($pattern, $uri) )
            return;

        $redirect = isset($_POST['redirect_to']) ? esc_url($_POST['redirect_to']) : esc_url(
            str_replace(array('send/', 'send'), '', (
                !empty( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : $uri
            ))
        );

        if ( !bbpm_verify_nonce('send_message') ) {
            return bbpm_redirect($redirect, 1, 0)->withNotice(array(
                'nonce',
                __('Error: Bad authentication!', 'bbp-messages'),
                'error'
            ));
        }

        $Messages = bbpm_messages();

        if ( !is_object($Messages) ) {
            return bbpm_redirect($redirect, 1, 0)->withNotice(array(
                'wp_messages',
                __('Error occured processing your request.', 'bbp-messages'),
                'error'
            ));
        }

        if ( empty($Messages->current_user) ) {
            return bbpm_redirect($redirect, 1, 0)->withNotice(array(
                'current_user',
                __('Error: Could not find a sender for this message!', 'bbp-messages'),
                'error'
            ));
        }

        $msg = $chat_id = null;

        if ( !empty( $_POST['chat_id'] ) ) {
            if ( $Messages->isChatRecipient($Messages->current_user, sanitize_text_field($_POST['chat_id'])) ) {
                $chat_id = sanitize_text_field($_POST['chat_id']);
            }
        }

        if ( !$chat_id && !empty($_POST['recipient']) ) {
            $_recipient = intval($_POST['recipient']);

            if ( !get_userdata($_recipient) ) {
                return bbpm_redirect($redirect, 1, 0)->withNotice(array(
                    'recipient',
                    __('Error: Invalid recipient specified!', 'bbp-messages'),
                    'error'
                ));
            } else {
                $chat_id = $Messages->getPrivateSharedChat($Messages->current_user, $_recipient);

                if ( !$chat_id ) {
                    do_action('bbpm_new_pre_generate_chat_id', $_recipient);

                    if ( !bbpm_has_errors() ) {
                        $chat_id = $Messages->set('chat_id', null)->getOrGenerateChatId();
                        $Messages->set('chat_id', $chat_id)->addChatRecipient(array($Messages->current_user, $_recipient));
                    }
                }
            }
        }

        $chat_id = apply_filters('bbpm_send_get_chat_id', $chat_id);

        if ( !trim($chat_id) ) {
            return bbpm_redirect($redirect, 1, 0)->withNotice(array(
                'chat_id',
                __('Error: Could not find a chat ID for this message!', 'bbp-messages'),
                'error'
            ));
        }

        if ( isset($_POST['message']) && trim($_POST['message']) ) {
            $msg = $_POST['message'];
        }

        $args = apply_filters('bbpm_init_pre_send_message_args', array(
            'chat_id' => $chat_id,
            'redirect_to' => $redirect,
            'sender' => $Messages->current_user,
            'message' => $msg
        ));

        if ( !$args['message'] ) {
            bbpm_add_error('empty_message', 'Error: Could not send an empty message!', 'error');
        }

        do_action('bbpm_init_pre_send_message', $args);

        if ( bbpm_has_errors() ) {
            return bbpm_redirect($args['redirect_to'], 1, 0)->withNotice(bbpm_get_errors_array());
        }

        $Messages->set(array(
            'chat_id' => $args['chat_id'],
            'message' => apply_filters('bbpm_pre_insert_message_str', $args['message'])
        ))->notify(true)->send();

        preg_match($pattern, $uri, $m);
        
        if ( isset($m[1]) ) {
            // hardcoded
            #$chatUri = site_url(bbp_get_user_slug() . '/' . $m[1] . '/' . $bbpm_bases['messages_base'] . '/' . $chat_id . '/');
            $chatUri = bbpm_messages_url($chat_id, $Messages->current_user);
        }

        if ( $Messages->isSent() ) {
            if ( isset($chatUri) ) {
                $args['redirect_to'] = $chatUri;
            }

            else if ( !strpos($args['redirect_to'], $chat_id) ) {
                $args['redirect_to'] = add_query_arg('sent', 1, $args['redirect_to']);   
            }

            return bbpm_redirect($args['redirect_to'], 1);
        } else {
            if ( $Messages->errors->get_error_codes() ) {
                $errors = array();

                foreach ( $Messages->errors->get_error_codes() as $code ) {
                    if ( $Messages->errors->get_error_messages($code) ) {
                        foreach ( $Messages->errors->get_error_messages($code) as $message ) {
                            $errors[] = array(
                                $code,
                                !is_array($message) ? $message : implode('<br/>', $message),
                                'error'
                            );
                        }
                    }
                }

                if ( !strpos($args['redirect_to'], $chat_id) ) {
                    $args['redirect_to'] = add_query_arg('error', 1, $args['redirect_to']);   
                }

                return bbpm_redirect($args['redirect_to'], 1, 0)->withNotice($errors);
            }
        }
    }

    public function parseSingleActions()
    {
        global $bbpm_bases;

        if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
            $pattern = sprintf(
                '/%s\/([^(*|\s|\/)]*)\/%s\/(^[A-Za-z]|[A-Za-z0-9]+)\/actions[\/]?$/si',
                str_replace('/', '\/', bbp_get_user_slug()),
                $bbpm_bases['messages_base']
            );
        } else {
            $pattern = sprintf(
                '/%s\/([^(\z|\s|\/)]*)\/%s\/(^[A-Za-z]|[A-Za-z0-9]+)\/actions[\/]?$/si',
                str_replace('/', '\/', bbp_get_user_slug()),
                $bbpm_bases['messages_base']
            );
        }
        
        $uri = $_SERVER['REQUEST_URI'];

        $parsed = parse_url($uri);

        if ( isset($parsed['path']) ) {
            $uri = $parsed['path'];
        }

        preg_match($pattern, $uri, $m);
        
        if ( !isset($m[2]) )
            return;

        $chat_id = esc_attr($m[2]);

        $Messages = bbpm_messages();

        if ( !$Messages->isChatRecipient($Messages->current_user, $chat_id) ) {
            return;
        }

        if ( isset($_SERVER['HTTP_REFERER']) ) {
            $redirect_to = $_SERVER['HTTP_REFERER'];
        } else {
            $redirect_to = $m[0];
        }

        if ('actions/' == substr($redirect_to, strlen($redirect_to)-8) ) {
            $redirect_to = substr($redirect_to, 0, strlen($redirect_to)-8);
        } else if ('actions' == substr($redirect_to, strlen($redirect_to)-7) ) {
            $redirect_to = substr($redirect_to, 0, strlen($redirect_to)-7);
        }

        if ( !bbpm_verify_nonce("single_actions_{$chat_id}") ) {
            return bbpm_redirect($redirect_to, 1, 0)->withNotice(array(
                'nonce',
                __('Error: Bad authentication!', 'bbp-messages'),
                'error'
            ));
        }

        $ids = isset($_POST['messages']) && is_array($_POST['messages']) ? (
            array_map('intval', $_POST['messages'])
        ) : array();

        if ( $ids ) {
            foreach ( $ids as $i=>$id ) {
                $message = $Messages->getMessage($id);

                if ( !isset($message['chat_id']) || $chat_id !== $message['chat_id'] ) {
                    unset($ids[$i]);
                }
            }
        }

        $action = isset($_POST['action']) ? esc_attr($_POST['action']) : null;

        if ( isset($_POST['mark_unread']) ) {
            $action = 'mark_unread';
        }

        do_action('bbpm_process_chat_action', $action, $chat_id, $ids);
        do_action("bbpm_process_chat_action_{$action}", $chat_id, $ids);

        switch ( $action ) {
            case 'delete':
                if ( $ids ) {
                    // filter out the pending-delete items
                    foreach ( $ids as $i=>$id ) {
                        $deletes = (array) $Messages->get_message_meta($message['id'], 'delete_scheduled', null);
                        if ( $deletes && in_array($Messages->current_user, $deletes) ) {
                            unset($ids[$i]);
                        }
                    }

                    if ( !$ids ) {
                        return bbpm_redirect($redirect_to, 1, 0)->withNotice(array(
                            'messages',
                            __('Error: Please select at least 1 message for this action!', 'bbp-messages'),
                            'error'
                        ));
                    }

                    // schedule deletion
                    $Messages->fakeDelete(true)->deleteMessage($ids, $Messages->current_user);

                    // print success message
                    return bbpm_redirect($redirect_to, 1, 0)->withNotice(array(
                        'messages',
                        __('Your messages are being deleted.', 'bbp-messages'),
                        'success'
                    ));
                } else {
                    return bbpm_redirect($redirect_to, 1, 0)->withNotice(array(
                        'messages',
                        __('Error: Please select at least 1 message for this action!', 'bbp-messages'),
                        'error'
                    ));
                }
                break;

            case 'update_settings':
                return $this->updateSettingsAction($chat_id);
                break;

            case 'delete_chat':
                return $this->deleteChatAction($chat_id);
                break;

            case 'mark_unread':
                return $this->markUnreadAction($chat_id, $redirect_to);
                break;

            default:
                return bbpm_redirect($redirect_to, 1, 0)->withNotice(array(
                    'action',
                    __('Error: Invalid bulk action!', 'bbp-messages'),
                    'error'
                ));
                break;
        }
    }

    public function setupProfileTab()
    {
        if ( !class_exists('\bbPressProfileTabs') ) {
            require_once BBP_MESSAGES_DIR . (
                'Inc/Lib/bbPress-Profile-Tabs/bbPressProfileTabs.php'
            );
        }

        global $bbpm_bases;

        $text = __('Messages', 'bbp-messages');
        $uc = bbpm_messages()->getChatsUnreadCount();

        if ( $uc ) {
            $text = sprintf(__('Messages (%d)', 'bbp-messages'), $uc);
        }

        $this->profile_tab = \bbPressProfileTabs::create(array(
            'slug' => $bbpm_bases['messages_base'],
            'menu-item-text' => $text,
            'menu-item-position' => 2,
            'query_var' => 'messages'
        ))->init();
    }

    public function rewriteRules()
    {        
        $rules = $this->rewrite_rules;
        $bases = $this->bases;
        $bases['bbp_user_slug'] = bbp_get_user_slug();

        $rules = array_combine(
            str_replace(array_map(function($b){
                return "{{$b}}";
            }, array_keys($bases)), array_values($bases), array_keys($rules)),
            array_values($rules)
        );

        foreach ( $rules as $regex=>$rewrite ) {
            add_rewrite_rule($regex, $rewrite, 'top');
        }
    }

    public function queryVars($args)
    {
        return array_merge($args, $this->query_vars);
    }

    public function parseQuery()
    {
        // logged-in user check
        if ( bbpm_is_messages() ) {
            if ( !is_user_logged_in() ) {
                return bbpm_redirect(apply_filters(
                    'bbpm_redirect_login_url',
                    wp_login_url(esc_url($_SERVER['REQUEST_URI']))
                ));
            }

            else if ( !bbp_is_user_home() ) {
                global $current_user;

                $displayed_user = get_userdata(bbp_get_displayed_user_id());

                if ( empty($displayed_user->user_nicename) ) {
                    $redirect_to = bbpm_messages_url(null, $current_user->ID);
                } else {
                    $uri = $_SERVER['REQUEST_URI'];
                    $id = apply_filters('bbpm_bbp_user_id_data', 'user_nicename');
                    $match = bbp_get_user_slug() . '/' . $displayed_user->$id . '/';

                    if ( false !== strpos($uri, $match) ) {
                        $uri = str_replace(
                            $match,
                            bbp_get_user_slug() . '/' . $current_user->$id . '/',
                            $uri
                        );
                    }
                    $redirect_to = $uri;
                }

                return bbpm_redirect($redirect_to, 1);
            }
        }

        $args = array();

        if ( bbpm_get_current_page() ) {
            $args['current_page'] = bbpm_get_current_page();
        }

        if ( bbpm_search_query() ) {
            $args['search_term'] = '%' . bbpm_search_query_prepared() . '%';
        }

        $opt = (object) bbpm_options();

        if ( bbpm_is_chats() ) {
            $args['per_page'] = $opt->pagi_chats;
            $this->parseQueryChats($args);
        } else if ( bbpm_is_single_chat() ) {
            $args['per_page'] = $opt->pagi_messages;
            $this->parseQuerySingle($args);
        } else if ( get_query_var('bbpm_new') ) {
            $this->parseQueryNew();
        } else if ( get_query_var('bbpm_with') ) {
            $this->dispatchWithRedirect(get_query_var('bbpm_with'));
        }

        if ( !bbp_is_user_home() ) {
            global $BPT_tabs;

            if ( !empty($BPT_tabs) && is_array($BPT_tabs) ) {
                foreach ( $BPT_tabs as $i=>$tab ) {
                    if ( !empty($tab['slug']) && 'messages' == $tab['slug'] ) {
                        unset($BPT_tabs[$i]);
                    }
                }
            }
        }
    }

    public function parseQueryChats($wp_messages_args=null)
    {
        global $bbpm_template, $bbpm_chats, $bbpm_pagination;
        $messages = bbpm_messages();

        if ( is_array($wp_messages_args) ) {
            $messages->set($wp_messages_args);
        }

        $this->loop_chats['chats'] = $messages->chats()->get('chats');

        if ( $this->loop_chats['chats'] ) {
            $this->loop_chats['chats'] = array_map(array($messages, 'prepareChat'), $this->loop_chats['chats']);
        }

        $bbpm_chats = $this;
        $bbpm_template = 'messages/chats.php';
        $bbpm_pagination = $messages->get('paginate_links_args', array());
    }

    public function parseQueryNew($wp_messages_args=null)
    {
        global $bbpm_recipient, $bbpm_template, $bbpm_search;
        $bbpm_template = 'messages/new.php';

        if ( empty($bbpm_recipient->ID) ) {
            if ( $_POST ) {
                if ( isset($_POST['u']) ) {
                    $user = get_userdata($_POST['u']);
                    if ( !empty($user->ID) ) {
                        $bbpm_recipient = $user;
                    } else {
                        bbpm_add_error('recipient', __('Invalid user! Please search and select a recipient below.', 'bbp-messages'), 'error');
                    }
                }

                else if ( bbpm_search_query() ) {
                    global $bbpm_search;

                    $users = new \WP_User_Query(array(
                        'search'         => apply_filters('bbpm_new_search_users_pattern', '*'.esc_attr(bbpm_search_query()).'*' ),
                        'search_columns' => apply_filters('bbpm_new_search_users_fields', array(
                            'user_nicename',
                            'display_name',
                            'first_name',
                            'last_name',
                        )),
                        'exclude' => array( get_current_user_id() )
                    ));
                    $bbpm_search = apply_filters('bbpm_new_search_users', $users->get_results(), bbpm_search_query());
                }
            } else if ( $_GET ) {
                if ( isset( $_GET['u'] ) ) {
                    $user = get_userdata( (int) $_GET['u'] );

                    if ( !empty($user->ID) ) {
                        $bbpm_recipient = $user;
                    } else {
                        bbpm_add_error('recipient', __('Invalid user! Please search and select a recipient below.', 'bbp-messages'), 'error');
                    }
                }

                else if ( bbpm_search_query() ) {
                    global $bbpm_search;

                    $users = new \WP_User_Query(array(
                        'search'         => apply_filters('bbpm_new_search_users_pattern', '*'.esc_attr(bbpm_search_query()).'*' ),
                        'search_columns' => apply_filters('bbpm_new_search_users_fields', array(
                            'user_nicename',
                            'display_name',
                            'first_name',
                            'last_name',
                        )),
                        'exclude' => array( get_current_user_id() )
                    ));
                    $bbpm_search = apply_filters('bbpm_new_search_users', $users->get_results(), bbpm_search_query());
                }
            }
        }

        if ( !empty($bbpm_recipient->ID) ) {
            if ( !bbpm_can_contact($bbpm_recipient->ID) ) {
                unset($GLOBALS['bbpm_recipient']);
                bbpm_add_error(
                    'recipient',
                    __('Sorry, you cannot contact this user. Please search and select a recipient below.', 'bbp-messages'),
                    'error'
                );
            }

            if ( !isset($_GET['u']) ) {
                return bbpm_redirect(add_query_arg('u', $bbpm_recipient->ID, bbpm_messages_url('new')), 1);
            }

            $m = bbpm_messages();

            $chat_id = $m->getPrivateSharedChat($m->current_user, $bbpm_recipient->ID);

            if ( !$chat_id ) {

                do_action('bbpm_new_pre_generate_chat_id', $bbpm_recipient->ID);

                if ( bbpm_has_errors() )
                    return;

                $chat_id = $m->set('chat_id', null)->getOrGenerateChatId();
                $m->set('chat_id', $chat_id)->addChatRecipient(array($m->current_user, $bbpm_recipient->ID));
            }

            global $bbpm_chat_id;
            $bbpm_chat_id = $chat_id;
        }
    }

    public function parseQuerySingle($wp_messages_args=null)
    {
        global $bbpm_template
             , $bbpm_inbox_ids
             , $bbpm_pagination
             , $bbpm_chat_id
             , $bbpm_recipient
             , $bbpm_chat;
        $messages = bbpm_messages();

        if ( is_array($wp_messages_args) ) {
            $messages->set($wp_messages_args);
        }

        $chat_id = get_query_var('bbpm_chat');
        $recipients = $messages->getChatRecipients($chat_id);

        if ( $recipients ) {
            foreach ( $recipients as $user_id ) {
                if ( $user_id !== $messages->current_user ) {
                    $bbpm_recipient = get_userdata($user_id);
                    break;
                }
            }
        }

        $bbpm_chat = (object) $messages->prepareChat(array('chat_id'=>$chat_id));

        if ( !$chat_id || !$messages->isChatRecipient($messages->current_user, $chat_id) ) {
            //
            do_action('bbpm_chat_not_found', $chat_id);

            return bbpm_redirect(bbpm_messages_url(), 1, 0)->withNotice(array(
                '404_chat',
                __('Error: Could not find chat!', 'bbp-messages'),
                'error'
            ));
        }

        $bbpm_chat_id = $chat_id;

        if ( bbpm_is_chat_settings() ) {
            $bbpm_template = 'messages/single-settings.php';
        } else {
            $deletes = (array) $messages->get_chat_meta($chat_id, 'delete_scheduled', null);

            if ( $deletes && in_array($messages->current_user, $deletes) ) {
                $bbpm_pagination = $bbpm_inbox_ids = array();                
            } else {
                $bbpm_inbox_ids = array_reverse($messages->inboxMessages($chat_id)->get('inbox_messages'));

                if ( $_ids = bbpm_get_list_keys($bbpm_inbox_ids, 'id', 'intval') ) {
                    if ( $_ids !== array_unique($_ids) ) {
                        $_passed = array();
                        foreach ( $bbpm_inbox_ids as $i=>$_m ) {
                            if ( !isset($_passed[$_m['id']]) ) {
                                $_passed[$_m['id']] = true;
                            } else {
                                unset($bbpm_inbox_ids[$i]);
                            }
                        }
                    }
                }

                $bbpm_chat->unread = (array) $messages->get_chat_meta($chat_id, 'unread', null);
                if ( $bbpm_chat->unread ) {
                    $bbpm_chat->unread = array_map('intval', $bbpm_chat->unread);
                }

                if (
                    // on first page
                    1 == $messages->current_page
                    // not search
                    && !$messages->search_term
                    // there are messages
                    && $bbpm_inbox_ids
                    // last message is set
                    && !empty($bbpm_inbox_ids[count($bbpm_inbox_ids)-1])
                    // last message sender is set
                    && !empty($bbpm_inbox_ids[count($bbpm_inbox_ids)-1]['sender'])
                    // last message was sent
                    && $bbpm_inbox_ids[count($bbpm_inbox_ids)-1]['sender'] == $messages->current_user
                ) {
                    // get chat contacts
                    $contacts = $bbpm_chat->recipients;
                    // unset current user from contacts
                    $i = array_search($messages->current_user, $contacts);
                    if ( isset($contacts[$i]) ) {
                        unset($contacts[$i]);
                    }
                    // who read the chat
                    $bbpm_chat->seen = array();
                    if ( $contacts ) {
                        foreach ( $contacts as $uid ) {
                            if ( !in_array($uid, $bbpm_chat->unread) ) {
                                $user = get_userdata($uid);
                                if ( $user->ID )
                                    $bbpm_chat->seen[] = $user;
                            }
                        }
                    }
                }

                $last = $messages->getLastChatMessage($chat_id);

                if ( isset($last['sender']) && $last['sender'] !== $messages->current_user ) {
                    $bbpm_chat->can_mark_unread = true;

                    // mark chat read
                    if ( !empty($bbpm_chat->unread) && in_array($messages->current_user, $bbpm_chat->unread) ) {
                        if ( apply_filters('bbpm_mark_read', true) ) {
                            $messages->markRead($chat_id);
                        }
                    }
                } else {
                    $bbpm_chat->can_mark_unread = false;
                }

                $bbpm_pagination = $messages->get('paginate_links_args', array());
            }
            $bbpm_template = 'messages/single-chat.php';
        }
    }

    public function hasChats($increment=null)
    {
        if ( $this->loop_chats['chats'] && isset($this->loop_chats['chats'][$this->loop_chats['current_index']+1]) ) {
            if ( $increment ) {
                $this->loop_chats['current_index']++;
            }
            return true;
        }

    }

    public function theChat()
    {
        global $bbpm_chat;

        if ( isset($this->loop_chats['chats'][$this->loop_chats['current_index']]) ) {
            $c = $this->loop_chats['chats'][$this->loop_chats['current_index']];
        } else {
            $c = null;
        }

        $bbpm_chat = $this->loop_chats['current_chat'] = $c;

        return $this;
    }

    public function get($prop, $default=null)
    {
        if ( isset($this->loop_chats[$prop]) ) {
            return $this->loop_chats[$prop];
        }

        return isset($this->$prop) ? $this->$prop : $default;
    }

    public function loadTemplate()
    {
        global $bbpm_template;

        print '<div class="bbpm">';

        do_action('bbpm_template_head');

        if ( isset($bbpm_template) ) {
            bbpm_load_template($bbpm_template);
        }

        do_action('bbpm_template_foot');

        print '</div>';
    }

    public function registerScripts()
    {
        $css = apply_filters('bbpm_assets-style.css', BBP_MESSAGES_URL . 'assets/css/style.css');

        if ( $css ) {
            wp_register_style('bbpm-style', $css, array(), BBP_MESSAGES_VER);
        }

        $css = apply_filters('bbpm_assets-messages.css', BBP_MESSAGES_URL . 'assets/css/messages.css');

        if ( $css ) {
            wp_register_style('bbpm-messages', $css, array(), BBP_MESSAGES_VER);
        }

        $js = apply_filters('bbpm_assets-messages.js', BBP_MESSAGES_URL . 'assets/js/messages.js');
        
        if ( $js ) {
            wp_register_script('bbpm-messages', $js, array(), BBP_MESSAGES_VER);        
        }
    }

    public function enqueueScripts()
    {
        if ( !bbpm_is_messages() )
            return;
        
        wp_enqueue_style('bbpm-messages');

        if ( bbpm_is_chats() || bbpm_is_chat_settings() ) {
            wp_enqueue_script('bbpm-messages');
            wp_localize_script('bbpm-messages', 'BBP_MESSAGES', array(
                'messages_base' => bbpm_messages_url()
            ));
        }
    }

    public function dispatchWithRedirect($user)
    {
        if ( is_numeric($user) ) {
            $user = get_userdata((int) $user);
        } else if ( is_email($user) ) {
            $user = get_user_by('email', $user);
        } else {
            $user = get_user_by('slug', $user);
        }

        if ( empty($user->ID) ) {
            return bbpm_redirect(bbpm_messages_url(), 1);
        }

        global $current_user;

        $chat_id = bbpm_messages()->getPrivateSharedChat($current_user->ID, $user->ID);

        if ( $chat_id ) {
            return bbpm_redirect(bbpm_messages_url($chat_id), 1);
        } else {
            global $bbpm_bases;
            return bbpm_redirect(add_query_arg('u', $user->ID, bbpm_messages_url($bbpm_bases['new'])), 1);
        }
    }

    public static function parseTempalteErrors()
    {
        Redirect::parseNotices('bbpm_errors', 'bbpm_add_error');
        // exclude errors by codes to parse errors below form fields
        $exclude_codes = apply_filters('bbpm_parse_template_errors_exclude_codes', array(
            'search',
            'message'
        ));
        // print'em
        bbpm_template_errors($exclude_codes);
    }

    public function obStart()
    {
        return ob_start();
    }

    public function filterMessageData($message)
    {
        if ( !is_array($message) || !(array) $message )
            return $message;

        $messages = bbpm_messages();
        $message['classes'] = array();

        if ( !empty($message['sender']) ) {
            $message['sender_data'] = get_userdata($message['sender']);
            $message['classes'][] = $message['sender'] == $messages->current_user ? 'sent' : 'received';
        }

        $deletes = (array) $messages->get_message_meta($message['id'], 'delete_scheduled', null);

        if ( in_array($messages->current_user, $deletes) ) {
            $message['classes'][] = 'pending-delete';
        }

        return $message;
    }

    public function updateSettingsAction($chat_id)
    {
        $m = bbpm_messages();
            
        $_unsub = (array) $m->get_chat_meta($chat_id, 'unsubscribe', null);
        $unsub = $_unsub;

        if ( !isset($_POST['subscribe']) ) {
            // unsubscribe
            if ( !in_array($m->current_user, $unsub) ) {
                $unsub[] = $m->current_user;
            }
        } else {
            // subscribe
            if ( in_array($m->current_user, $unsub) ) {
                $i = array_search($m->current_user, $unsub);
                if ( is_numeric($i) ) {
                    unset($unsub[$i]);
                }
            }
        }

        if ( $unsub !== $_unsub ) {
            if ( $unsub ) {
                $m->update_chat_meta($chat_id, 'unsubscribe', $unsub);
            } else {
                $m->delete_chat_meta($chat_id, 'unsubscribe');
            }
        }

        if ( isset($_POST['name']) ) {
            if ( trim($_POST['name']) ) {
                $m->update_chat_meta($chat_id, 'name', sanitize_text_field(esc_attr($_POST['name'])));                
            } else {
                $m->delete_chat_meta($chat_id, 'name');
            }
        }

        return bbpm_redirect(bbpm_messages_url($chat_id, $m->current_user), 1, 0)->withNotice(array(
            'updated',
            __('Settings updated successfully!', 'bbp-messages'),
            'success'
        ));
    }

    public function deleteChatAction($chat_id)
    {
        if ( !bbpm_verify_nonce("delete_chat_{$chat_id}", 'delete_chat_nonce') ) {
            return bbpm_redirect($redirect_to, 1, 0)->withNotice(array(
                'nonce',
                __('Error: Bad authentication!', 'bbp-messages'),
                'error'
            ));
        }

        $messages = bbpm_messages();

        $messages->fakeDelete(true)->deleteChat($chat_id);

        return bbpm_redirect(bbpm_messages_url(null, $messages->current_user), 1, 0)->withNotice(array(
            'delete',
            __('Your chat is being deleted.', 'bbp-messages'),
            'success'
        ));
    }

    public function markUnreadAction($chat_id, $redirect_to)
    {
        $messages = bbpm_messages();
        $last = $messages->getLastChatMessage($chat_id);

        if ( isset($last['sender']) && $last['sender'] !== $messages->current_user ) {
            $messages->markUnread($chat_id);

            return bbpm_redirect(bbpm_messages_url(null, $messages->current_user), 1, 0)->withNotice(array(
                'mark_unread',
                __('Chat successfully marked unread.', 'bbp-messages'),
                'success'
            ));
        }

        return bbpm_redirect($redirect_to, 1, 0)->withNotice(array(
            'mark_unread',
            __('Error: could not mark chat unread.', 'bbp-messages'),
            'error'
        ));
    }

    public function checkCanContact($args)
    {
        if ( !empty($args['chat_id']) ) {
            $chat_id = $args['chat_id'];
            $m = bbpm_messages()->set('chat_id', $chat_id);
            $r = $m->getChatRecipients();

            if ( 2 == count($r) ) {
                foreach ($r as $uid) {
                    if ($uid !== $m->current_user) {
                        $contact = $uid;
                    }
                }
            }

            if ( !empty($contact) ) {
                if ( !bbpm_can_contact($contact, $m->current_user) ) {
                    bbpm_add_error('cannot-contact', __(
                        'Sorry, you cannot contact this user!',
                        'bbp-messages'
                    ), 'error');
                }
            }
        }
    }

    public function loadIntegrate()
    {
        require_once BBP_MESSAGES_DIR . (
            'Inc/Core/integrate.php'
        );
    }

    public function widgetsInit()
    {
        if ( empty($this->widgets) )
            return;

        foreach ( $this->widgets as $widget ) {
            register_widget($widget);
        }
    }

    public function parseNewMessageWidgetErrors()
    {
        if ( !did_action('bbpm_template_head', array($this, 'parseTempalteErrors')) ) {
            Redirect::parseNotices('bbpm_errors', 'bbpm_add_error');
            // print'em
            bbpm_template_errors(array(
                'recipient',
                'message'
            ));
        }
    }

    public function enqueueStyleCSS()
    {
        return wp_enqueue_style('bbpm-style');
    }

    public function fitlerMenuItems($html, $term)
    {
        global $current_user, $bbpm_options;

        switch ( true ) {
            case !is_user_logged_in():
            case !isset($bbpm_options['menu_locations']) || !$bbpm_options['menu_locations']:
            case !isset($term->menu->term_id) || !in_array($term->menu->term_id, $bbpm_options['menu_locations']):
                return $html;
                break;

            default:
                return $html . sprintf(
                    '<li class="bbpm-menu-item"><a href="%s">%s</a></li>',
                    bbpm_messages_url(null, $current_user->ID),
                    do_shortcode($bbpm_options['menu_text'])
                );
                break;
        }
    }
}