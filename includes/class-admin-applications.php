<?php
namespace AffiliateBloom;

if (!defined('ABSPATH')) {
  exit;
}

class AdminApplications {

  public static function init() {
      $instance = new self();
      return $instance;
  }

  public function __construct() {
      add_action('admin_menu', array($this, 'add_admin_menu'));
      add_action('wp_ajax_approve_affiliate_application', array($this, 'approve_application'));
      add_action('wp_ajax_reject_affiliate_application', array($this, 'reject_application'));
      add_action('wp_ajax_get_application_details', array($this, 'get_application_details'));
  }

  public function add_admin_menu() {
      add_menu_page(
          __('Affiliate Bloom', 'affiliate-bloom'),
          __('Affiliate Bloom', 'affiliate-bloom'),
          'manage_options',
          'affiliate-bloom',
          array($this, 'admin_dashboard_page'),
          'dashicons-groups',
          30
      );

      add_submenu_page(
          'affiliate-bloom',
          __('Applications', 'affiliate-bloom'),
          __('Applications', 'affiliate-bloom'),
          'manage_options',
          'affiliate-bloom-applications',
          array($this, 'applications_page')
      );

      add_submenu_page(
          'affiliate-bloom',
          __('Affiliates', 'affiliate-bloom'),
          __('Affiliates', 'affiliate-bloom'),
          'manage_options',
          'affiliate-bloom-affiliates',
          array($this, 'affiliates_page')
      );
  }

  public function admin_dashboard_page() {
      $stats = $this->get_dashboard_stats();
      ?>
      <div class="wrap">
          <h1><?php _e('Affiliate Bloom Dashboard', 'affiliate-bloom'); ?></h1>

          <div class="affiliate-bloom-admin-stats">
              <div class="stat-box">
                  <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                  <div class="stat-label"><?php _e('Pending Applications', 'affiliate-bloom'); ?></div>
              </div>
              <div class="stat-box">
                  <div class="stat-number"><?php echo $stats['approved_affiliates']; ?></div>
                  <div class="stat-label"><?php _e('Approved Affiliates', 'affiliate-bloom'); ?></div>
              </div>
              <div class="stat-box">
                  <div class="stat-number"><?php echo $stats['total_clicks']; ?></div>
                  <div class="stat-label"><?php _e('Total Clicks', 'affiliate-bloom'); ?></div>
              </div>
              <div class="stat-box">
                  <div class="stat-number">$<?php echo number_format($stats['total_commissions'], 2); ?></div>
                  <div class="stat-label"><?php _e('Total Commissions', 'affiliate-bloom'); ?></div>
              </div>
          </div>

          <div class="affiliate-bloom-admin-actions">
              <h2><?php _e('Quick Actions', 'affiliate-bloom'); ?></h2>
              <a href="<?php echo admin_url('admin.php?page=affiliate-bloom-applications'); ?>" class="button button-primary">
                  <?php _e('Review Applications', 'affiliate-bloom'); ?>
              </a>
              <a href="<?php echo admin_url('admin.php?page=affiliate-bloom-affiliates'); ?>" class="button">
                  <?php _e('Manage Affiliates', 'affiliate-bloom'); ?>
              </a>
          </div>
      </div>
      <?php
  }

  public function applications_page() {
      $applications = $this->get_applications();
      ?>
      <div class="wrap">
          <h1><?php _e('Affiliate Applications', 'affiliate-bloom'); ?></h1>

          <div class="tablenav top">
              <div class="alignleft actions">
                  <select id="bulk-action-selector">
                      <option value=""><?php _e('Bulk Actions', 'affiliate-bloom'); ?></option>
                      <option value="approve"><?php _e('Approve', 'affiliate-bloom'); ?></option>
                      <option value="reject"><?php _e('Reject', 'affiliate-bloom'); ?></option>
                  </select>
                  <button class="button" id="bulk-apply"><?php _e('Apply', 'affiliate-bloom'); ?></button>
              </div>

              <div class="alignright">
                  <select id="status-filter">
                      <option value=""><?php _e('All Statuses', 'affiliate-bloom'); ?></option>
                      <option value="pending"><?php _e('Pending', 'affiliate-bloom'); ?></option>
                      <option value="approved"><?php _e('Approved', 'affiliate-bloom'); ?></option>
                      <option value="rejected"><?php _e('Rejected', 'affiliate-bloom'); ?></option>
                  </select>
                  <button class="button" id="filter-applications"><?php _e('Filter', 'affiliate-bloom'); ?></button>
              </div>
          </div>

          <table class="wp-list-table widefat fixed striped">
              <thead>
                  <tr>
                      <td class="manage-column column-cb check-column">
                          <input type="checkbox" id="select-all">
                      </td>
                      <th><?php _e('Name', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Email', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Website', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Experience', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Status', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Date', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Actions', 'affiliate-bloom'); ?></th>
                  </tr>
              </thead>
              <tbody id="applications-table-body">
                  <?php if (empty($applications)): ?>
                      <tr>
                          <td colspan="8" class="no-items"><?php _e('No applications found.', 'affiliate-bloom'); ?></td>
                      </tr>
                  <?php else: ?>
                      <?php foreach ($applications as $app): ?>
                          <tr data-application-id="<?php echo $app->id; ?>">
                              <th class="check-column">
                                  <input type="checkbox" class="application-checkbox" value="<?php echo $app->id; ?>">
                              </th>
                              <td>
                                  <strong><?php echo esc_html($app->first_name . ' ' . $app->last_name); ?></strong>
                              </td>
                              <td><?php echo esc_html($app->email); ?></td>
                              <td>
                                  <a href="<?php echo esc_url($app->website_url); ?>" target="_blank">
                                      <?php echo esc_html($app->website_url); ?>
                                  </a>
                              </td>
                              <td><?php echo esc_html(ucfirst($app->affiliate_experience)); ?></td>
                              <td>
                                  <span class="status-badge status-<?php echo $app->status; ?>">
                                      <?php echo esc_html(ucfirst($app->status)); ?>
                                  </span>
                              </td>
                              <td><?php echo date('M j, Y', strtotime($app->application_date)); ?></td>
                              <td>
                                  <button class="button button-small view-application" data-id="<?php echo $app->id; ?>">
                                      <?php _e('View', 'affiliate-bloom'); ?>
                                  </button>
                                  <?php if ($app->status === 'pending'): ?>
                                      <button class="button button-primary button-small approve-application" data-id="<?php echo $app->id; ?>">
                                          <?php _e('Approve', 'affiliate-bloom'); ?>
                                      </button>
                                      <button class="button button-small reject-application" data-id="<?php echo $app->id; ?>">
                                          <?php _e('Reject', 'affiliate-bloom'); ?>
                                      </button>
                                  <?php endif; ?>
                              </td>
                          </tr>
                      <?php endforeach; ?>
                  <?php endif; ?>
              </tbody>
          </table>
      </div>

      <!-- Application Details Modal -->
      <div id="application-modal" class="affiliate-bloom-modal" style="display: none;">
          <div class="modal-content">
              <div class="modal-header">
                  <h2><?php _e('Application Details', 'affiliate-bloom'); ?></h2>
                  <span class="close-modal">&times;</span>
              </div>
              <div class="modal-body" id="application-details">
                  <!-- Content loaded via AJAX -->
              </div>
              <div class="modal-footer">
                  <button class="button" id="close-modal"><?php _e('Close', 'affiliate-bloom'); ?></button>
                  <div id="modal-actions">
                      <!-- Action buttons loaded via AJAX -->
                  </div>
              </div>
          </div>
      </div>

      <script>
      jQuery(document).ready(function($) {
          // View application details
          $('.view-application').click(function() {
              var appId = $(this).data('id');
              loadApplicationDetails(appId);
          });

          // Approve application
          $(document).on('click', '.approve-application', function() {
              var appId = $(this).data('id');
              if (confirm('<?php _e('Are you sure you want to approve this application?', 'affiliate-bloom'); ?>')) {
                  processApplication(appId, 'approve');
              }
          });

          // Reject application
          $(document).on('click', '.reject-application', function() {
              var appId = $(this).data('id');
              var reason = prompt('<?php _e('Please provide a reason for rejection (optional):', 'affiliate-bloom'); ?>');
              if (reason !== null) {
                  processApplication(appId, 'reject', reason);
              }
          });

          // Modal functionality
          $('.close-modal, #close-modal').click(function() {
              $('#application-modal').hide();
          });

          // Select all checkbox
          $('#select-all').change(function() {
              $('.application-checkbox').prop('checked', this.checked);
          });

          function loadApplicationDetails(appId) {
              $.post(ajaxurl, {
                  action: 'get_application_details',
                  application_id: appId,
                  nonce: '<?php echo wp_create_nonce('affiliate_bloom_admin_nonce'); ?>'
              }, function(response) {
                  if (response.success) {
                      $('#application-details').html(response.data.html);
                      $('#modal-actions').html(response.data.actions);
                      $('#application-modal').show();
                  } else {
                      alert('<?php _e('Error loading application details', 'affiliate-bloom'); ?>');
                  }
              });
          }

          function processApplication(appId, action, reason) {
              var actionName = action === 'approve' ? 'approve_affiliate_application' : 'reject_affiliate_application';
              var data = {
                  action: actionName,
                  application_id: appId,
                  nonce: '<?php echo wp_create_nonce('affiliate_bloom_admin_nonce'); ?>'
              };

              if (reason) {
                  data.reason = reason;
              }

              $.post(ajaxurl, data, function(response) {
                  if (response.success) {
                      location.reload();
                  } else {
                      alert(response.data);
                  }
              });
          }
      });
      </script>
      <?php
  }

  public function affiliates_page() {
      $affiliates = $this->get_approved_affiliates();
      ?>
      <div class="wrap">
          <h1><?php _e('Approved Affiliates', 'affiliate-bloom'); ?></h1>

          <table class="wp-list-table widefat fixed striped">
              <thead>
                  <tr>
                      <th><?php _e('Name', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Email', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Affiliate Code', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Total Clicks', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Conversions', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Earnings', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Status', 'affiliate-bloom'); ?></th>
                      <th><?php _e('Actions', 'affiliate-bloom'); ?></th>
                  </tr>
              </thead>
              <tbody>
                  <?php if (empty($affiliates)): ?>
                      <tr>
                          <td colspan="8" class="no-items"><?php _e('No approved affiliates found.', 'affiliate-bloom'); ?></td>
                      </tr>
                  <?php else: ?>
                      <?php foreach ($affiliates as $affiliate): ?>
                          <tr>
                              <td>
                                  <strong><?php echo esc_html($affiliate->display_name); ?></strong>
                              </td>
                              <td><?php echo esc_html($affiliate->user_email); ?></td>
                              <td>
                                  <code><?php echo esc_html($affiliate->affiliate_code); ?></code>
                              </td>
                              <td><?php echo intval($affiliate->total_clicks); ?></td>
                              <td><?php echo intval($affiliate->total_conversions); ?></td>
                              <td>$<?php echo number_format($affiliate->total_earnings, 2); ?></td>
                              <td>
                                  <span class="status-badge status-<?php echo $affiliate->affiliate_status; ?>">
                                      <?php echo esc_html(ucfirst($affiliate->affiliate_status)); ?>
                                  </span>
                              </td>
                              <td>
                                  <a href="<?php echo admin_url('user-edit.php?user_id=' . $affiliate->ID); ?>" class="button button-small">
                                      <?php _e('Edit', 'affiliate-bloom'); ?>
                                  </a>
                              </td>
                          </tr>
                      <?php endforeach; ?>
                  <?php endif; ?>
              </tbody>
          </table>
      </div>
      <?php
  }

  public function approve_application() {
      if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_admin_nonce')) {
          wp_send_json_error('Invalid nonce');
      }

      if (!current_user_can('manage_options')) {
          wp_send_json_error('Insufficient permissions');
      }

      $application_id = intval($_POST['application_id']);
      $application = $this->get_application_by_id($application_id);

      if (!$application) {
          wp_send_json_error('Application not found');
      }

      // Update application status
      global $wpdb;
      $updated = $wpdb->update(
          $wpdb->prefix . 'affiliate_bloom_applications',
          array(
              'status' => 'approved',
              'reviewed_by' => get_current_user_id(),
              'reviewed_date' => current_time('mysql')
          ),
          array('id' => $application_id),
          array('%s', '%d', '%s'),
          array('%d')
      );

      if ($updated) {
          // Update user status
          $user = get_user_by('email', $application->email);
          if ($user) {
              update_user_meta($user->ID, 'affiliate_status', 'approved');

              // Generate affiliate code if not exists
              $affiliate_code = get_user_meta($user->ID, 'affiliate_code', true);
              if (empty($affiliate_code)) {
                  $affiliate_code = 'AFF' . $user->ID . '_' . wp_generate_password(6, false);
                  update_user_meta($user->ID, 'affiliate_code', $affiliate_code);
              }

              // Send approval email
              $this->send_approval_email($application, $affiliate_code);

              wp_send_json_success('Application approved successfully');
          } else {
              wp_send_json_error('User not found');
          }
      } else {
          wp_send_json_error('Failed to update application');
      }
  }

  public function reject_application() {
      if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_admin_nonce')) {
          wp_send_json_error('Invalid nonce');
      }

      if (!current_user_can('manage_options')) {
          wp_send_json_error('Insufficient permissions');
      }

      $application_id = intval($_POST['application_id']);
      $reason = sanitize_textarea_field($_POST['reason'] ?? '');
      $application = $this->get_application_by_id($application_id);

      if (!$application) {
          wp_send_json_error('Application not found');
      }

      // Update application status
      global $wpdb;
      $updated = $wpdb->update(
          $wpdb->prefix . 'affiliate_bloom_applications',
          array(
              'status' => 'rejected',
              'admin_notes' => $reason,
              'reviewed_by' => get_current_user_id(),
              'reviewed_date' => current_time('mysql')
          ),
          array('id' => $application_id),
          array('%s', '%s', '%d', '%s'),
          array('%d')
      );

      if ($updated) {
          // Update user status
          $user = get_user_by('email', $application->email);
          if ($user) {
              update_user_meta($user->ID, 'affiliate_status', 'rejected');
          }

          // Send rejection email
          $this->send_rejection_email($application, $reason);

          wp_send_json_success('Application rejected');
      } else {
          wp_send_json_error('Failed to update application');
      }
  }

  public function get_application_details() {
      if (!wp_verify_nonce($_POST['nonce'], 'affiliate_bloom_admin_nonce')) {
          wp_send_json_error('Invalid nonce');
      }

      $application_id = intval($_POST['application_id']);
      $application = $this->get_application_by_id($application_id);

      if (!$application) {
          wp_send_json_error('Application not found');
      }

      ob_start();
      ?>
      <div class="application-details">
          <div class="detail-section">
              <h3><?php _e('Personal Information', 'affiliate-bloom'); ?></h3>
              <p><strong><?php _e('Name:', 'affiliate-bloom'); ?></strong> <?php echo esc_html($application->first_name . ' ' . $application->last_name); ?></p>
              <p><strong><?php _e('Email:', 'affiliate-bloom'); ?></strong> <?php echo esc_html($application->email); ?></p>
              <p><strong><?php _e('Phone:', 'affiliate-bloom'); ?></strong> <?php echo esc_html($application->phone); ?></p>
              <p><strong><?php _e('Address:', 'affiliate-bloom'); ?></strong> <?php echo nl2br(esc_html($application->address)); ?></p>
          </div>

          <div class="detail-section">
              <h3><?php _e('Business Information', 'affiliate-bloom'); ?></h3>
              <p><strong><?php _e('Website:', 'affiliate-bloom'); ?></strong> <a href="<?php echo esc_url($application->website_url); ?>" target="_blank"><?php echo esc_html($application->website_url); ?></a></p>
              <p><strong><?php _e('Monthly Visitors:', 'affiliate-bloom'); ?></strong> <?php echo esc_html($application->monthly_visitors); ?></p>
              <p><strong><?php _e('Niche:', 'affiliate-bloom'); ?></strong> <?php echo esc_html($application->niche); ?></p>
              <p><strong><?php _e('Social Media:', 'affiliate-bloom'); ?></strong> <?php echo nl2br(esc_html($application->social_media)); ?></p>
          </div>

          <div class="detail-section">
              <h3><?php _e('Marketing Experience', 'affiliate-bloom'); ?></h3>
              <p><strong><?php _e('Experience Level:', 'affiliate-bloom'); ?></strong> <?php echo esc_html(ucfirst($application->affiliate_experience)); ?></p>
              <p><strong><?php _e('Other Programs:', 'affiliate-bloom'); ?></strong> <?php echo nl2br(esc_html($application->other_programs)); ?></p>
              <p><strong><?php _e('Promotion Methods:', 'affiliate-bloom'); ?></strong> <?php echo nl2br(esc_html($application->promotion_methods)); ?></p>
          </div>

          <div class="detail-section">
              <h3><?php _e('Payment Information', 'affiliate-bloom'); ?></h3>
              <p><strong><?php _e('Payment Method:', 'affiliate-bloom'); ?></strong> <?php echo esc_html(ucfirst($application->payment_method)); ?></p>
              <?php if ($application->paypal_email): ?>
                  <p><strong><?php _e('PayPal Email:', 'affiliate-bloom'); ?></strong> <?php echo esc_html($application->paypal_email); ?></p>
              <?php endif; ?>
              <?php if ($application->bank_details): ?>
                  <p><strong><?php _e('Bank Details:', 'affiliate-bloom'); ?></strong> <?php echo nl2br(esc_html($application->bank_details)); ?></p>
              <?php endif; ?>
          </div>
      </div>
      <?php
      $html = ob_get_clean();

      $actions = '';
      if ($application->status === 'pending') {
          $actions = '<button class="button button-primary approve-application" data-id="' . $application->id . '">' . __('Approve', 'affiliate-bloom') . '</button> ';
          $actions .= '<button class="button reject-application" data-id="' . $application->id . '">' . __('Reject', 'affiliate-bloom') . '</button>';
      }

      wp_send_json_success(array(
          'html' => $html,
          'actions' => $actions
      ));
  }

  // Helper methods
  private function get_dashboard_stats() {
      global $wpdb;

      $stats = array();

      // Pending applications
      $stats['pending_applications'] = $wpdb->get_var(
          "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_applications WHERE status = 'pending'"
      ) ?: 0;

      // Approved affiliates
      $stats['approved_affiliates'] = $wpdb->get_var(
          $wpdb->prepare(
              "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'affiliate_status' AND meta_value = %s",
              'approved'
          )
      ) ?: 0;

      // Total clicks
      $stats['total_clicks'] = $wpdb->get_var(
          "SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_bloom_clicks"
      ) ?: 0;

      // Total commissions
      $stats['total_commissions'] = $wpdb->get_var(
          "SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_bloom_conversions"
      ) ?: 0;

      return $stats;
  }

  private function get_applications($status = '') {
      global $wpdb;

      $where = '';
      if ($status) {
          $where = $wpdb->prepare(" WHERE status = %s", $status);
      }

      return $wpdb->get_results(
          "SELECT * FROM {$wpdb->prefix}affiliate_bloom_applications{$where} ORDER BY application_date DESC"
      );
  }

  private function get_application_by_id($id) {
      global $wpdb;

      return $wpdb->get_row($wpdb->prepare(
          "SELECT * FROM {$wpdb->prefix}affiliate_bloom_applications WHERE id = %d",
          $id
      ));
  }

  private function get_approved_affiliates() {
      global $wpdb;

      return $wpdb->get_results(
          "SELECT u.*,
                  um1.meta_value as affiliate_status,
                  um2.meta_value as affiliate_code,
                  COALESCE(clicks.total_clicks, 0) as total_clicks,
                  COALESCE(conversions.total_conversions, 0) as total_conversions,
                  COALESCE(conversions.total_earnings, 0) as total_earnings
           FROM {$wpdb->users} u
           INNER JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'affiliate_status' AND um1.meta_value = 'approved'
           LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'affiliate_code'
           LEFT JOIN (
               SELECT user_id, COUNT(*) as total_clicks
               FROM {$wpdb->prefix}affiliate_bloom_clicks
               GROUP BY user_id
           ) clicks ON u.ID = clicks.user_id
           LEFT JOIN (
               SELECT user_id, COUNT(*) as total_conversions, SUM(commission_amount) as total_earnings
               FROM {$wpdb->prefix}affiliate_bloom_conversions
               GROUP BY user_id
           ) conversions ON u.ID = conversions.user_id
           ORDER BY u.display_name"
      );
  }

  private function send_approval_email($application, $affiliate_code) {
      $subject = __('Affiliate Application Approved!', 'affiliate-bloom');
      $message = sprintf(
          __('Dear %s,

Congratulations! Your affiliate application has been approved.

Your affiliate details:
- Affiliate Code: %s
- Dashboard: %s

You can now start promoting our products and earning commissions!

Best regards,
The Affiliate Team', 'affiliate-bloom'),
          $application->first_name,
          $affiliate_code,
          home_url('/affiliate-dashboard/')
      );

      wp_mail($application->email, $subject, $message);
  }

  private function send_rejection_email($application, $reason) {
      $subject = __('Affiliate Application Update', 'affiliate-bloom');
      $message = sprintf(
          __('Dear %s,

Thank you for your interest in our affiliate program.

Unfortunately, we are unable to approve your application at this time.

%s

If you have any questions, please contact our support team.

Best regards,
The Affiliate Team', 'affiliate-bloom'),
          $application->first_name,
          $reason ? "\nReason: " . $reason : ''
      );

      wp_mail($application->email, $subject, $message);
  }
}