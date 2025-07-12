<?php
namespace AffiliateBloom;

if (!defined('ABSPATH')) {
  exit;
}

class Application {

  public static function init() {
      $instance = new self();
      return $instance;
  }

  public function __construct() {
      add_action('init', array($this, 'init_application_system'));
      add_shortcode('affiliate_bloom_application', array($this, 'render_application_form'));
      add_action('wp_ajax_submit_affiliate_application', array($this, 'handle_application_submission'));
      add_action('wp_ajax_nopriv_submit_affiliate_application', array($this, 'handle_application_submission'));
  }

  public function init_application_system() {
      $this->maybe_create_application_table();
  }

  public function render_application_form($atts = array()) {
      // Check if user is already logged in and has status
      if (is_user_logged_in()) {
          $user_id = get_current_user_id();
          $status = get_user_meta($user_id, 'affiliate_status', true);

          if ($status === 'approved') {
              return '<div class="affiliate-bloom-notice success">' .
                     __('You are already an approved affiliate!', 'affiliate-bloom') .
                     ' <a href="' . home_url('/affiliate-dashboard/') . '">' .
                     __('Go to Dashboard', 'affiliate-bloom') . '</a></div>';
          } elseif ($status === 'pending') {
              return '<div class="affiliate-bloom-notice info">' .
                     __('Your affiliate application is pending review.', 'affiliate-bloom') .
                     '</div>';
          } elseif ($status === 'rejected') {
              return '<div class="affiliate-bloom-notice error">' .
                     __('Your affiliate application was rejected. Please contact support for more information.', 'affiliate-bloom') .
                     '</div>';
          }
      }

      ob_start();
      ?>
      <div class="affiliate-bloom-application">
          <div class="application-header">
              <h2><?php _e('Become an Affiliate', 'affiliate-bloom'); ?></h2>
              <p><?php _e('Join our affiliate program and start earning commissions today!', 'affiliate-bloom'); ?></p>
          </div>

          <form id="affiliate-application-form" class="affiliate-application-form">
              <?php wp_nonce_field('affiliate_application_nonce', 'application_nonce'); ?>

              <div class="form-section">
                  <h3><?php _e('Personal Information', 'affiliate-bloom'); ?></h3>

                  <div class="form-row">
                      <div class="form-group">
                          <label for="first_name"><?php _e('First Name', 'affiliate-bloom'); ?> *</label>
                          <input type="text" id="first_name" name="first_name" required>
                      </div>
                      <div class="form-group">
                          <label for="last_name"><?php _e('Last Name', 'affiliate-bloom'); ?> *</label>
                          <input type="text" id="last_name" name="last_name" required>
                      </div>
                  </div>

                  <div class="form-row">
                      <div class="form-group">
                          <label for="email"><?php _e('Email Address', 'affiliate-bloom'); ?> *</label>
                          <input type="email" id="email" name="email" required>
                      </div>
                      <div class="form-group">
                          <label for="phone"><?php _e('Phone Number', 'affiliate-bloom'); ?></label>
                          <input type="tel" id="phone" name="phone">
                      </div>
                  </div>

                  <div class="form-group">
                      <label for="address"><?php _e('Address', 'affiliate-bloom'); ?></label>
                      <textarea id="address" name="address" rows="3"></textarea>
                  </div>
              </div>

              <div class="form-section">
                  <h3><?php _e('Business Information', 'affiliate-bloom'); ?></h3>

                  <div class="form-group">
                      <label for="website_url"><?php _e('Website/Blog URL', 'affiliate-bloom'); ?> *</label>
                      <input type="url" id="website_url" name="website_url" required>
                      <small><?php _e('Where will you promote our products?', 'affiliate-bloom'); ?></small>
                  </div>

                  <div class="form-row">
                      <div class="form-group">
                          <label for="monthly_visitors"><?php _e('Monthly Website Visitors', 'affiliate-bloom'); ?></label>
                          <select id="monthly_visitors" name="monthly_visitors">
                              <option value=""><?php _e('Select range', 'affiliate-bloom'); ?></option>
                              <option value="0-1000">0 - 1,000</option>
                              <option value="1000-5000">1,000 - 5,000</option>
                              <option value="5000-10000">5,000 - 10,000</option>
                              <option value="10000-50000">10,000 - 50,000</option>
                              <option value="50000+">50,000+</option>
                          </select>
                      </div>
                      <div class="form-group">
                          <label for="niche"><?php _e('Your Niche/Industry', 'affiliate-bloom'); ?></label>
                          <input type="text" id="niche" name="niche">
                      </div>
                  </div>

                  <div class="form-group">
                      <label for="social_media"><?php _e('Social Media Profiles', 'affiliate-bloom'); ?></label>
                      <textarea id="social_media" name="social_media" rows="3" placeholder="<?php _e('Facebook, Instagram, Twitter, YouTube, etc.', 'affiliate-bloom'); ?>"></textarea>
                  </div>
              </div>

              <div class="form-section">
                  <h3><?php _e('Marketing Experience', 'affiliate-bloom'); ?></h3>

                  <div class="form-group">
                      <label for="affiliate_experience"><?php _e('Affiliate Marketing Experience', 'affiliate-bloom'); ?></label>
                      <select id="affiliate_experience" name="affiliate_experience">
                          <option value=""><?php _e('Select experience level', 'affiliate-bloom'); ?></option>
                          <option value="beginner"><?php _e('Beginner (0-1 year)', 'affiliate-bloom'); ?></option>
                          <option value="intermediate"><?php _e('Intermediate (1-3 years)', 'affiliate-bloom'); ?></option>
                          <option value="advanced"><?php _e('Advanced (3+ years)', 'affiliate-bloom'); ?></option>
                      </select>
                  </div>

                  <div class="form-group">
                      <label for="other_programs"><?php _e('Other Affiliate Programs', 'affiliate-bloom'); ?></label>
                      <textarea id="other_programs" name="other_programs" rows="3" placeholder="<?php _e('List other affiliate programs you participate in', 'affiliate-bloom'); ?>"></textarea>
                  </div>

                  <div class="form-group">
                      <label for="promotion_methods"><?php _e('How will you promote our products?', 'affiliate-bloom'); ?> *</label>
                      <textarea id="promotion_methods" name="promotion_methods" rows="4" required placeholder="<?php _e('Describe your marketing strategy...', 'affiliate-bloom'); ?>"></textarea>
                  </div>
              </div>

              <div class="form-section">
                  <h3><?php _e('Payment Information', 'affiliate-bloom'); ?></h3>

                  <div class="form-group">
                      <label for="payment_method"><?php _e('Preferred Payment Method', 'affiliate-bloom'); ?></label>
                      <select id="payment_method" name="payment_method">
                          <option value=""><?php _e('Select payment method', 'affiliate-bloom'); ?></option>
                          <option value="paypal"><?php _e('PayPal', 'affiliate-bloom'); ?></option>
                          <option value="bank_transfer"><?php _e('Bank Transfer', 'affiliate-bloom'); ?></option>
                          <option value="check"><?php _e('Check', 'affiliate-bloom'); ?></option>
                      </select>
                  </div>

                  <div class="form-group" id="paypal_email_group" style="display: none;">
                      <label for="paypal_email"><?php _e('PayPal Email', 'affiliate-bloom'); ?></label>
                      <input type="email" id="paypal_email" name="paypal_email">
                  </div>

                  <div class="form-group" id="bank_details_group" style="display: none;">
                      <label for="bank_details"><?php _e('Bank Details', 'affiliate-bloom'); ?></label>
                      <textarea id="bank_details" name="bank_details" rows="4" placeholder="<?php _e('Bank name, account number, routing number, etc.', 'affiliate-bloom'); ?>"></textarea>
                  </div>
              </div>

              <div class="form-section">
                  <div class="form-group checkbox-group">
                      <label>
                          <input type="checkbox" id="agree_terms" name="agree_terms" required>
                          <?php printf(__('I agree to the <a href="%s" target="_blank">Terms and Conditions</a> and <a href="%s" target="_blank">Privacy Policy</a>', 'affiliate-bloom'), '#', '#'); ?>
                      </label>
                  </div>

                  <div class="form-group checkbox-group">
                      <label>
                          <input type="checkbox" id="marketing_emails" name="marketing_emails">
                          <?php _e('I agree to receive marketing emails and updates', 'affiliate-bloom'); ?>
                      </label>
                  </div>
              </div>

              <div class="form-actions">
                  <button type="submit" id="submit-application" class="btn-primary">
                      <?php _e('Submit Application', 'affiliate-bloom'); ?>
                  </button>
              </div>

              <div id="application-result" class="application-result" style="display: none;"></div>
          </form>
      </div>

      <script>
      jQuery(document).ready(function($) {
          // Show/hide payment fields based on selection
          $('#payment_method').change(function() {
              var method = $(this).val();
              $('#paypal_email_group, #bank_details_group').hide();

              if (method === 'paypal') {
                  $('#paypal_email_group').show();
              } else if (method === 'bank_transfer') {
                  $('#bank_details_group').show();
              }
          });

          // Handle form submission
          $('#affiliate-application-form').submit(function(e) {
              e.preventDefault();

              var $form = $(this);
              var $submitBtn = $('#submit-application');
              var $result = $('#application-result');

              // Disable submit button
              $submitBtn.prop('disabled', true).text('<?php _e('Submitting...', 'affiliate-bloom'); ?>');

              $.ajax({
                  url: '<?php echo admin_url('admin-ajax.php'); ?>',
                  type: 'POST',
                  data: $form.serialize() + '&action=submit_affiliate_application',
                  success: function(response) {
                      if (response.success) {
                          $result.removeClass('error').addClass('success')
                                 .html('<p>' + response.data.message + '</p>')
                                 .show();
                          $form[0].reset();
                      } else {
                          $result.removeClass('success').addClass('error')
                                 .html('<p>' + response.data + '</p>')
                                 .show();
                      }
                  },
                  error: function() {
                      $result.removeClass('success').addClass('error')
                             .html('<p><?php _e('An error occurred. Please try again.', 'affiliate-bloom'); ?></p>')
                             .show();
                  },
                  complete: function() {
                      $submitBtn.prop('disabled', false).text('<?php _e('Submit Application', 'affiliate-bloom'); ?>');
                      $('html, body').animate({
                          scrollTop: $result.offset().top - 100
                      }, 500);
                  }
              });
          });
      });
      </script>
      <?php
      return ob_get_clean();
  }

  public function handle_application_submission() {
      // Verify nonce
      if (!wp_verify_nonce($_POST['application_nonce'], 'affiliate_application_nonce')) {
          wp_send_json_error('Invalid security token');
      }

      // Validate required fields
      $required_fields = array('first_name', 'last_name', 'email', 'website_url', 'promotion_methods');
      foreach ($required_fields as $field) {
          if (empty($_POST[$field])) {
              wp_send_json_error(sprintf(__('Field "%s" is required', 'affiliate-bloom'), $field));
          }
      }

      // Validate email
      if (!is_email($_POST['email'])) {
          wp_send_json_error(__('Please enter a valid email address', 'affiliate-bloom'));
      }

      // Check if terms are agreed
      if (empty($_POST['agree_terms'])) {
          wp_send_json_error(__('You must agree to the terms and conditions', 'affiliate-bloom'));
      }

      // Check if email already exists
      if (email_exists($_POST['email'])) {
          $user = get_user_by('email', $_POST['email']);
          $existing_status = get_user_meta($user->ID, 'affiliate_status', true);

          if ($existing_status === 'approved') {
              wp_send_json_error(__('This email is already registered as an approved affiliate', 'affiliate-bloom'));
          } elseif ($existing_status === 'pending') {
              wp_send_json_error(__('An application with this email is already pending review', 'affiliate-bloom'));
          }
      }

      // Sanitize data
      $application_data = array(
          'first_name' => sanitize_text_field($_POST['first_name']),
          'last_name' => sanitize_text_field($_POST['last_name']),
          'email' => sanitize_email($_POST['email']),
          'phone' => sanitize_text_field($_POST['phone']),
          'address' => sanitize_textarea_field($_POST['address']),
          'website_url' => esc_url_raw($_POST['website_url']),
          'monthly_visitors' => sanitize_text_field($_POST['monthly_visitors']),
          'niche' => sanitize_text_field($_POST['niche']),
          'social_media' => sanitize_textarea_field($_POST['social_media']),
          'affiliate_experience' => sanitize_text_field($_POST['affiliate_experience']),
          'other_programs' => sanitize_textarea_field($_POST['other_programs']),
          'promotion_methods' => sanitize_textarea_field($_POST['promotion_methods']),
          'payment_method' => sanitize_text_field($_POST['payment_method']),
          'paypal_email' => sanitize_email($_POST['paypal_email']),
          'bank_details' => sanitize_textarea_field($_POST['bank_details']),
          'marketing_emails' => !empty($_POST['marketing_emails']) ? 1 : 0,
          'application_date' => current_time('mysql'),
          'status' => 'pending'
      );

      // Save application
      $application_id = $this->save_application($application_data);

      if ($application_id) {
          // Create user account if doesn't exist
          $user_id = $this->create_or_update_user($application_data);

          if ($user_id) {
              // Set affiliate status to pending
              update_user_meta($user_id, 'affiliate_status', 'pending');
              update_user_meta($user_id, 'affiliate_application_id', $application_id);

              // Send notification emails
              $this->send_application_notifications($application_data, $application_id);

              wp_send_json_success(array(
                  'message' => __('Your affiliate application has been submitted successfully! We will review your application and get back to you within 2-3 business days.', 'affiliate-bloom'),
                  'application_id' => $application_id
              ));
          } else {
              wp_send_json_error(__('Failed to create user account', 'affiliate-bloom'));
          }
      } else {
          wp_send_json_error(__('Failed to save application', 'affiliate-bloom'));
      }
  }

  private function maybe_create_application_table() {
      global $wpdb;

      $table_name = $wpdb->prefix . 'affiliate_bloom_applications';
      $charset_collate = $wpdb->get_charset_collate();

      $sql = "CREATE TABLE IF NOT EXISTS $table_name (
          id int(11) NOT NULL AUTO_INCREMENT,
          first_name varchar(100) NOT NULL,
          last_name varchar(100) NOT NULL,
          email varchar(255) NOT NULL,
          phone varchar(20),
          address text,
          website_url varchar(255) NOT NULL,
          monthly_visitors varchar(50),
          niche varchar(100),
          social_media text,
          affiliate_experience varchar(50),
          other_programs text,
          promotion_methods text NOT NULL,
          payment_method varchar(50),
          paypal_email varchar(255),
          bank_details text,
          marketing_emails tinyint(1) DEFAULT 0,
          application_date datetime DEFAULT CURRENT_TIMESTAMP,
          status varchar(20) DEFAULT 'pending',
          admin_notes text,
          reviewed_by int(11),
          reviewed_date datetime,
          PRIMARY KEY (id),
          KEY email (email),
          KEY status (status)
      ) $charset_collate;";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);
  }

  private function save_application($data) {
      global $wpdb;

      $result = $wpdb->insert(
          $wpdb->prefix . 'affiliate_bloom_applications',
          $data,
          array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
      );

      return $result ? $wpdb->insert_id : false;
  }

  private function create_or_update_user($data) {
      $user = get_user_by('email', $data['email']);

      if ($user) {
          // Update existing user
          $user_id = $user->ID;
          wp_update_user(array(
              'ID' => $user_id,
              'first_name' => $data['first_name'],
              'last_name' => $data['last_name']
          ));
      } else {
          // Create new user
          $username = $this->generate_username($data['first_name'], $data['last_name'], $data['email']);
          $password = wp_generate_password();

          $user_id = wp_create_user($username, $password, $data['email']);

          if (is_wp_error($user_id)) {
              return false;
          }

          // Update user meta
          wp_update_user(array(
              'ID' => $user_id,
              'first_name' => $data['first_name'],
              'last_name' => $data['last_name'],
              'display_name' => $data['first_name'] . ' ' . $data['last_name']
          ));

          // Send password reset email
          wp_new_user_notification($user_id, null, 'user');
      }

      // Update user meta with application data
      update_user_meta($user_id, 'affiliate_website_url', $data['website_url']);
      update_user_meta($user_id, 'affiliate_phone', $data['phone']);
      update_user_meta($user_id, 'affiliate_payment_method', $data['payment_method']);
      update_user_meta($user_id, 'affiliate_paypal_email', $data['paypal_email']);

      return $user_id;
  }

  private function generate_username($first_name, $last_name, $email) {
      $username = strtolower($first_name . $last_name);
      $username = sanitize_user($username);

      if (username_exists($username)) {
          $username = strtolower($first_name . $last_name . rand(100, 999));
      }

      if (username_exists($username)) {
          $username = sanitize_user(substr($email, 0, strpos($email, '@')));
      }

      if (username_exists($username)) {
          $username .= rand(1000, 9999);
      }

      return $username;
  }

  private function send_application_notifications($data, $application_id) {
      // Send confirmation email to applicant
      $subject = __('Affiliate Application Received', 'affiliate-bloom');
      $message = sprintf(
          __('Dear %s,

Thank you for applying to become an affiliate with us!

Your application (ID: %d) has been received and is currently under review. We will get back to you within 2-3 business days.

Application Details:
- Name: %s %s
- Email: %s
- Website: %s

If you have any questions, please contact our support team.

Best regards,
The Affiliate Team', 'affiliate-bloom'),
          $data['first_name'],
          $application_id,
          $data['first_name'],
          $data['last_name'],
          $data['email'],
          $data['website_url']
      );

      wp_mail($data['email'], $subject, $message);

      // Send notification to admin
      $admin_email = get_option('admin_email');
      $admin_subject = __('New Affiliate Application', 'affiliate-bloom');
      $admin_message = sprintf(
          __('A new affiliate application has been submitted.

Application ID: %d
Name: %s %s
Email: %s
Website: %s

Please review the application in the admin panel.', 'affiliate-bloom'),
          $application_id,
          $data['first_name'],
          $data['last_name'],
          $data['email'],
          $data['website_url']
      );

      wp_mail($admin_email, $admin_subject, $admin_message);
  }
}