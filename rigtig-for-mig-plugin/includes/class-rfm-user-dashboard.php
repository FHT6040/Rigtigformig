<?php
/**
 * User Dashboard Management
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_User_Dashboard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register shortcode immediately (not hooked, since class is instantiated during init)
        $this->register_shortcodes();

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers
        add_action('wp_ajax_rfm_update_user_profile', array($this, 'handle_profile_update'));
        add_action('wp_ajax_rfm_upload_user_avatar', array($this, 'handle_avatar_upload'));
        add_action('wp_ajax_rfm_delete_user_account', array($this, 'handle_account_deletion'));

        rfm_log('RFM_User_Dashboard: Class constructed and hooks registered');
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('rfm_user_dashboard', array($this, 'dashboard_shortcode'));
        rfm_log('RFM_User_Dashboard: Shortcode [rfm_user_dashboard] registered');
    }

    /**
     * Enqueue dashboard scripts and styles
     *
     * NOTE: We enqueue globally instead of checking for shortcode to ensure
     * reliability with page builders, widgets, and various theme architectures.
     * This matches the approach used by the working expert dashboard system.
     */
    public function enqueue_scripts() {
        // Only load on frontend (not admin)
        if (is_admin()) {
            return;
        }

        // Enqueue user dashboard script globally on all frontend pages
        // CRITICAL: Depends on 'rfm-public' to ensure rfmData is available
        wp_enqueue_script(
            'rfm-user-dashboard',
            RFM_PLUGIN_URL . 'assets/js/user-dashboard.js',
            array('jquery', 'rfm-public'),
            RFM_VERSION,
            true
        );

        // Localize script with translations and data
        wp_localize_script('rfm-user-dashboard', 'rfmUserDashboard', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rfm_nonce'),
            'homeUrl' => home_url(),
            'strings' => array(
                'saving' => __('Gemmer...', 'rigtig-for-mig'),
                'error' => __('Der opstod en fejl', 'rigtig-for-mig'),
                'fillAllFields' => __('Udfyld alle felter', 'rigtig-for-mig'),
                'passwordMismatch' => __('De nye adgangskoder matcher ikke', 'rigtig-for-mig'),
                'passwordTooShort' => __('Ny adgangskode skal være mindst 8 tegn', 'rigtig-for-mig'),
                'changingPassword' => __('Skifter adgangskode...', 'rigtig-for-mig'),
                'loggingOut' => __('Logger ud...', 'rigtig-for-mig'),
                'downloading' => __('Downloader...', 'rigtig-for-mig'),
                'dataDownloaded' => __('Dine data er downloadet', 'rigtig-for-mig'),
                'enterPassword' => __('Indtast din adgangskode', 'rigtig-for-mig'),
                'finalWarning' => __('SIDSTE ADVARSEL: Dette kan ikke fortrydes!', 'rigtig-for-mig'),
                'deleting' => __('Sletter...', 'rigtig-for-mig'),
                'confirmDelete' => __('Ja, slet min konto', 'rigtig-for-mig')
            )
        ));

        rfm_log('RFM_User_Dashboard: Scripts enqueued globally on frontend');
    }
    
    /**
     * User dashboard shortcode
     */
    public function dashboard_shortcode($atts) {
        rfm_log('RFM_User_Dashboard: dashboard_shortcode called');

        if (!is_user_logged_in()) {
            rfm_log('RFM_User_Dashboard: User not logged in');
            return '<div class="rfm-message rfm-message-warning">' .
                   __('Du skal være logget ind for at se denne side.', 'rigtig-for-mig') .
                   ' <a href="' . home_url('/login') . '">' . __('Log ind her', 'rigtig-for-mig') . '</a></div>';
        }

        $user = wp_get_current_user();
        rfm_log('RFM_User_Dashboard: User logged in - ID: ' . $user->ID . ', Roles: ' . implode(', ', $user->roles));

        // Check if user has correct role
        if (!in_array('rfm_user', $user->roles)) {
            rfm_log('RFM_User_Dashboard: User does not have rfm_user role');
            return '<div class="rfm-message rfm-message-error">' .
                   __('Du har ikke adgang til denne side.', 'rigtig-for-mig') . '</div>';
        }

        rfm_log('RFM_User_Dashboard: Rendering dashboard for user ' . $user->ID);
        
        // Get user profile data
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_user_profiles';
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user->ID
        ));
        
        ob_start();
        ?>
        <div class="rfm-user-dashboard">
            <div class="rfm-dashboard-header">
                <h1><?php printf(__('Velkommen, %s', 'rigtig-for-mig'), $user->display_name); ?></h1>
                <button id="rfm-logout-btn" class="rfm-btn rfm-btn-secondary">
                    <?php _e('Log ud', 'rigtig-for-mig'); ?>
                </button>
            </div>
            
            <div class="rfm-dashboard-content">
                <!-- Profile Section -->
                <div class="rfm-dashboard-section">
                    <h2><?php _e('Min Profil', 'rigtig-for-mig'); ?></h2>
                    
                    <form id="rfm-user-profile-form" class="rfm-form">
                        <div class="rfm-profile-image-section">
                            <div class="rfm-profile-image-preview">
                                <?php
                                $avatar_url = $profile && $profile->profile_image 
                                    ? $profile->profile_image 
                                    : get_avatar_url($user->ID);
                                ?>
                                <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php _e('Profilbillede', 'rigtig-for-mig'); ?>" id="user-avatar-preview">
                            </div>
                            <div class="rfm-profile-image-upload">
                                <label for="user_avatar_upload" class="rfm-btn rfm-btn-secondary">
                                    <?php _e('Upload profilbillede', 'rigtig-for-mig'); ?>
                                </label>
                                <input type="file" id="user_avatar_upload" accept="image/*" style="display: none;">
                                <small class="rfm-field-note"><?php _e('Valgfrit - JPG, PNG eller GIF (maks 2 MB)', 'rigtig-for-mig'); ?></small>
                            </div>
                        </div>
                        
                        <div class="rfm-form-group">
                            <label for="user_display_name"><?php _e('Visningsnavn', 'rigtig-for-mig'); ?></label>
                            <input type="text" id="user_display_name" name="display_name" 
                                   value="<?php echo esc_attr($user->display_name); ?>">
                            <small class="rfm-field-note"><?php _e('Dette navn vises for eksperter', 'rigtig-for-mig'); ?></small>
                        </div>
                        
                        <div class="rfm-form-group">
                            <label for="user_email"><?php _e('E-mail', 'rigtig-for-mig'); ?></label>
                            <input type="email" id="user_email" name="email" 
                                   value="<?php echo esc_attr($user->user_email); ?>" readonly>
                            <small class="rfm-field-note"><?php _e('Kontakt admin for at ændre e-mail', 'rigtig-for-mig'); ?></small>
                        </div>
                        
                        <div class="rfm-form-group">
                            <label for="user_phone"><?php _e('Telefon', 'rigtig-for-mig'); ?></label>
                            <input type="tel" id="user_phone" name="phone" 
                                   value="<?php echo esc_attr($profile ? $profile->phone : ''); ?>">
                            <small class="rfm-field-note"><?php _e('Valgfrit - kun synligt for dig', 'rigtig-for-mig'); ?></small>
                        </div>
                        
                        <div class="rfm-form-group">
                            <label for="user_bio"><?php _e('Om mig', 'rigtig-for-mig'); ?></label>
                            <textarea id="user_bio" name="bio" rows="4"><?php echo esc_textarea($profile ? $profile->bio : ''); ?></textarea>
                            <small class="rfm-field-note"><?php _e('Valgfrit - synligt for eksperter', 'rigtig-for-mig'); ?></small>
                        </div>
                        
                        <div class="rfm-form-messages"></div>
                        
                        <button type="submit" class="rfm-btn rfm-btn-primary">
                            <?php _e('Gem ændringer', 'rigtig-for-mig'); ?>
                        </button>
                    </form>
                </div>
                
                <!-- Password Change Section -->
                <div class="rfm-dashboard-section">
                    <h2><?php _e('Skift adgangskode', 'rigtig-for-mig'); ?></h2>
                    
                    <form id="rfm-password-change-form" class="rfm-form">
                        <div class="rfm-form-group">
                            <label for="current_password"><?php _e('Nuværende adgangskode', 'rigtig-for-mig'); ?></label>
                            <input type="password" id="current_password" name="current_password">
                        </div>
                        
                        <div class="rfm-form-group">
                            <label for="new_password"><?php _e('Ny adgangskode', 'rigtig-for-mig'); ?></label>
                            <input type="password" id="new_password" name="new_password">
                            <small class="rfm-field-note"><?php _e('Mindst 8 tegn', 'rigtig-for-mig'); ?></small>
                        </div>
                        
                        <div class="rfm-form-group">
                            <label for="confirm_password"><?php _e('Bekræft ny adgangskode', 'rigtig-for-mig'); ?></label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <div class="rfm-password-messages"></div>
                        
                        <button type="submit" class="rfm-btn rfm-btn-primary">
                            <?php _e('Skift adgangskode', 'rigtig-for-mig'); ?>
                        </button>
                    </form>
                </div>
                
                <!-- Messages Section -->
                <div class="rfm-dashboard-section">
                    <h2><?php _e('Mine beskeder', 'rigtig-for-mig'); ?></h2>
                    <div class="rfm-messages-container">
                        <?php echo $this->get_user_messages($user->ID); ?>
                    </div>
                </div>
                
                <!-- My Ratings Section -->
                <div class="rfm-dashboard-section">
                    <h2><?php _e('Mine anmeldelser', 'rigtig-for-mig'); ?></h2>
                    <div class="rfm-user-ratings-container">
                        <?php echo $this->get_user_ratings_display($user->ID); ?>
                    </div>
                </div>
                
                <!-- GDPR Section -->
                <div class="rfm-dashboard-section rfm-gdpr-section">
                    <h2><?php _e('Mine data (GDPR)', 'rigtig-for-mig'); ?></h2>
                    
                    <div class="rfm-gdpr-info">
                        <p><?php _e('I henhold til GDPR har du følgende rettigheder:', 'rigtig-for-mig'); ?></p>
                        <ul>
                            <li><?php _e('Ret til at få adgang til dine data', 'rigtig-for-mig'); ?></li>
                            <li><?php _e('Ret til at rette dine data (brug formularen ovenfor)', 'rigtig-for-mig'); ?></li>
                            <li><?php _e('Ret til at slette dine data', 'rigtig-for-mig'); ?></li>
                            <li><?php _e('Ret til dataportabilitet', 'rigtig-for-mig'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="rfm-gdpr-actions">
                        <a href="#" id="rfm-download-data" class="rfm-btn rfm-btn-secondary">
                            <?php _e('Download mine data', 'rigtig-for-mig'); ?>
                        </a>
                        
                        <button type="button" id="rfm-delete-account" class="rfm-btn rfm-btn-danger">
                            <?php _e('Slet min konto', 'rigtig-for-mig'); ?>
                        </button>
                    </div>
                    
                    <div class="rfm-gdpr-info-text">
                        <p><?php 
                        printf(
                            __('Konto oprettet: %s', 'rigtig-for-mig'),
                            $profile ? date_i18n(get_option('date_format'), strtotime($profile->account_created_at)) : 'N/A'
                        ); 
                        ?></p>
                        <p><?php 
                        printf(
                            __('GDPR samtykke givet: %s', 'rigtig-for-mig'),
                            $profile && $profile->gdpr_consent ? __('Ja', 'rigtig-for-mig') : __('Nej', 'rigtig-for-mig')
                        ); 
                        ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Delete Account Confirmation Modal -->
        <div id="rfm-delete-modal" class="rfm-modal" style="display: none;">
            <div class="rfm-modal-content">
                <h3><?php _e('Bekræft sletning af konto', 'rigtig-for-mig'); ?></h3>
                <p><?php _e('Er du sikker på at du vil slette din konto? Dette kan ikke fortrydes.', 'rigtig-for-mig'); ?></p>
                <p><strong><?php _e('Alle dine data vil blive permanent slettet:', 'rigtig-for-mig'); ?></strong></p>
                <ul>
                    <li><?php _e('Din profil og profilbillede', 'rigtig-for-mig'); ?></li>
                    <li><?php _e('Alle dine beskeder', 'rigtig-for-mig'); ?></li>
                    <li><?php _e('Din login-information', 'rigtig-for-mig'); ?></li>
                </ul>
                
                <div class="rfm-form-group">
                    <label for="delete_confirm_password"><?php _e('Bekræft med din adgangskode', 'rigtig-for-mig'); ?></label>
                    <input type="password" id="delete_confirm_password" name="delete_confirm_password">
                </div>
                
                <div class="rfm-modal-messages"></div>
                
                <div class="rfm-modal-actions">
                    <button type="button" id="rfm-confirm-delete" class="rfm-btn rfm-btn-danger">
                        <?php _e('Ja, slet min konto', 'rigtig-for-mig'); ?>
                    </button>
                    <button type="button" id="rfm-cancel-delete" class="rfm-btn rfm-btn-secondary">
                        <?php _e('Annuller', 'rigtig-for-mig'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user messages
     */
    private function get_user_messages($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_messages';
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name as expert_name 
             FROM $table m
             LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE m.recipient_id = %d OR m.sender_id = %d
             ORDER BY m.created_at DESC
             LIMIT 10",
            $user_id,
            $user_id
        ));
        
        if (empty($messages)) {
            return '<p>' . __('Du har ingen beskeder endnu.', 'rigtig-for-mig') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="rfm-messages-list">
            <?php foreach ($messages as $message): ?>
                <div class="rfm-message-item <?php echo $message->is_read ? '' : 'unread'; ?>">
                    <div class="rfm-message-header">
                        <strong><?php echo esc_html($message->expert_name); ?></strong>
                        <span class="rfm-message-date"><?php echo date_i18n(get_option('date_format'), strtotime($message->created_at)); ?></span>
                    </div>
                    <div class="rfm-message-content">
                        <?php echo esc_html(wp_trim_words($message->message, 20)); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="rfm-info-text"><?php _e('Beskedsystemet vil blive fuldt funktionelt snart.', 'rigtig-for-mig'); ?></p>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user ratings display
     */
    private function get_user_ratings_display($user_id) {
        $ratings_system = RFM_Ratings::get_instance();
        $ratings = $ratings_system->get_user_ratings($user_id);
        
        if (empty($ratings)) {
            return '<p>' . __('Du har ikke skrevet nogen anmeldelser endnu.', 'rigtig-for-mig') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="rfm-user-ratings-list">
            <?php foreach ($ratings as $rating): ?>
                <div class="rfm-user-rating-item">
                    <div class="rfm-rating-header">
                        <h4>
                            <a href="<?php echo get_permalink($rating->expert_id); ?>">
                                <?php echo esc_html($rating->expert_name); ?>
                            </a>
                        </h4>
                        <div class="rfm-rating-meta">
                            <?php echo RFM_Ratings::display_stars($rating->rating, false); ?>
                            <span class="rfm-rating-date">
                                <?php echo date_i18n(get_option('date_format'), strtotime($rating->created_at)); ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($rating->review)): ?>
                        <div class="rfm-rating-review">
                            <p><?php echo esc_html($rating->review); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="rfm-rating-actions">
                        <?php
                        $can_update = $ratings_system->can_user_rate($rating->expert_id, $user_id);
                        if ($can_update): ?>
                            <a href="<?php echo get_permalink($rating->expert_id); ?>#bedommelser" class="rfm-btn rfm-btn-sm rfm-btn-secondary">
                                <?php _e('Opdater anmeldelse', 'rigtig-for-mig'); ?>
                            </a>
                        <?php else: 
                            // Calculate days until can update
                            global $wpdb;
                            $table = $wpdb->prefix . 'rfm_ratings';
                            $existing = $wpdb->get_row($wpdb->prepare(
                                "SELECT created_at FROM $table WHERE expert_id = %d AND user_id = %d",
                                $rating->expert_id,
                                $user_id
                            ));
                            $days_since = floor((time() - strtotime($existing->created_at)) / (60 * 60 * 24));
                            $days_remaining = 180 - $days_since;
                        ?>
                            <span class="rfm-rating-cooldown">
                                <?php printf(__('Du kan opdatere din anmeldelse om %d dage', 'rigtig-for-mig'), $days_remaining); ?>
                            </span>
                        <?php endif; ?>
                        
                        <a href="<?php echo get_permalink($rating->expert_id); ?>" class="rfm-btn rfm-btn-sm rfm-btn-secondary">
                            <?php _e('Se ekspertprofil', 'rigtig-for-mig'); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Ensure user profile row exists in database
     * Creates row if missing to prevent UPDATE failures
     *
     * @param int $user_id WordPress user ID
     * @return bool True if row exists or was created successfully
     */
    private function ensure_user_profile_exists($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_user_profiles';

        // Check if profile exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d",
            $user_id
        ));

        if (!$exists) {
            // Create profile row
            $result = $wpdb->insert(
                $table,
                array(
                    'user_id' => $user_id,
                    'account_created_at' => current_time('mysql')
                ),
                array('%d', '%s')
            );

            if ($result === false) {
                error_log('RFM ERROR: Failed to create user profile row for user ' . $user_id);
                error_log('RFM ERROR: ' . $wpdb->last_error);
                return false;
            }

            error_log('RFM INFO: Created user profile row for user ' . $user_id);
        }

        return true;
    }

    /**
     * Handle profile update
     */
    public function handle_profile_update() {
        // DEBUG: CRITICAL - Log that handler is called (this should appear if WordPress routes the request here)
        error_log('=== RFM DEBUG START ===');
        error_log('RFM DEBUG: handle_profile_update CALLED at ' . current_time('mysql'));
        error_log('RFM DEBUG: REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
        error_log('RFM DEBUG: REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
        error_log('RFM DEBUG: POST data: ' . print_r($_POST, true));
        error_log('RFM DEBUG: User ID: ' . get_current_user_id());
        error_log('RFM DEBUG: Is user logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));

        // Check if this is being called
        if (!is_user_logged_in()) {
            error_log('RFM DEBUG: User not logged in BEFORE nonce check - this should not happen');
            wp_send_json_error(array('message' => 'DEBUG: Not logged in before nonce'));
            return;
        }

        check_ajax_referer('rfm_nonce', 'nonce');

        error_log('RFM DEBUG: Nonce check PASSED');

        if (!is_user_logged_in()) {
            error_log('RFM DEBUG: User not logged in - sending error');
            wp_send_json_error(array('message' => __('Du skal være logget ind', 'rigtig-for-mig')));
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        error_log('RFM DEBUG: User roles: ' . print_r($user->roles, true));

        // Check role
        if (!in_array('rfm_user', $user->roles)) {
            error_log('RFM DEBUG: User does not have rfm_user role - sending error');
            wp_send_json_error(array('message' => __('Uautoriseret adgang', 'rigtig-for-mig')));
        }

        error_log('RFM DEBUG: Role check PASSED');
        
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_user_profiles';
        
        // Handle data download (GDPR)
        if (isset($_POST['download_data']) && $_POST['download_data']) {
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d",
                $user_id
            ), ARRAY_A);
            
            $ratings = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}rfm_ratings WHERE user_id = %d",
                $user_id
            ), ARRAY_A);
            
            $user_data = array(
                'user_info' => array(
                    'ID' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name,
                    'registered' => $user->user_registered
                ),
                'profile' => $profile,
                'ratings' => $ratings,
                'export_date' => current_time('mysql')
            );
            
            wp_send_json_success(array(
                'user_data' => $user_data,
                'message' => __('Data klar til download', 'rigtig-for-mig')
            ));
            return;
        }
        
        // Handle password change
        if (isset($_POST['new_password']) && !empty($_POST['new_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            
            if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
                wp_send_json_error(array('message' => __('Nuværende adgangskode er forkert', 'rigtig-for-mig')));
            }
            
            wp_set_password($new_password, $user_id);
            wp_send_json_success(array('message' => __('Adgangskode ændret succesfuldt', 'rigtig-for-mig')));
            return;
        }
        
        // Update profile data
        $display_name = sanitize_text_field($_POST['display_name']);
        $phone = sanitize_text_field($_POST['phone']);
        $bio = sanitize_textarea_field($_POST['bio']);
        
        // Update WordPress user
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name
        ));

        // Ensure profile row exists before updating
        if (!$this->ensure_user_profile_exists($user_id)) {
            wp_send_json_error(array('message' => __('Kunne ikke oprette profil i databasen', 'rigtig-for-mig')));
            return;
        }

        // Update custom profile
        $result = $wpdb->update(
            $table,
            array(
                'phone' => $phone,
                'bio' => $bio
            ),
            array('user_id' => $user_id),
            array('%s', '%s'),
            array('%d')
        );

        // Check if update succeeded
        if ($result === false) {
            error_log('RFM ERROR: Failed to update user profile for user ' . $user_id);
            error_log('RFM ERROR: ' . $wpdb->last_error);
            wp_send_json_error(array('message' => __('Kunne ikke opdatere profil', 'rigtig-for-mig')));
            return;
        }

        // Clear caches after profile update
        wp_cache_delete($user_id, 'users');
        wp_cache_delete($user_id, 'user_meta');

        // Clear plugin caches
        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }
        do_action('litespeed_purge_all');
        do_action('w3tc_flush_all');

        wp_send_json_success(array('message' => __('Profil opdateret succesfuldt', 'rigtig-for-mig')));
    }
    
    /**
     * Handle avatar upload
     */
    public function handle_avatar_upload() {
        check_ajax_referer('rfm_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind', 'rigtig-for-mig')));
        }

        $user_id = get_current_user_id();

        if (empty($_FILES['avatar'])) {
            wp_send_json_error(array('message' => __('Ingen fil uploadet', 'rigtig-for-mig')));
        }

        // Validate file size (2MB max)
        if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('Billedet må maksimalt være 2 MB', 'rigtig-for-mig')));
        }

        // Validate file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        $file_type = $_FILES['avatar']['type'];
        if (!in_array($file_type, $allowed_types)) {
            wp_send_json_error(array('message' => __('Kun JPG, PNG og GIF er tilladt', 'rigtig-for-mig')));
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('avatar', 0);

        if (is_wp_error($attachment_id)) {
            error_log('RFM ERROR: Avatar upload failed - ' . $attachment_id->get_error_message());
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        $image_url = wp_get_attachment_url($attachment_id);

        // Ensure profile row exists
        if (!$this->ensure_user_profile_exists($user_id)) {
            // Cleanup uploaded file since we can't save reference
            wp_delete_attachment($attachment_id, true);
            wp_send_json_error(array('message' => __('Kunne ikke oprette profil i databasen', 'rigtig-for-mig')));
            return;
        }

        // Update profile
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_user_profiles';

        $result = $wpdb->update(
            $table,
            array('profile_image' => $image_url),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );

        // Check if update succeeded
        if ($result === false) {
            error_log('RFM ERROR: Failed to save avatar URL for user ' . $user_id);
            error_log('RFM ERROR: ' . $wpdb->last_error);
            // Cleanup uploaded file since save failed
            wp_delete_attachment($attachment_id, true);
            wp_send_json_error(array('message' => __('Kunne ikke gemme profilbillede i databasen', 'rigtig-for-mig')));
            return;
        }

        // Clear caches
        wp_cache_delete($user_id, 'users');
        wp_cache_delete($user_id, 'user_meta');

        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }
        do_action('litespeed_purge_all');
        do_action('w3tc_flush_all');

        wp_send_json_success(array(
            'message' => __('Profilbillede uploadet succesfuldt', 'rigtig-for-mig'),
            'avatar_url' => $image_url
        ));
    }
    
    /**
     * Handle account deletion (GDPR)
     */
    public function handle_account_deletion() {
        check_ajax_referer('rfm_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind', 'rigtig-for-mig')));
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $password = $_POST['password'];
        
        // Verify password
        if (!wp_check_password($password, $user->user_pass, $user_id)) {
            wp_send_json_error(array('message' => __('Forkert adgangskode', 'rigtig-for-mig')));
        }
        
        // Delete all user data (GDPR compliance)
        global $wpdb;
        
        // Delete profile
        $wpdb->delete($wpdb->prefix . 'rfm_user_profiles', array('user_id' => $user_id));
        
        // Delete messages
        $wpdb->delete($wpdb->prefix . 'rfm_messages', array('sender_id' => $user_id));
        $wpdb->delete($wpdb->prefix . 'rfm_messages', array('recipient_id' => $user_id));
        
        // Delete threads
        $wpdb->delete($wpdb->prefix . 'rfm_message_threads', array('user_id' => $user_id));
        
        // Delete ratings
        $wpdb->delete($wpdb->prefix . 'rfm_ratings', array('user_id' => $user_id));
        
        // Delete WordPress user
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user_id);
        
        // Logout
        wp_logout();
        
        wp_send_json_success(array('message' => __('Din konto er nu slettet', 'rigtig-for-mig')));
    }
}
