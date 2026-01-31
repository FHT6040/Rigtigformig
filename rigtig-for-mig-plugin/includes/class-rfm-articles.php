<?php
/**
 * Article System for Experts
 *
 * Handles article creation, limits by subscription tier, and admin approval workflow.
 *
 * Article limits:
 * - Gratis: 0 articles
 * - Standard: 4 articles/year (calendar year)
 * - Premium: 1 article/month (calendar month)
 *
 * Approval workflow:
 * - Expert submits article -> status 'pending'
 * - Admin approves -> status 'publish'
 * - Admin rejects -> status 'draft' with _rfm_article_rejected meta
 * - Rejected articles do NOT count toward quota
 * - Published articles remain visible even after downgrade
 *
 * @package Rigtig_For_Mig
 * @since 3.13.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Articles {

    private static $instance = null;

    /**
     * Article limits per subscription tier
     */
    const LIMITS = array(
        'free'     => 0,
        'standard' => 4,   // per year
        'premium'  => 1,   // per month
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin columns for article post type
        add_filter('manage_rfm_article_posts_columns', array($this, 'add_article_columns'));
        add_action('manage_rfm_article_posts_custom_column', array($this, 'render_article_columns'), 10, 2);

        // Admin meta boxes
        add_action('add_meta_boxes', array($this, 'add_article_meta_boxes'));
        add_action('save_post_rfm_article', array($this, 'save_article_meta'), 10, 2);

        // Admin approval actions
        add_action('admin_post_rfm_approve_article', array($this, 'handle_approve_article'));
        add_action('admin_post_rfm_reject_article', array($this, 'handle_reject_article'));

        // Admin notices for pending articles
        add_action('admin_notices', array($this, 'pending_articles_notice'));

        // Template redirect for single articles
        add_filter('single_template', array($this, 'article_template'));

        // Add articles to category archive
        add_action('pre_get_posts', array($this, 'include_articles_in_queries'));

        // AJAX handlers (via wp_ajax)
        add_action('wp_ajax_rfm_save_article', array($this, 'ajax_save_article'));
        add_action('wp_ajax_rfm_delete_article', array($this, 'ajax_delete_article'));
        add_action('wp_ajax_rfm_upload_article_image', array($this, 'ajax_upload_article_image'));
    }

    /**
     * Get article limit for a subscription plan
     *
     * @param string $plan Subscription plan (free/standard/premium)
     * @return int Article limit
     */
    public static function get_limit($plan) {
        $limits = self::LIMITS;
        return isset($limits[$plan]) ? $limits[$plan] : 0;
    }

    /**
     * Get the period type for a subscription plan
     *
     * @param string $plan Subscription plan
     * @return string 'year', 'month', or 'none'
     */
    public static function get_period_type($plan) {
        switch ($plan) {
            case 'premium':
                return 'month';
            case 'standard':
                return 'year';
            default:
                return 'none';
        }
    }

    /**
     * Count articles used by expert in current period
     *
     * Counts published + pending articles (rejected do NOT count)
     *
     * @param int $expert_id Expert post ID
     * @param string $plan Subscription plan
     * @return int Number of articles used
     */
    public function count_articles_in_period($expert_id, $plan) {
        $period = self::get_period_type($plan);

        if ($period === 'none') {
            return 0;
        }

        $args = array(
            'post_type'      => 'rfm_article',
            'post_status'    => array('publish', 'pending'),
            'meta_query'     => array(
                array(
                    'key'   => '_rfm_expert_id',
                    'value' => $expert_id,
                ),
            ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );

        // Add date query based on period
        if ($period === 'year') {
            $args['date_query'] = array(
                array(
                    'year' => date('Y'),
                ),
            );
        } elseif ($period === 'month') {
            $args['date_query'] = array(
                array(
                    'year'  => date('Y'),
                    'month' => date('n'),
                ),
            );
        }

        $query = new WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Check if expert can create a new article
     *
     * @param int $expert_id Expert post ID
     * @return array ['allowed' => bool, 'reason' => string, 'used' => int, 'limit' => int]
     */
    public function can_create_article($expert_id) {
        $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);
        $limit = self::get_limit($plan);

        if ($limit === 0) {
            return array(
                'allowed' => false,
                'reason'  => __('Dit abonnement tillader ikke artikler. Opgrader til Standard eller Premium.', 'rigtig-for-mig'),
                'used'    => 0,
                'limit'   => 0,
                'plan'    => $plan,
            );
        }

        $used = $this->count_articles_in_period($expert_id, $plan);

        if ($used >= $limit) {
            $period_label = self::get_period_type($plan) === 'month'
                ? __('denne måned', 'rigtig-for-mig')
                : __('dette år', 'rigtig-for-mig');

            return array(
                'allowed' => false,
                'reason'  => sprintf(
                    __('Du har brugt alle dine artikler %s (%d af %d).', 'rigtig-for-mig'),
                    $period_label,
                    $used,
                    $limit
                ),
                'used'    => $used,
                'limit'   => $limit,
                'plan'    => $plan,
            );
        }

        return array(
            'allowed' => true,
            'reason'  => '',
            'used'    => $used,
            'limit'   => $limit,
            'plan'    => $plan,
        );
    }

    /**
     * Get articles by expert
     *
     * @param int $expert_id Expert post ID
     * @param string $status Post status filter ('any', 'publish', 'pending', 'draft')
     * @param int $limit Number of articles to return
     * @return WP_Post[] Array of article posts
     */
    public function get_expert_articles($expert_id, $status = 'any', $limit = -1) {
        $args = array(
            'post_type'      => 'rfm_article',
            'post_status'    => $status,
            'meta_query'     => array(
                array(
                    'key'   => '_rfm_expert_id',
                    'value' => $expert_id,
                ),
            ),
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        return get_posts($args);
    }

    /**
     * Get articles by category
     *
     * @param int $category_id Category term ID
     * @param int $limit Number of articles to return
     * @param int $offset Offset for pagination
     * @return WP_Post[] Array of article posts
     */
    public function get_articles_by_category($category_id, $limit = 10, $offset = 0) {
        return get_posts(array(
            'post_type'      => 'rfm_article',
            'post_status'    => 'publish',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'rfm_category',
                    'terms'    => $category_id,
                ),
            ),
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));
    }

    /**
     * Get latest published articles
     *
     * @param int $limit Number of articles
     * @return WP_Post[] Array of articles
     */
    public function get_latest_articles($limit = 10) {
        return get_posts(array(
            'post_type'      => 'rfm_article',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));
    }

    /**
     * Get the expert post linked to an article
     *
     * @param int $article_id Article post ID
     * @return WP_Post|null Expert post or null
     */
    public function get_article_expert($article_id) {
        $expert_id = get_post_meta($article_id, '_rfm_expert_id', true);
        if (!$expert_id) {
            return null;
        }
        return get_post($expert_id);
    }

    /**
     * Check if an edit is "major" (requires re-approval)
     *
     * Major edit: title changed OR content changed by more than 20%
     *
     * @param int $article_id Article post ID
     * @param string $new_title New title
     * @param string $new_content New content
     * @return bool True if major edit
     */
    public function is_major_edit($article_id, $new_title, $new_content) {
        $post = get_post($article_id);
        if (!$post) {
            return true;
        }

        // Title changed
        if ($post->post_title !== $new_title) {
            return true;
        }

        // Content changed significantly (>20%)
        $old_length = strlen($post->post_content);
        $new_length = strlen($new_content);

        if ($old_length === 0) {
            return $new_length > 0;
        }

        $diff = abs($old_length - $new_length);
        $change_ratio = $diff / $old_length;

        if ($change_ratio > 0.2) {
            return true;
        }

        // Check actual text difference using similar_text
        similar_text($post->post_content, $new_content, $similarity);
        if ($similarity < 80) {
            return true;
        }

        return false;
    }

    /**
     * Get status label in Danish
     *
     * @param string $status Post status
     * @param int $article_id Optional article ID to check if rejected
     * @return string Danish label
     */
    public static function get_status_label($status, $article_id = 0) {
        if ($status === 'draft' && $article_id) {
            $rejected = get_post_meta($article_id, '_rfm_article_rejected', true);
            if ($rejected) {
                return __('Afvist', 'rigtig-for-mig');
            }
        }

        switch ($status) {
            case 'publish':
                return __('Publiceret', 'rigtig-for-mig');
            case 'pending':
                return __('Afventer godkendelse', 'rigtig-for-mig');
            case 'draft':
                return __('Kladde', 'rigtig-for-mig');
            case 'trash':
                return __('Slettet', 'rigtig-for-mig');
            default:
                return ucfirst($status);
        }
    }

    /**
     * Get status color for styling
     *
     * @param string $status Post status
     * @param int $article_id Optional article ID
     * @return string CSS color
     */
    public static function get_status_color($status, $article_id = 0) {
        if ($status === 'draft' && $article_id) {
            $rejected = get_post_meta($article_id, '_rfm_article_rejected', true);
            if ($rejected) {
                return '#e74c3c';
            }
        }

        switch ($status) {
            case 'publish':
                return '#27ae60';
            case 'pending':
                return '#f39c12';
            case 'draft':
                return '#95a5a6';
            default:
                return '#666';
        }
    }

    // =========================================================================
    // AJAX Handlers
    // =========================================================================

    /**
     * Save article via AJAX (create or update)
     */
    public function ajax_save_article() {
        check_ajax_referer('rfm_dashboard_tabbed', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }

        $user_id = get_current_user_id();
        $expert_id = intval($_POST['expert_id'] ?? 0);
        $article_id = intval($_POST['article_id'] ?? 0);

        // Verify ownership
        $expert_post = get_post($expert_id);
        if (!$expert_post || $expert_post->post_author != $user_id) {
            wp_send_json_error(array('message' => __('Du har ikke tilladelse.', 'rigtig-for-mig')));
        }

        // Sanitize input
        $title = sanitize_text_field($_POST['article_title'] ?? '');
        $content = wp_kses_post($_POST['article_content'] ?? '');
        $category_id = intval($_POST['article_category'] ?? 0);
        $image_id = intval($_POST['article_image_id'] ?? 0);

        if (empty($title)) {
            wp_send_json_error(array('message' => __('Artiklen skal have en titel.', 'rigtig-for-mig')));
        }

        if (empty($content)) {
            wp_send_json_error(array('message' => __('Artiklen skal have indhold.', 'rigtig-for-mig')));
        }

        if (!$category_id) {
            wp_send_json_error(array('message' => __('Vælg en kategori for artiklen.', 'rigtig-for-mig')));
        }

        // Verify expert has this category
        $expert_categories = wp_get_object_terms($expert_id, 'rfm_category', array('fields' => 'ids'));
        if (!in_array($category_id, $expert_categories)) {
            wp_send_json_error(array('message' => __('Du kan kun skrive artikler i dine egne kategorier.', 'rigtig-for-mig')));
        }

        // UPDATE existing article
        if ($article_id) {
            $existing = get_post($article_id);
            if (!$existing || get_post_meta($article_id, '_rfm_expert_id', true) != $expert_id) {
                wp_send_json_error(array('message' => __('Artiklen blev ikke fundet.', 'rigtig-for-mig')));
            }

            // Determine if this is a major edit (requires re-approval)
            $was_published = ($existing->post_status === 'publish');
            $needs_reapproval = $was_published && $this->is_major_edit($article_id, $title, $content);

            $new_status = $needs_reapproval ? 'pending' : $existing->post_status;

            wp_update_post(array(
                'ID'           => $article_id,
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => $new_status,
            ));

            // Update category
            wp_set_object_terms($article_id, array($category_id), 'rfm_category');

            // Update image
            if ($image_id) {
                set_post_thumbnail($article_id, $image_id);
            } else {
                delete_post_thumbnail($article_id);
            }

            // Clear rejected flag if re-submitting
            delete_post_meta($article_id, '_rfm_article_rejected');
            delete_post_meta($article_id, '_rfm_article_reject_reason');

            $message = $needs_reapproval
                ? __('Artiklen er opdateret og sendt til godkendelse igen.', 'rigtig-for-mig')
                : __('Artiklen er opdateret.', 'rigtig-for-mig');

            wp_send_json_success(array(
                'message'    => $message,
                'article_id' => $article_id,
                'status'     => $new_status,
            ));
        }

        // CREATE new article
        $can_create = $this->can_create_article($expert_id);
        if (!$can_create['allowed']) {
            wp_send_json_error(array('message' => $can_create['reason']));
        }

        $article_id = wp_insert_post(array(
            'post_type'    => 'rfm_article',
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'pending',
            'post_author'  => $user_id,
        ));

        if (is_wp_error($article_id)) {
            wp_send_json_error(array('message' => $article_id->get_error_message()));
        }

        // Set meta
        update_post_meta($article_id, '_rfm_expert_id', $expert_id);

        // Set category
        wp_set_object_terms($article_id, array($category_id), 'rfm_category');

        // Set featured image
        if ($image_id) {
            set_post_thumbnail($article_id, $image_id);
        }

        wp_send_json_success(array(
            'message'    => __('Artiklen er indsendt til godkendelse!', 'rigtig-for-mig'),
            'article_id' => $article_id,
            'status'     => 'pending',
        ));
    }

    /**
     * Delete article via AJAX
     */
    public function ajax_delete_article() {
        check_ajax_referer('rfm_dashboard_tabbed', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }

        $user_id = get_current_user_id();
        $expert_id = intval($_POST['expert_id'] ?? 0);
        $article_id = intval($_POST['article_id'] ?? 0);

        // Verify ownership
        $expert_post = get_post($expert_id);
        if (!$expert_post || $expert_post->post_author != $user_id) {
            wp_send_json_error(array('message' => __('Du har ikke tilladelse.', 'rigtig-for-mig')));
        }

        // Verify article belongs to expert
        if (get_post_meta($article_id, '_rfm_expert_id', true) != $expert_id) {
            wp_send_json_error(array('message' => __('Artiklen blev ikke fundet.', 'rigtig-for-mig')));
        }

        wp_trash_post($article_id);

        wp_send_json_success(array(
            'message' => __('Artiklen er slettet.', 'rigtig-for-mig'),
        ));
    }

    /**
     * Upload article image via AJAX
     */
    public function ajax_upload_article_image() {
        check_ajax_referer('rfm_dashboard_tabbed', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }

        $user_id = get_current_user_id();
        $expert_id = intval($_POST['expert_id'] ?? 0);

        // Verify ownership
        $expert_post = get_post($expert_id);
        if (!$expert_post || $expert_post->post_author != $user_id) {
            wp_send_json_error(array('message' => __('Du har ikke tilladelse.', 'rigtig-for-mig')));
        }

        // Check subscription allows articles
        $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);
        if ($plan === 'free') {
            wp_send_json_error(array('message' => __('Dit abonnement tillader ikke artikler.', 'rigtig-for-mig')));
        }

        if (empty($_FILES['article_image'])) {
            wp_send_json_error(array('message' => __('Ingen fil blev uploadet.', 'rigtig-for-mig')));
        }

        $file = $_FILES['article_image'];

        // Validate file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($real_mime, $allowed_types)) {
            wp_send_json_error(array('message' => __('Ugyldig filtype. Kun JPG, PNG, GIF og WebP er tilladt.', 'rigtig-for-mig')));
        }

        // Validate file extension
        $filename = sanitize_file_name($file['name']);
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($file_ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
            wp_send_json_error(array('message' => __('Ugyldig fil-extension.', 'rigtig-for-mig')));
        }

        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('Filen er for stor. Maksimum 5MB.', 'rigtig-for-mig')));
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('article_image', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        // Tag as RFM upload
        update_post_meta($attachment_id, '_rfm_owner_type', 'rfm_article');
        update_post_meta($attachment_id, '_rfm_owner_id', $expert_id);
        update_post_meta($attachment_id, '_rfm_upload_type', 'article_image');
        update_post_meta($attachment_id, '_rfm_upload_date', current_time('mysql'));

        $image_url = wp_get_attachment_image_url($attachment_id, 'medium');

        wp_send_json_success(array(
            'message'       => __('Billede uploadet!', 'rigtig-for-mig'),
            'attachment_id' => $attachment_id,
            'image_url'     => $image_url,
        ));
    }

    // =========================================================================
    // Admin Interface
    // =========================================================================

    /**
     * Add custom columns to article list in admin
     */
    public function add_article_columns($columns) {
        $new_columns = array(
            'cb'                    => $columns['cb'],
            'title'                 => $columns['title'],
            'rfm_article_expert'    => __('Ekspert', 'rigtig-for-mig'),
            'rfm_article_category'  => __('Kategori', 'rigtig-for-mig'),
            'rfm_article_status'    => __('Status', 'rigtig-for-mig'),
            'rfm_article_image'     => __('Billede', 'rigtig-for-mig'),
            'date'                  => $columns['date'],
        );
        return $new_columns;
    }

    /**
     * Render custom admin columns
     */
    public function render_article_columns($column, $post_id) {
        switch ($column) {
            case 'rfm_article_expert':
                $expert_id = get_post_meta($post_id, '_rfm_expert_id', true);
                if ($expert_id) {
                    $expert = get_post($expert_id);
                    if ($expert) {
                        $edit_link = get_edit_post_link($expert_id);
                        echo '<a href="' . esc_url($edit_link) . '">' . esc_html($expert->post_title) . '</a>';
                        $plan = get_post_meta($expert_id, '_rfm_subscription_plan', true);
                        echo '<br><small>' . ucfirst($plan ?: 'free') . '</small>';
                    }
                }
                break;

            case 'rfm_article_category':
                $terms = wp_get_object_terms($post_id, 'rfm_category');
                if (!empty($terms) && !is_wp_error($terms)) {
                    $names = array_map(function($t) { return $t->name; }, $terms);
                    echo esc_html(implode(', ', $names));
                }
                break;

            case 'rfm_article_status':
                $status = get_post_status($post_id);
                $label = self::get_status_label($status, $post_id);
                $color = self::get_status_color($status, $post_id);
                echo '<span style="color: ' . esc_attr($color) . '; font-weight: bold;">' . esc_html($label) . '</span>';

                // Show approve/reject buttons for pending articles
                if ($status === 'pending') {
                    $approve_url = wp_nonce_url(
                        admin_url('admin-post.php?action=rfm_approve_article&article_id=' . $post_id),
                        'rfm_approve_article_' . $post_id
                    );
                    $reject_url = wp_nonce_url(
                        admin_url('admin-post.php?action=rfm_reject_article&article_id=' . $post_id),
                        'rfm_reject_article_' . $post_id
                    );
                    echo '<br>';
                    echo '<a href="' . esc_url($approve_url) . '" class="button button-small" style="color: green;">Godkend</a> ';
                    echo '<a href="' . esc_url($reject_url) . '" class="button button-small" style="color: red;">Afvis</a>';
                }
                break;

            case 'rfm_article_image':
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, array(60, 60), array('style' => 'border-radius: 4px;'));
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;
        }
    }

    /**
     * Add meta boxes for article editing in admin
     */
    public function add_article_meta_boxes() {
        add_meta_box(
            'rfm_article_info',
            __('Artikel Information', 'rigtig-for-mig'),
            array($this, 'render_article_info_meta_box'),
            'rfm_article',
            'side',
            'high'
        );

        add_meta_box(
            'rfm_article_approval',
            __('Godkendelse', 'rigtig-for-mig'),
            array($this, 'render_article_approval_meta_box'),
            'rfm_article',
            'side',
            'high'
        );
    }

    /**
     * Render article info meta box
     */
    public function render_article_info_meta_box($post) {
        $expert_id = get_post_meta($post->ID, '_rfm_expert_id', true);
        $expert = $expert_id ? get_post($expert_id) : null;
        $rejected = get_post_meta($post->ID, '_rfm_article_rejected', true);
        $reject_reason = get_post_meta($post->ID, '_rfm_article_reject_reason', true);

        wp_nonce_field('rfm_article_meta_nonce', 'rfm_article_meta_nonce');
        ?>
        <p>
            <strong><?php _e('Ekspert:', 'rigtig-for-mig'); ?></strong><br>
            <?php if ($expert): ?>
                <a href="<?php echo esc_url(get_edit_post_link($expert_id)); ?>">
                    <?php echo esc_html($expert->post_title); ?>
                </a>
                <?php
                $plan = get_post_meta($expert_id, '_rfm_subscription_plan', true);
                echo ' (' . ucfirst($plan ?: 'free') . ')';
                ?>
            <?php else: ?>
                <em><?php _e('Ukendt', 'rigtig-for-mig'); ?></em>
            <?php endif; ?>
        </p>

        <?php if ($rejected): ?>
        <p style="background: #fff3cd; padding: 8px; border-left: 4px solid #e74c3c; margin-top: 10px;">
            <strong style="color: #e74c3c;"><?php _e('Afvist', 'rigtig-for-mig'); ?></strong><br>
            <?php if ($reject_reason): ?>
                <?php echo esc_html($reject_reason); ?>
            <?php endif; ?>
        </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render approval meta box
     */
    public function render_article_approval_meta_box($post) {
        $status = $post->post_status;
        ?>
        <?php if ($status === 'pending'): ?>
        <div style="text-align: center; padding: 10px;">
            <p><strong><?php _e('Denne artikel afventer godkendelse.', 'rigtig-for-mig'); ?></strong></p>
            <?php
            $approve_url = wp_nonce_url(
                admin_url('admin-post.php?action=rfm_approve_article&article_id=' . $post->ID),
                'rfm_approve_article_' . $post->ID
            );
            $reject_url = wp_nonce_url(
                admin_url('admin-post.php?action=rfm_reject_article&article_id=' . $post->ID),
                'rfm_reject_article_' . $post->ID
            );
            ?>
            <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary" style="margin-right: 10px;">
                <?php _e('Godkend', 'rigtig-for-mig'); ?>
            </a>
            <a href="<?php echo esc_url($reject_url); ?>" class="button" style="color: #e74c3c;">
                <?php _e('Afvis', 'rigtig-for-mig'); ?>
            </a>
        </div>
        <?php elseif ($status === 'publish'): ?>
        <p style="color: #27ae60; font-weight: bold; text-align: center;">
            <?php _e('Artiklen er godkendt og publiceret.', 'rigtig-for-mig'); ?>
        </p>
        <?php elseif ($status === 'draft'): ?>
            <?php if (get_post_meta($post->ID, '_rfm_article_rejected', true)): ?>
            <p style="color: #e74c3c; font-weight: bold; text-align: center;">
                <?php _e('Artiklen er afvist.', 'rigtig-for-mig'); ?>
            </p>
            <?php else: ?>
            <p style="color: #95a5a6; text-align: center;">
                <?php _e('Artiklen er en kladde.', 'rigtig-for-mig'); ?>
            </p>
            <?php endif; ?>
        <?php endif; ?>
        <?php
    }

    /**
     * Save article meta
     */
    public function save_article_meta($post_id, $post) {
        if (!isset($_POST['rfm_article_meta_nonce']) || !wp_verify_nonce($_POST['rfm_article_meta_nonce'], 'rfm_article_meta_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    /**
     * Handle article approval (admin action)
     */
    public function handle_approve_article() {
        $article_id = intval($_GET['article_id'] ?? 0);
        check_admin_referer('rfm_approve_article_' . $article_id);

        if (!current_user_can('manage_options')) {
            wp_die(__('Du har ikke tilladelse.', 'rigtig-for-mig'));
        }

        $article = get_post($article_id);
        if (!$article || $article->post_type !== 'rfm_article') {
            wp_die(__('Artikel ikke fundet.', 'rigtig-for-mig'));
        }

        wp_update_post(array(
            'ID'          => $article_id,
            'post_status' => 'publish',
        ));

        // Clear rejection flags
        delete_post_meta($article_id, '_rfm_article_rejected');
        delete_post_meta($article_id, '_rfm_article_reject_reason');

        // Notify expert
        $expert_id = get_post_meta($article_id, '_rfm_expert_id', true);
        do_action('rfm_article_approved', $article_id, $expert_id);

        wp_redirect(add_query_arg(
            array('rfm_article_action' => 'approved'),
            admin_url('edit.php?post_type=rfm_article')
        ));
        exit;
    }

    /**
     * Handle article rejection (admin action)
     */
    public function handle_reject_article() {
        $article_id = intval($_GET['article_id'] ?? 0);
        check_admin_referer('rfm_reject_article_' . $article_id);

        if (!current_user_can('manage_options')) {
            wp_die(__('Du har ikke tilladelse.', 'rigtig-for-mig'));
        }

        $article = get_post($article_id);
        if (!$article || $article->post_type !== 'rfm_article') {
            wp_die(__('Artikel ikke fundet.', 'rigtig-for-mig'));
        }

        wp_update_post(array(
            'ID'          => $article_id,
            'post_status' => 'draft',
        ));

        update_post_meta($article_id, '_rfm_article_rejected', '1');

        // Notify expert
        $expert_id = get_post_meta($article_id, '_rfm_expert_id', true);
        do_action('rfm_article_rejected', $article_id, $expert_id);

        wp_redirect(add_query_arg(
            array('rfm_article_action' => 'rejected'),
            admin_url('edit.php?post_type=rfm_article')
        ));
        exit;
    }

    /**
     * Show admin notice for pending articles
     */
    public function pending_articles_notice() {
        $screen = get_current_screen();

        // Only show on relevant pages
        if (!$screen || !in_array($screen->id, array('dashboard', 'edit-rfm_article', 'toplevel_page_rfm-dashboard'))) {
            return;
        }

        $pending_count = wp_count_posts('rfm_article')->pending;

        if ($pending_count > 0) {
            $url = admin_url('edit.php?post_type=rfm_article&post_status=pending');
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Rigtig for mig:', 'rigtig-for-mig'); ?></strong>
                    <?php printf(
                        _n(
                            '%d artikel afventer godkendelse.',
                            '%d artikler afventer godkendelse.',
                            $pending_count,
                            'rigtig-for-mig'
                        ),
                        $pending_count
                    ); ?>
                    <a href="<?php echo esc_url($url); ?>"><?php _e('Se artikler', 'rigtig-for-mig'); ?></a>
                </p>
            </div>
            <?php
        }

        // Show success message after approval/rejection
        if (isset($_GET['rfm_article_action'])) {
            $action_result = sanitize_text_field($_GET['rfm_article_action']);
            if ($action_result === 'approved') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Artiklen er godkendt og publiceret.', 'rigtig-for-mig') . '</p></div>';
            } elseif ($action_result === 'rejected') {
                echo '<div class="notice notice-info is-dismissible"><p>' . __('Artiklen er afvist.', 'rigtig-for-mig') . '</p></div>';
            }
        }
    }

    /**
     * Use custom template for single articles
     */
    public function article_template($template) {
        global $post;

        if ($post && $post->post_type === 'rfm_article') {
            $custom_template = RFM_PLUGIN_DIR . 'templates/single-article.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

    /**
     * Include articles in category queries if relevant
     */
    public function include_articles_in_queries($query) {
        // Only modify main frontend queries
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // Include articles in rfm_category taxonomy archive
        if ($query->is_tax('rfm_category')) {
            $post_types = $query->get('post_type');
            if (empty($post_types)) {
                $post_types = array('rfm_expert');
            } elseif (!is_array($post_types)) {
                $post_types = array($post_types);
            }
            if (!in_array('rfm_article', $post_types)) {
                $post_types[] = 'rfm_article';
            }
            $query->set('post_type', $post_types);
        }
    }

    // =========================================================================
    // Dashboard Rendering
    // =========================================================================

    /**
     * Render article tab content for expert dashboard
     *
     * @param int $expert_id Expert post ID
     * @param string $plan Current subscription plan
     */
    public function render_dashboard_tab($expert_id, $plan) {
        $can_create = $this->can_create_article($expert_id);
        $articles = $this->get_expert_articles($expert_id);
        $expert_categories = wp_get_object_terms($expert_id, 'rfm_category', array('fields' => 'all'));

        ?>
        <div class="rfm-articles-dashboard">
            <h3><?php _e('Mine Artikler', 'rigtig-for-mig'); ?></h3>

            <!-- Article Stats -->
            <div class="rfm-article-stats">
                <?php if ($can_create['limit'] > 0): ?>
                    <div class="rfm-article-quota">
                        <span class="rfm-quota-used"><?php echo $can_create['used']; ?></span>
                        <span class="rfm-quota-separator">/</span>
                        <span class="rfm-quota-limit"><?php echo $can_create['limit']; ?></span>
                        <span class="rfm-quota-label">
                            <?php
                            $period = self::get_period_type($plan);
                            echo $period === 'month'
                                ? __('artikler denne måned', 'rigtig-for-mig')
                                : __('artikler dette år', 'rigtig-for-mig');
                            ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="rfm-upgrade-notice">
                        <?php _e('Dit abonnement tillader ikke artikler. Opgrader til Standard eller Premium for at skrive artikler.', 'rigtig-for-mig'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- New Article Button -->
            <?php if ($can_create['allowed']): ?>
            <div class="rfm-article-actions" style="margin-bottom: 20px;">
                <button type="button" class="rfm-btn rfm-btn-primary" id="rfm-new-article-btn">
                    + <?php _e('Skriv ny artikel', 'rigtig-for-mig'); ?>
                </button>
            </div>
            <?php endif; ?>

            <!-- Article Editor (hidden by default) -->
            <div id="rfm-article-editor" class="rfm-article-editor" style="display: none;">
                <form id="rfm-article-form" method="post">
                    <input type="hidden" name="expert_id" value="<?php echo esc_attr($expert_id); ?>" />
                    <input type="hidden" name="article_id" id="rfm-article-id" value="0" />
                    <input type="hidden" name="article_image_id" id="rfm-article-image-id" value="0" />

                    <div class="rfm-form-section">
                        <h4 id="rfm-article-editor-title"><?php _e('Ny artikel', 'rigtig-for-mig'); ?></h4>

                        <div class="rfm-form-field">
                            <label for="rfm-article-title"><?php _e('Titel', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                            <input type="text" id="rfm-article-title" name="article_title" required
                                   placeholder="<?php esc_attr_e('Giv din artikel en titel...', 'rigtig-for-mig'); ?>" />
                        </div>

                        <div class="rfm-form-field">
                            <label for="rfm-article-category"><?php _e('Kategori', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                            <select id="rfm-article-category" name="article_category" required>
                                <option value=""><?php _e('– Vælg kategori –', 'rigtig-for-mig'); ?></option>
                                <?php foreach ($expert_categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>">
                                    <?php echo esc_html($cat->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="rfm-form-field">
                            <label for="rfm-article-content"><?php _e('Indhold', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                            <textarea id="rfm-article-content" name="article_content" rows="12" required
                                      placeholder="<?php esc_attr_e('Skriv din artikel her...', 'rigtig-for-mig'); ?>"></textarea>
                        </div>

                        <!-- Article Image -->
                        <div class="rfm-form-field">
                            <label><?php _e('Artikelbillede', 'rigtig-for-mig'); ?></label>
                            <div class="rfm-article-image-upload">
                                <div id="rfm-article-image-preview" class="rfm-article-image-preview">
                                    <span class="rfm-no-image"><?php _e('Intet billede valgt', 'rigtig-for-mig'); ?></span>
                                </div>
                                <div class="rfm-image-buttons">
                                    <button type="button" class="rfm-btn rfm-btn-secondary rfm-btn-small" id="rfm-upload-article-image-btn">
                                        <?php _e('Upload billede', 'rigtig-for-mig'); ?>
                                    </button>
                                    <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger" id="rfm-remove-article-image-btn" style="display: none;">
                                        <?php _e('Fjern billede', 'rigtig-for-mig'); ?>
                                    </button>
                                </div>
                                <input type="file" id="rfm-article-image-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;" />
                                <small class="rfm-field-hint"><?php _e('Anbefalet: 1200x630px, max 5MB. JPG, PNG, GIF eller WebP.', 'rigtig-for-mig'); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="rfm-form-submit rfm-article-submit-buttons">
                        <button type="submit" class="rfm-btn rfm-btn-primary rfm-btn-large" id="rfm-submit-article-btn">
                            <?php _e('Indsend til godkendelse', 'rigtig-for-mig'); ?>
                        </button>
                        <button type="button" class="rfm-btn rfm-btn-secondary" id="rfm-cancel-article-btn">
                            <?php _e('Annuller', 'rigtig-for-mig'); ?>
                        </button>
                    </div>

                    <div id="rfm-article-editor-message" class="rfm-message" style="display: none;"></div>
                </form>
            </div>

            <!-- Article List -->
            <div id="rfm-article-list" class="rfm-article-list">
                <?php if (empty($articles)): ?>
                    <div class="rfm-no-articles">
                        <p><?php _e('Du har ingen artikler endnu.', 'rigtig-for-mig'); ?></p>
                        <?php if ($can_create['limit'] > 0): ?>
                            <p><?php _e('Klik "Skriv ny artikel" for at komme i gang!', 'rigtig-for-mig'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($articles as $article):
                        $status = $article->post_status;
                        $status_label = self::get_status_label($status, $article->ID);
                        $status_color = self::get_status_color($status, $article->ID);
                        $categories = wp_get_object_terms($article->ID, 'rfm_category');
                        $cat_name = !empty($categories) ? $categories[0]->name : '';
                        $rejected = get_post_meta($article->ID, '_rfm_article_rejected', true);
                        $reject_reason = get_post_meta($article->ID, '_rfm_article_reject_reason', true);
                        $image_id = get_post_thumbnail_id($article->ID);
                    ?>
                    <div class="rfm-article-item" data-article-id="<?php echo esc_attr($article->ID); ?>">
                        <?php if ($image_id): ?>
                        <div class="rfm-article-item-image">
                            <?php echo get_the_post_thumbnail($article->ID, 'thumbnail'); ?>
                        </div>
                        <?php endif; ?>
                        <div class="rfm-article-item-content">
                            <h4 class="rfm-article-item-title">
                                <?php if ($status === 'publish'): ?>
                                    <a href="<?php echo get_permalink($article->ID); ?>" target="_blank">
                                        <?php echo esc_html($article->post_title); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo esc_html($article->post_title); ?>
                                <?php endif; ?>
                            </h4>
                            <div class="rfm-article-item-meta">
                                <span class="rfm-article-status" style="color: <?php echo esc_attr($status_color); ?>;">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                                <?php if ($cat_name): ?>
                                    <span class="rfm-article-category"><?php echo esc_html($cat_name); ?></span>
                                <?php endif; ?>
                                <span class="rfm-article-date"><?php echo get_the_date('j. M Y', $article->ID); ?></span>
                            </div>
                            <?php if ($rejected && $reject_reason): ?>
                            <div class="rfm-article-reject-reason">
                                <strong><?php _e('Afvisningsgrund:', 'rigtig-for-mig'); ?></strong>
                                <?php echo esc_html($reject_reason); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="rfm-article-item-actions">
                            <button type="button" class="rfm-btn rfm-btn-small rfm-edit-article-btn"
                                    data-article-id="<?php echo esc_attr($article->ID); ?>"
                                    data-title="<?php echo esc_attr($article->post_title); ?>"
                                    data-content="<?php echo esc_attr($article->post_content); ?>"
                                    data-category="<?php echo esc_attr(!empty($categories) ? $categories[0]->term_id : ''); ?>"
                                    data-image-id="<?php echo esc_attr($image_id); ?>"
                                    data-image-url="<?php echo esc_attr($image_id ? wp_get_attachment_image_url($image_id, 'medium') : ''); ?>">
                                <?php _e('Rediger', 'rigtig-for-mig'); ?>
                            </button>
                            <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-delete-article-btn"
                                    data-article-id="<?php echo esc_attr($article->ID); ?>">
                                <?php _e('Slet', 'rigtig-for-mig'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
