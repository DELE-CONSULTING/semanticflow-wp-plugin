<?php
/**
 * Plugin Name: SemanticFlow Tracker
 * Plugin URI: https://github.com/DELE-CONSULTING/semanticflow-wp-plugin
 * Description: Semanticflow tracker
 * Version: 1.1.0
 * Author: SemanticFlow
 * Update URI: https://git-updater.com
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SemanticFlow_Tracker {
    
    private $base_url = 'https://api.semanticflow.ai/functions/v1/track';
    private $project_id = 'xxxxx-xxxx';
    
    public function __construct() {
        // Hook into WordPress template redirect to track visits
        add_action('template_redirect', array($this, 'track_post_visit'));
    }
    
    /**
     * Track post visits from LLM bots and send to base_url
     */
    public function track_post_visit() {
        // Only track single post/page views
        if (!is_singular()) {
            return;
        }
        
        global $post;
        
        // Get visitor information
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        
        // Detect if it's an LLM bot
        $robot_source = $this->detect_llm_bot($user_agent);
        
        // Only track if it's an LLM bot
        if ($robot_source === 'Human') {
            return;
        }
        
        $ip_address = $this->get_client_ip();
        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        $url = get_permalink($post->ID);
        
        // Prepare data payload
        $data = array(
            'project_id' => $this->project_id,
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
        $llm_bots = array(
            // OpenAI
            'gptbot' => 'GPTBot',
            'oai-searchbot' => 'OAI-SearchBot',
            'chatgpt-user' => 'ChatGPT-User',
            
            // Anthropic
            'anthropic-ai' => 'Anthropic',
            'claudebot' => 'ClaudeBot',
            'claude-searchbot' => 'Claude-SearchBot',
            'claude-user' => 'Claude-User',
            
            // Google AI
            'google-extended' => 'Google-Extended',
            'googleother' => 'GoogleOther',
            
            // Amazon AI
            'amazonbot' => 'Amazonbot',
            'amazonkendra' => 'AmazonKendra',
            'bedrockbot' => 'BedrockBot',
            'novaact' => 'NovaAct',
            
            // Apple AI
            'applebot-extended' => 'Applebot-Extended',
            'applebot' => 'Applebot',
            
            // Meta AI
            'facebookbot' => 'FacebookBot',
            
            // Perplexity
            'perplexitybot' => 'PerplexityBot',
            'perplexity-user' => 'Perplexity-User',
            
            // Cohere
            'cohere-ai' => 'Cohere-AI',
            'cohere-training-data-crawler' => 'Cohere-Training',
            
            // Other AI/LLM bots
            'ai2bot' => 'AI2Bot',
            'bytespider' => 'Bytespider',
            'ccbot' => 'CCBot',
            'diffbot' => 'Diffbot',
            'duckassistbot' => 'DuckAssistBot',
            'friendlycrawler' => 'FriendlyCrawler',
            'deepseekbot' => 'DeepSeekBot',
            'img2dataset' => 'img2dataset',
            'mistralai-user' => 'MistralAI-User',
            'pangubot' => 'PanguBot',
            'paperlibot' => 'PaperLiBot',
            'petalbot' => 'PetalBot',
            'scrapy' => 'Scrapy',
            'semrushbot' => 'SemrushBot',
            'sider.ai' => 'Sider.AI',
            'theknowledgeai' => 'TheKnowledgeAI',
            'timpibot' => 'TimpiBot',
            'youbot' => 'YouBot',
        );
        
        $user_agent_lower = strtolower($user_agent);
        
        foreach ($llm_bots as $bot => $name) {
            if (strpos($user_agent_lower, $bot) !== false) {
                return $name;
            }
        }
        
        return 'Human';
    }
    
    /**
     * Send tracking data to SemanticFlow
     */
    private function send_tracking_data($data) {
        // Use wp_remote_post for asynchronous request
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

// Initialize the plugin
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
        register_setting('semanticflow_tracker_settings', 'sft_project_id');
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

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/vendor/afragen/git-updater-lite/Lite.php';
( new \Fragen\Git_Updater\Lite( __FILE__ ) )->run();