<?php

/**
 * @file
 * Contains \Drupal\piwik\Form\PiwikSettings.
 */

namespace Drupal\piwik\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the configuration for the piwik module.
 */
class PiwikSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'piwik_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('piwik.settings');

    $form['account'] = array(
      '#type' => 'fieldset',
      '#title' => t('General settings'),
    );

    $form['account']['site_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Piwik site ID'),
      '#default_value' => $config->get('site_id'),
      '#size' => 15,
      '#maxlength' => 20,
      '#required' => TRUE,
      '#description' => t('The user account number is unique to the websites domain. Click the <strong>Settings</strong> link in your Piwik account, then the <strong>Websites</strong> tab and enter the appropriate site <strong>ID</strong> into this field.'),
    );
    $form['account']['url_http'] = array(
      '#type' => 'textfield',
      '#title' => t('Piwik HTTP URL'),
      '#default_value' => $config->get('url_http'),
      '#size' => 80,
      '#maxlength' => 255,
      '#required' => TRUE,
      '#description' => t('The URL to your Piwik base directory. Example: "http://www.example.com/piwik/".'),
    );
    $form['account']['url_https'] = array(
      '#type' => 'textfield',
      '#title' => t('Piwik HTTPS URL'),
      '#default_value' => $config->get('url_https'),
      '#size' => 80,
      '#maxlength' => 255,
      '#description' => t('The URL to your Piwik base directory with SSL certificate installed. Required if you track a SSL enabled website. Example: "https://www.example.com/piwik/".'),
    );

    // Visibility settings.
    $form['tracking_title'] = array(
      '#type' => 'item',
      '#title' => t('Tracking scope'),
    );
    $form['tracking'] = array(
      '#type' => 'vertical_tabs',
      '#attached' => array(
        'js' => array(drupal_get_path('module', 'piwik') . '/piwik.admin.js'),
      ),
    );

    $form['tracking']['domain_tracking'] = array(
      '#type' => 'fieldset',
      '#title' => t('Domains'),
    );

    global $cookie_domain;
    $multiple_sub_domains = array();
    foreach (array('www', 'app', 'shop') as $subdomain) {
      if (count(explode('.', $cookie_domain)) > 2 && !is_numeric(str_replace('.', '', $cookie_domain))) {
        $multiple_sub_domains[] = $subdomain . $cookie_domain;
      }
      // IP addresses or localhost.
      else {
        $multiple_sub_domains[] = $subdomain . '.example.com';
      }
    }

    $form['tracking']['domain_tracking']['domain_mode'] = array(
      '#type' => 'radios',
      '#title' => t('What are you tracking?'),
      '#options' => array(
        0 => t('A single domain (default)') . '<div class="description">' . t('Domain: @domain', array('@domain' => $_SERVER['HTTP_HOST'])) . '</div>',
        1 => t('One domain with multiple subdomains') . '<div class="description">' . t('Examples: @domains', array('@domains' => implode(', ', $multiple_sub_domains))) . '</div>',
      ),
      '#default_value' => $config->get('domain_mode', 0),
    );

    // Page specific visibility configurations.
    $php_access = $this->currentUser()->hasPermission('use PHP for tracking visibility');
    $visibility = $config->get('visibility_pages', 0);
    $pages = $config->get('pages', pages);

    $form['tracking']['page_vis_settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('Pages'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    if ($visibility == 2 && !$php_access) {
      $form['tracking']['page_vis_settings'] = array();
      $form['tracking']['page_vis_settings']['visibility'] = array('#type' => 'value', '#value' => 2);
      $form['tracking']['page_vis_settings']['pages'] = array('#type' => 'value', '#value' => $pages);
    }
    else {
      $options = array(
        t('Every page except the listed pages'),
        t('The listed pages only')
      );
      $description = t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are %blog for the blog page and %blog-wildcard for every personal blog. %front is the front page.", array('%blog' => 'blog', '%blog-wildcard' => 'blog/*', '%front' => '<front>'));

      // @TODO Replace that with condition plugins or something similar.
      if (\Drupal::moduleHandler()->moduleExists('php') && $php_access) {
        $options[] = t('Pages on which this PHP code returns <code>TRUE</code> (experts only)');
        $title = t('Pages or PHP code');
        $description .= ' ' . t('If the PHP option is chosen, enter PHP code between %php. Note that executing incorrect PHP code can break your Drupal site.', array('%php' => '<?php ?>'));
      }
      else {
        $title = t('Pages');
      }
      $form['tracking']['page_vis_settings']['visibility_pages'] = array(
        '#type' => 'radios',
        '#title' => t('Add tracking to specific pages'),
        '#options' => $options,
        '#default_value' => $visibility,
      );
      $form['tracking']['page_vis_settings']['pages'] = array(
        '#type' => 'textarea',
        '#title' => $title,
        '#title_display' => 'invisible',
        '#default_value' => $pages,
        '#description' => $description,
        '#rows' => 10,
      );
    }

    // Render the role overview.
    $form['tracking']['role_vis_settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('Roles'),
    );

    $form['tracking']['role_vis_settings']['visibility_roles'] = array(
      '#type' => 'radios',
      '#title' => t('Add tracking for specific roles'),
      '#options' => array(
        t('Add to the selected roles only'),
        t('Add to every role except the selected ones'),
      ),
      '#default_value' => $config->get('visibility_roles', 0),
    );

    $role_options = array_map('check_plain', user_roles());
    $form['tracking']['role_vis_settings']['roles'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Roles'),
      '#default_value' => $config->get('roles', array()),
      '#options' => $role_options,
      '#description' => t('If none of the roles are selected, all users will be tracked. If a user has any of the roles checked, that user will be tracked (or excluded, depending on the setting above).'),
    );

    // Standard tracking configurations.
    $form['tracking']['user_vis_settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('Users'),
    );
    $t_permission = array('%permission' => t('opt-in or out of tracking'));
    $form['tracking']['user_vis_settings']['custom'] = array(
      '#type' => 'radios',
      '#title' => t('Allow users to customize tracking on their account page'),
      '#options' => array(
        t('No customization allowed'),
        t('Tracking on by default, users with %permission permission can opt out', $t_permission),
        t('Tracking off by default, users with %permission permission can opt in', $t_permission)
      ),
      '#default_value' => $config->get('custom', 0),
    );

    // Link specific configurations.
    $form['tracking']['linktracking'] = array(
      '#type' => 'fieldset',
      '#title' => t('Links and downloads'),
    );
    $form['tracking']['linktracking']['trackmailto'] = array(
      '#type' => 'checkbox',
      '#title' => t('Track clicks on mailto links'),
      '#default_value' => $config->get('trackmailto', 1),
    );
    $form['tracking']['linktracking']['track'] = array(
      '#type' => 'checkbox',
      '#title' => t('Track clicks on outbound links and downloads (clicks on file links) for the following extensions'),
      '#default_value' => $config->get('track', 1),
    );
    $form['tracking']['linktracking']['trackfiles_extensions'] = array(
      '#title' => t('List of download file extensions'),
      '#title_display' => 'invisible',
      '#type' => 'textfield',
      '#default_value' => $config->get('trackfiles_extensions', PIWIK_TRACKFILES_EXTENSIONS),
      '#description' => t('A file extension list separated by the | character that will be tracked when clicked. Regular expressions are supported. For example: !extensions', array('!extensions' => trackFILES_EXTENSIONS)),
      '#maxlength' => 255,
      '#states' => array(
        'enabled' => array(
          ':input[name="track"]' => array('checked' => TRUE),
        ),
        # Note: Form required marker is not visible as title is invisible.
        'required' => array(
          ':input[name="track"]' => array('checked' => TRUE),
        ),
      ),
    );

    // Message specific configurations.
    $form['tracking']['messagetracking'] = array(
      '#type' => 'fieldset',
      '#title' => t('Messages'),
    );
    $form['tracking']['messagetracking']['trackmessages'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Track messages of type'),
      '#default_value' => $config->get('trackmessages', array()),
      '#description' => t('This will track the selected message types shown to users. Tracking of form validation errors may help you identifying usability issues in your site. Every message is tracked as one individual event. Messages from excluded pages cannot tracked.'),
      '#options' => array(
        'status' => t('Status message'),
        'warning' => t('Warning message'),
        'error' => t('Error message'),
      ),
    );

    $form['tracking']['search'] = array(
      '#type' => 'fieldset',
      '#title' => t('Search'),
    );

    $site_search_dependencies = '<div class="admin-requirements">';
    $site_search_dependencies .= t('Requires: !module-list', array('!module-list' => (\Drupal::moduleHandler()->moduleExists('search') ? t('@module (<span class="admin-enabled">enabled</span>)', array('@module' => 'Search')) : t('@module (<span class="admin-disabled">disabled</span>)', array('@module' => 'Search')))));
    $site_search_dependencies .= '</div>';

    $form['tracking']['search']['site_search'] = array(
      '#type' => 'checkbox',
      '#title' => t('Track internal search'),
      '#description' => t('If checked, internal search keywords are tracked.') . $site_search_dependencies,
      '#default_value' => $config->get('site_search', FALSE),
      '#disabled' => (\Drupal::moduleHandler()->moduleExists('search') ? FALSE : TRUE),
    );

    // Privacy specific configurations.
    $form['tracking']['privacy'] = array(
      '#type' => 'fieldset',
      '#title' => t('Privacy'),
    );
    $form['tracking']['privacy']['privacy_donottrack'] = array(
      '#type' => 'checkbox',
      '#title' => t('Universal web tracking opt-out'),
      '#description' => t('If enabled and your Piwik server receives the <a href="http://donottrack.us/">Do-Not-Track</a> header from the client browser, the Piwik server will not track the user. Compliance with Do Not Track could be purely voluntary, enforced by industry self-regulation, or mandated by state or federal law. Please accept your visitors privacy. If they have opt-out from tracking and advertising, you should accept their personal decision.'),
      '#default_value' => $config->get('privacy_donottrack', 1),
    );

    // Piwik page title tree view settings.
    $form['page_title_hierarchy'] = array(
      '#type' => 'fieldset',
      '#title' => t('Page titles hierarchy'),
      '#description' => t('This functionality enables a dynamically expandable tree view of your site page titles in your Piwik statistics. See in Piwik statistics under <em>Actions</em> > <em>Page titles</em>.'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
    $form['page_title_hierarchy']['page_title_hierarchy'] = array(
      '#type' => 'checkbox',
      '#title' => t("Show page titles as hierarchy like breadcrumbs"),
      '#description' => t('By default Piwik tracks the current page title and shows you a flat list of the most popular titles. This enables a breadcrumbs like tree view.'),
      '#default_value' => $config->get('page_title_hierarchy', FALSE),
    );
    $form['page_title_hierarchy']['page_title_hierarchy_exclude_home'] = array(
      '#type' => 'checkbox',
      '#title' => t('Hide home page from hierarchy'),
      '#description' => t('If enabled, the "Home" item will be removed from the hierarchy to flatten the structure in the Piwik statistics. Hits to the home page will still be counted, but for other pages the hierarchy will start at level Home+1.'),
      '#default_value' => $config->get('page_title_hierarchy_exclude_home', TRUE),
    );

    $form['custom_var'] = array(
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('You can add Piwiks <a href="!custom_var_documentation">Custom Variables</a> here. These will be added to every page that Piwik tracking code appears on. Custom variable names and values are limited to 200 characters in length. Keep the names and values as short as possible and expect long values to get trimmed. You may use tokens in custom variable names and values. Global and user tokens are always available; on node pages, node tokens are also available.', array('!custom_var_documentation' => 'http://piwik.org/docs/custom-variables/')),
      '#theme' => 'piwik_admin_custom_var_table',
      '#title' => t('Custom variables'),
      '#tree' => TRUE,
      '#type' => 'fieldset',
    );

    $custom_vars = $config->get('custom_var', array());

    // Piwik supports up to 5 custom variables.
    for ($i = 1; $i < 6; $i++) {
      $form['custom_var']['slots'][$i]['slot'] = array(
        '#default_value' => $i,
        '#description' => t('Slot number'),
        '#disabled' => TRUE,
        '#size' => 1,
        '#title' => t('Custom variable slot #@slot', array('@slot' => $i)),
        '#title_display' => 'invisible',
        '#type' => 'textfield',
      );
      $form['custom_var']['slots'][$i]['name'] = array(
        '#default_value' => !empty($custom_vars['slots'][$i]['name']) ? $custom_vars['slots'][$i]['name'] : '',
        '#description' => t('The custom variable name.'),
        '#maxlength' => 100,
        '#size' => 20,
        '#title' => t('Custom variable name #@slot', array('@slot' => $i)),
        '#title_display' => 'invisible',
        '#type' => 'textfield',
      );
      $form['custom_var']['slots'][$i]['value'] = array(
        '#default_value' => !empty($custom_vars['slots'][$i]['value']) ? $custom_vars['slots'][$i]['value'] : '',
        '#description' => t('The custom variable value.'),
        '#maxlength' => 255,
        '#title' => t('Custom variable value #@slot', array('@slot' => $i)),
        '#title_display' => 'invisible',
        '#type' => 'textfield',
        '#element_validate' => array('piwik_token_element_validate'),
        '#token_types' => array('node'),
      );
      if (\Drupal::moduleHandler()->moduleExists('token')) {
        $form['custom_var']['slots'][$i]['value']['#element_validate'][] = 'token_element_validate';
      }
      $form['custom_var']['slots'][$i]['scope'] = array(
        '#default_value' => !empty($custom_vars['slots'][$i]['scope']) ? $custom_vars['slots'][$i]['scope'] : 'visit',
        '#description' => t('The scope for the custom variable.'),
        '#title' => t('Custom variable slot #@slot', array('@slot' => $i)),
        '#title_display' => 'invisible',
        '#type' => 'select',
        '#options' => array(
          'visit' => t('Visit'),
          'page' => t('Page'),
        ),
      );
    }

    $form['custom_var']['custom_var_description'] = array(
      '#type' => 'item',
      '#description' => t('You can supplement Piwiks\' basic IP address tracking of visitors by segmenting users based on custom variables. Make sure you will not associate (or permit any third party to associate) any data gathered from your websites (or such third parties\' websites) with any personally identifying information from any source as part of your use (or such third parties\' use) of the Piwik\' service.'),
    );
    $form['custom_var']['custom_var_token_tree'] = array(
      '#theme' => 'token_tree',
      '#token_types' => array('node'),
      '#dialog' => TRUE,
    );

    // Advanced feature configurations.
    $form['advanced'] = array(
      '#type' => 'fieldset',
      '#title' => t('Advanced settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['advanced']['cache'] = array(
      '#type' => 'checkbox',
      '#title' => t('Locally cache tracking code file'),
      '#description' => t('If checked, the tracking code file is retrieved from your Piwik site and cached locally. It is updated daily to ensure updates to tracking code are reflected in the local copy.'),
      '#default_value' => $config->get('cache', 0),
    );

    // Allow for tracking of the originating node when viewing translation sets.
    if (\Drupal::moduleHandler()->moduleExists('translation')) {
      $form['advanced']['translation_set'] = array(
        '#type' => 'checkbox',
        '#title' => t('Track translation sets as one unit'),
        '#description' => t('When a node is part of a translation set, record statistics for the originating node instead. This allows for a translation set to be treated as a single unit.'),
        '#default_value' => $config->get('translation_set', 0),
      );
    }

    // Code snippet settings.
    $form['advanced']['codesnippet'] = array(
      '#type' => 'fieldset',
      '#title' => t('Custom JavaScript code'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('You can add custom Piwik <a href="@snippets">code snippets</a> here. These will be added to every page that Piwik appears on. <strong>Do not include the &lt;script&gt; tags</strong>, and always end your code with a semicolon (;).', array('@snippets' => 'http://piwik.org/docs/javascript-tracking/'))
    );
    $form['advanced']['codesnippet']['codesnippet_before'] = array(
      '#type' => 'textarea',
      '#title' => t('Code snippet (before)'),
      '#default_value' => $config->get('codesnippet_before', ''),
      '#rows' => 5,
      '#description' => t('Code in this textarea will be added <strong>before</strong> _paq.push(["trackPageView"]).')
    );
    $form['advanced']['codesnippet']['codesnippet_after'] = array(
      '#type' => 'textarea',
      '#title' => t('Code snippet (after)'),
      '#default_value' => $config->get('codesnippet_after', ''),
      '#rows' => 5,
      '#description' => t('Code in this textarea will be added <strong>after</strong> _paq.push(["trackPageView"]). This is useful if you\'d like to track a site in two accounts.')
    );

    $form['advanced']['js_scope'] = array(
      '#type' => 'select',
      '#title' => t('JavaScript scope'),
      '#description' => t("Piwik recommends adding the tracking code to the header for performance reasons."),
      '#options' => array(
        'footer' => t('Footer'),
        'header' => t('Header'),
      ),
      '#default_value' => $config->get('js_scope', 'header'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Custom variables validation.
    foreach ($form_state['values']['custom_var']['slots'] as $custom_var) {
      $form_state['values']['custom_var']['slots'][$custom_var['slot']]['name'] = trim($custom_var['name']);
      $form_state['values']['custom_var']['slots'][$custom_var['slot']]['value'] = trim($custom_var['value']);

      // Validate empty names/values.
      if (empty($custom_var['name']) && !empty($custom_var['value'])) {
        $form_state->setErrorByName("custom_var][slots][" . $custom_var['slot'] . "][name", t('The custom variable @slot-number requires a <em>Name</em> if a <em>Value</em> has been provided.', array('@slot-number' => $custom_var['slot'])));
      }
      elseif (!empty($custom_var['name']) && empty($custom_var['value'])) {
        $form_state->setErrorByName("custom_var][slots][" . $custom_var['slot'] . "][value", t('The custom variable @slot-number requires a <em>Value</em> if a <em>Name</em> has been provided.', array('@slot-number' => $custom_var['slot'])));
      }
    }

    // Trim some text area values.
    $form_state['values']['site_id'] = trim($form_state['values']['site_id']);
    $form_state['values']['pages'] = trim($form_state['values']['pages']);
    $form_state['values']['codesnippet_before'] = trim($form_state['values']['codesnippet_before']);
    $form_state['values']['codesnippet_after'] = trim($form_state['values']['codesnippet_after']);

    if (!preg_match('/^\d{1,}$/', $form_state['values']['site_id'])) {
      $form_state->setErrorByName('site_id', t('A valid Piwik site ID is an integer only.'));
    }

    $url = $form_state['values']['url_http'] . 'piwik.php';
    $http_client = \Drupal::httpClient();
    /** @var \Guzzle\Http\Message\Response $result */
    $result = $http_client->get($url);
    if ($result->getStatusCode() != 200) {
      $form_state->setErrorByName('url_http', t('The validation of "@url" failed with error "@error" (HTTP code @code).', array('@url' => check_url($url), '@error' => $result->error, '@code' => $result->code)));
    }

    if (!empty($form_state['values']['url_https'])) {
      $url = $form_state['values']['url_https'] . 'piwik.php';
      $result = $http_client->get($url);
      if ($result->getStatusCode() != 200) {
        $form_state->setErrorByName('url_https', t('The validation of "@url" failed with error "@error" (HTTP code @code).', array('@url' => check_url($url), '@error' => $result->error, '@code' => $result->code)));
      }
    }

    // Delete obsolete local cache file.
    if (empty($form_state['values']['cache']) && $form['advanced']['cache']['#default_value']) {
      piwik_clear_js_cache();
    }

    // This is for the Newbie's who cannot read a text area description.
    if (preg_match('/(.*)<\/?script(.*)>(.*)/i', $form_state['values']['codesnippet_before'])) {
      $form_state->setErrorByName('codesnippet_before', t('Do not include the &lt;script&gt; tags in the javascript code snippets.'));
    }
    if (preg_match('/(.*)<\/?script(.*)>(.*)/i', $form_state['values']['codesnippet_after'])) {
      $form_state->setErrorByName('codesnippet_after', t('Do not include the &lt;script&gt; tags in the javascript code snippets.'));
    }
  }

}

