<?php
/**
 * Plugin Name: SemanticFlow Tracker
 * Plugin URI: https://github.com/DELE-CONSULTING/semanticflow-wp-plugin
 * Description: Semanticflow tracker
 * Version: 1.2.3
 * Author: SemanticFlow
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SemanticFlow_Tracker {
    
    private $base_url = 'https://txsipwnsmxemwkgnseqn.supabase.co/functions/v1/track';
    private $project_id = 'xxxxx-xxxx';
    
    public function __construct() {
        add_action('template_redirect', array($this, 'track_post_visit'));
    }
    
    /**
     * Track post visits from LLM bots and send to base_url
     */
    public function track_post_visit() {
        if (!is_singular()) {
            return;
        }
        
        global $post;
        
        $included_types = get_option('sft_included_post_types');
        if (is_array($included_types) && !empty($included_types)) {
            $post_type = get_post_type($post);
            if (!in_array($post_type, $included_types, true)) {
                return;
            }
        }
        
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $robot_source = $this->detect_llm_bot($user_agent);
        
        if ($robot_source === 'Human') {
            return;
        }
        
        $project_id_option = get_option('sft_project_id');
        $resolved_project_id = is_string($project_id_option) ? trim(sanitize_text_field($project_id_option)) : '';
        if ($resolved_project_id === '' || $resolved_project_id === 'xxxxx-xxxx') {
            $resolved_project_id = $this->project_id;
        }
        if (empty($resolved_project_id) || $resolved_project_id === 'xxxxx-xxxx') {
            return;
        }
        
        $ip_address = $this->get_client_ip();
        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        $url = get_permalink($post->ID);
        
        if ($this->is_url_excluded($url)) {
            return;
        }
        
        // Prepare data payload
        $data = array(
            'project_id' => $resolved_project_id,
            'robot_source' => $robot_source,
            'user_agent' => $user_agent,
            'ip_address' => $ip_address,
            'referrer' => $referrer,
            'url' => $url
        );
        
        // Send data asynchronously to avoid slowing down page load
        $this->send_tracking_data($data);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    }
    
    /**
     * Detect if visitor is an LLM/AI bot (only AI bots, no traditional search engines)
     */
    private function detect_llm_bot($user_agent) {
        // Enabled families (empty => allow all families)
        $enabled_families = get_option('sft_enabled_bot_families');
        $enabled_families = is_array($enabled_families) ? array_map('sanitize_text_field', $enabled_families) : array();
        $enabled_lookup = array();
        foreach ($enabled_families as $fam) { $enabled_lookup[strtolower($fam)] = true; }

        // Custom UA allow/deny lists
        $ua_deny = $this->explode_lines_to_array(get_option('sft_ua_deny_list'));
        $ua_allow = $this->explode_lines_to_array(get_option('sft_ua_allow_list'));

        $user_agent_lower = strtolower($user_agent);

        foreach ($ua_deny as $deny) {
            if ($deny !== '' && strpos($user_agent_lower, $deny) !== false) {
                return 'Human';
            }
        }
        foreach ($ua_allow as $allow) {
            if ($allow !== '' && strpos($user_agent_lower, $allow) !== false) {
                return 'Custom-Allowed';
            }
        }

        // Catalog of bots with families
        $llm_bots = array(
            // OpenAI
            'gptbot' => array('name' => 'GPTBot', 'family' => 'OpenAI'),
            'oai-searchbot' => array('name' => 'OAI-SearchBot', 'family' => 'OpenAI'),
            'chatgpt-user' => array('name' => 'ChatGPT-User', 'family' => 'OpenAI'),
            
            // Anthropic
            'anthropic-ai' => array('name' => 'Anthropic', 'family' => 'Anthropic'),
            'claudebot' => array('name' => 'ClaudeBot', 'family' => 'Anthropic'),
            'claude-searchbot' => array('name' => 'Claude-SearchBot', 'family' => 'Anthropic'),
            'claude-user' => array('name' => 'Claude-User', 'family' => 'Anthropic'),
            
            // Google AI
            'google-extended' => array('name' => 'Google-Extended', 'family' => 'Google'),
            'googleother' => array('name' => 'GoogleOther', 'family' => 'Google'),
            
            // Amazon AI
            'amazonbot' => array('name' => 'Amazonbot', 'family' => 'Amazon'),
            'amazonkendra' => array('name' => 'AmazonKendra', 'family' => 'Amazon'),
            'bedrockbot' => array('name' => 'BedrockBot', 'family' => 'Amazon'),
            'novaact' => array('name' => 'NovaAct', 'family' => 'Amazon'),
            
            // Apple AI
            'applebot-extended' => array('name' => 'Applebot-Extended', 'family' => 'Apple'),
            'applebot' => array('name' => 'Applebot', 'family' => 'Apple'),
            
            // Meta AI
            'facebookbot' => array('name' => 'FacebookBot', 'family' => 'Meta'),
            
            // Perplexity
            'perplexitybot' => array('name' => 'PerplexityBot', 'family' => 'Perplexity'),
            'perplexity-user' => array('name' => 'Perplexity-User', 'family' => 'Perplexity'),
            
            // Cohere
            'cohere-ai' => array('name' => 'Cohere-AI', 'family' => 'Cohere'),
            'cohere-training-data-crawler' => array('name' => 'Cohere-Training', 'family' => 'Cohere'),
            
            // Other AI/LLM bots
            'ai2bot' => array('name' => 'AI2Bot', 'family' => 'Other'),
            'bytespider' => array('name' => 'Bytespider', 'family' => 'Other'),
            'ccbot' => array('name' => 'CCBot', 'family' => 'Other'),
            'diffbot' => array('name' => 'Diffbot', 'family' => 'Other'),
            'duckassistbot' => array('name' => 'DuckAssistBot', 'family' => 'Other'),
            'friendlycrawler' => array('name' => 'FriendlyCrawler', 'family' => 'Other'),
            'deepseekbot' => array('name' => 'DeepSeekBot', 'family' => 'Other'),
            'img2dataset' => array('name' => 'img2dataset', 'family' => 'Other'),
            'mistralai-user' => array('name' => 'MistralAI-User', 'family' => 'Other'),
            'pangubot' => array('name' => 'PanguBot', 'family' => 'Other'),
            'paperlibot' => array('name' => 'PaperLiBot', 'family' => 'Other'),
            'petalbot' => array('name' => 'PetalBot', 'family' => 'Other'),
            'scrapy' => array('name' => 'Scrapy', 'family' => 'Other'),
            'semrushbot' => array('name' => 'SemrushBot', 'family' => 'Other'),
            'sider.ai' => array('name' => 'Sider.AI', 'family' => 'Other'),
            'theknowledgeai' => array('name' => 'TheKnowledgeAI', 'family' => 'Other'),
            'timpibot' => array('name' => 'TimpiBot', 'family' => 'Other'),
            'youbot' => array('name' => 'YouBot', 'family' => 'Other'),
        );

        foreach ($llm_bots as $needle => $info) {
            if (strpos($user_agent_lower, $needle) !== false) {
                $fam = strtolower($info['family']);
                if (!empty($enabled_lookup) && !isset($enabled_lookup[$fam])) {
                    continue; // family disabled
                }
                return $info['name'];
            }
        }
        
        return 'Human';
    }

    private function is_url_excluded($url) {
        $patterns = $this->explode_lines_to_array(get_option('sft_exclude_url_patterns'));
        if (empty($patterns)) {
            return false;
        }
        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        foreach ($patterns as $pattern) {
            if ($pattern === '') { continue; }
            // Regex if wrapped in /.../
            if (strlen($pattern) >= 2 && $pattern[0] === '/' && substr($pattern, -1) === '/') {
                $regex = $pattern;
                if (@preg_match($regex, $url)) {
                    if (preg_match($regex, $url)) { return true; }
                }
                continue;
            }
            // Prefix match on path
            if (strpos($path, $pattern) === 0) {
                return true;
            }
            // Substring anywhere in full URL
            if (strpos($url, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    private function explode_lines_to_array($option_value) {
        if (!is_string($option_value) || $option_value === '') { return array(); }
        $lines = preg_split('/\r\n|\r|\n/', $option_value);
        $result = array();
        foreach ($lines as $line) {
            $line = trim(sanitize_text_field($line));
            if ($line !== '') { $result[] = strtolower($line); }
        }
        return $result;
    }
    
    /**
     * Send tracking data to SemanticFlow
     */
    private function send_tracking_data($data) {
        $args = array(
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 5,
            'blocking' => false, // Non-blocking for better performance
        );
        
        wp_remote_post($this->base_url, $args);
    }
}

new SemanticFlow_Tracker();

/**
 * Optional: Add admin settings page
 */
class SemanticFlow_Tracker_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'SemanticFlow Tracker Settings',
            'SemanticFlow Tracker',
            'manage_options',
            'semanticflow-tracker',
            array($this, 'settings_page')
        );
    }
    
    public function register_settings() {
        register_setting(
            'semanticflow_tracker_settings',
            'sft_project_id',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'semanticflow_tracker_settings',
            'sft_included_post_types',
            array(
                'type' => 'array',
                'sanitize_callback' => function($value) {
                    $out = array();
                    if (is_array($value)) {
                        foreach ($value as $v) { $out[] = sanitize_text_field($v); }
                    }
                    return $out;
                },
                'default' => array()
            )
        );

        register_setting(
            'semanticflow_tracker_settings',
            'sft_exclude_url_patterns',
            array(
                'type' => 'string',
                'sanitize_callback' => function($value) { return is_string($value) ? trim(wp_kses_post($value)) : ''; },
                'default' => ''
            )
        );

        register_setting(
            'semanticflow_tracker_settings',
            'sft_enabled_bot_families',
            array(
                'type' => 'array',
                'sanitize_callback' => function($value) {
                    $out = array();
                    if (is_array($value)) {
                        foreach ($value as $v) { $out[] = sanitize_text_field($v); }
                    }
                    return $out;
                },
                'default' => array()
            )
        );

        register_setting(
            'semanticflow_tracker_settings',
            'sft_ua_allow_list',
            array(
                'type' => 'string',
                'sanitize_callback' => function($value) { return is_string($value) ? trim(wp_kses_post($value)) : ''; },
                'default' => ''
            )
        );

        register_setting(
            'semanticflow_tracker_settings',
            'sft_ua_deny_list',
            array(
                'type' => 'string',
                'sanitize_callback' => function($value) { return is_string($value) ? trim(wp_kses_post($value)) : ''; },
                'default' => ''
            )
        );
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>SemanticFlow Tracker Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('semanticflow_tracker_settings');
                do_settings_sections('semanticflow_tracker_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Project ID</th>
                        <td>
                            <input type="text" name="sft_project_id" 
                                   value="<?php echo esc_attr(get_option('sft_project_id')); ?>" 
                                   class="regular-text" />
                            <p class="description">Your SemanticFlow project ID.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Included Post Types</th>
                        <td>
                            <?php
                            $public_types = get_post_types(array('public' => true), 'objects');
                            $selected_types = get_option('sft_included_post_types');
                            $selected_types = is_array($selected_types) ? $selected_types : array();
                            foreach ($public_types as $type => $obj) {
                                $checked = in_array($type, $selected_types, true) ? 'checked' : '';
                                echo '<label style="display:block; margin-bottom:4px;">';
                                echo '<input type="checkbox" name="sft_included_post_types[]" value="' . esc_attr($type) . '" ' . $checked . ' /> ' . esc_html($obj->labels->singular_name) . ' (' . esc_html($type) . ')';
                                echo '</label>';
                            }
                            ?>
                            <p class="description">Leave all unchecked to include all public post types.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Exclude URL Patterns</th>
                        <td>
                            <textarea name="sft_exclude_url_patterns" rows="5" class="large-text code"><?php echo esc_textarea(get_option('sft_exclude_url_patterns')); ?></textarea>
                            <p class="description">One per line. Use <code>/regex/</code> for regex; otherwise prefix/substring match.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enabled Bot Families</th>
                        <td>
                            <?php
                            $families = array('OpenAI','Anthropic','Google','Amazon','Apple','Meta','Perplexity','Cohere','Other');
                            $enabled = get_option('sft_enabled_bot_families');
                            $enabled = is_array($enabled) ? $enabled : array();
                            foreach ($families as $fam) {
                                $checked = in_array($fam, $enabled, true) ? 'checked' : '';
                                echo '<label style="display:inline-block; margin-right:12px;">';
                                echo '<input type="checkbox" name="sft_enabled_bot_families[]" value="' . esc_attr($fam) . '" ' . $checked . ' /> ' . esc_html($fam);
                                echo '</label>';
                            }
                            ?>
                            <p class="description">Leave all unchecked to enable all families.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">User-Agent Allow List</th>
                        <td>
                            <textarea name="sft_ua_allow_list" rows="4" class="large-text code"><?php echo esc_textarea(get_option('sft_ua_allow_list')); ?></textarea>
                            <p class="description">Substrings; any match is treated as bot.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">User-Agent Deny List</th>
                        <td>
                            <textarea name="sft_ua_deny_list" rows="4" class="large-text code"><?php echo esc_textarea(get_option('sft_ua_deny_list')); ?></textarea>
                            <p class="description">Substrings; any match is treated as human.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize admin settings
if (is_admin()) {
    new SemanticFlow_Tracker_Admin();
}

require 'vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/DELE-CONSULTING/semanticflow-wp-plugin',
	__FILE__,
	'semanticflow-wp-plugin'
);
$myUpdateChecker->setBranch('main');
