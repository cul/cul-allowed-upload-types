<?php

/*
Plugin Name: CUL Allowed Upload Types
Plugin URI:  http://library.columbia.edu
Description: Limits the set of allowed upload file extensions for CUL WP sites.
Version:     1.0
Author:      Erix
Author URI:  http://library.columbia.edu
License:     MIT
License URI: https://opensource.org/licenses/MIT
*/

define('CUL_ALLOWED_EXTENSIONS_KEY', 'cul_allowed_extensions');

function get_default_mimes() {
  return array(
    // Image formats
    'jpg'                          => 'image/jpeg',
    'jpeg'                         => 'image/jpeg',
    'gif'                          => 'image/gif',
    'png'                          => 'image/png',
    'bmp'                          => 'image/bmp',
    'tif'                          => 'image/tiff',
    'tiff'                         => 'image/tiff',

    // Video formats
    'mp4'                          => 'video/mp4',
    'm4v'                          => 'video/mp4',
    'avi'                          => 'video/avi',

    // Text formats
    'txt'                          => 'text/plain',
    'csv'                          => 'text/csv',

    // Audio formats
    'mp3'                          => 'audio/mpeg',
    'm4a'                          => 'audio/mp4',
    'wav'                          => 'audio/wav',
    'ogg'                          => 'audio/ogg',
    'oga'                          => 'audio/ogg',
    'mid'                          => 'audio/midi',
    'midi'                         => 'audio/midi',

    // Misc application formats
    'rtf'                          => 'application/rtf',
    'pdf'                          => 'application/pdf',

    // MS Office formats
    'doc'                          => 'application/msword',
    'ppt'                          => 'application/vnd.ms-powerpoint',
    'xls'                          => 'application/vnd.ms-excel',
    'docx'                         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xlsx'                         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'pptx'                         => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

    // OpenOffice formats
    'odt'                          => 'application/vnd.oasis.opendocument.text',
    'odp'                          => 'application/vnd.oasis.opendocument.presentation',
    'ods'                          => 'application/vnd.oasis.opendocument.spreadsheet'
  );
}

function cul_allowed_upload_types($mimes=array()) {
  $current_value = get_site_option(CUL_ALLOWED_EXTENSIONS_KEY);
  if($current_value == false || $current_value == '{}' || $current_value == 'null') {
    update_site_option(CUL_ALLOWED_EXTENSIONS_KEY, json_encode(get_default_mimes()));
  }

  $mimes = json_decode(get_site_option(CUL_ALLOWED_EXTENSIONS_KEY), true);
  return $mimes;
}
add_action('upload_mimes', 'cul_allowed_upload_types');


function multisite_update_upload_types_setting_from_post_data() {
  $mime_json = stripslashes($_POST[CUL_ALLOWED_EXTENSIONS_KEY]);

  if(mime_json_is_valid($mime_json)) {
    update_site_option(CUL_ALLOWED_EXTENSIONS_KEY, $mime_json);

    // Since this is a multisite instance, also update the upload_filetypes site
    // option so it's in sync with our allowed extensions option.
    // This is the setting labeled "Upload file types" on the /wp-admin/network/settings.php page.
    update_site_option('upload_filetypes', 'Do Not Edit! Overridden by CUL Allowed Upload Types Plugin');

    wp_redirect(
      add_query_arg(
        array( 'page' => 'cul_allowed_upload_types_menu', 'updated' => 'true' ),
        (is_multisite() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ))
      )
    );

    exit;
  } else {

    echo '<h2>Validation Error</h2>';

    foreach(get_settings_errors() as $error) {
      echo '<p>' . $error['message'] . '</p>';
    }

    $return_url = add_query_arg(
      array( 'page' => 'cul_allowed_upload_types_menu' ),
      (is_multisite() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ))
    );

    echo '<a href="' . $return_url . '" onclick="window.history.back(); return false;">&laquo; Go Back</a>';

    wp_die();
  }
}

function cul_allowed_upload_file_extensions_as_json() {
  return json_encode(array_keys(cul_allowed_upload_types()));
}

/** Set up settings page **/
if ( is_admin() ){
  cul_allowed_upload_types(array()); // Call allowed types method to initialize default values if blank

  if ( is_multisite() ) {
    add_action( 'network_admin_menu',  'culaut_add_network_admin_menu' );
    add_action( 'network_admin_edit_multisite_update_upload_types_setting_from_post_data', 'multisite_update_upload_types_setting_from_post_data' );
  } else {
    add_action( 'admin_menu', 'culaut_add_admin_menu' );
    add_action( 'admin_init', 'register_cul_allowed_upload_types_plugin_settings' );
  }
}

function culaut_add_network_admin_menu() {
	add_menu_page(
			'CUL Allowed Upload Types', // Page title
			'CUL Upload Types', // Menu title
			'manage_network_options', // Capability
			'cul_allowed_upload_types_menu', // Menu slug
			'cul_allowed_upload_types_plugin_options'
	);
}

function culaut_add_admin_menu() {
  add_options_page( 'CUL Allowed Upload Types', 'CUL Upload Types', 'manage_options', 'cul-allowed-upload-types', 'cul_allowed_upload_types_plugin_options' );
}

function register_cul_allowed_upload_types_plugin_settings() {
  // It seems like register_setting is only necessary on single site, and does nothing on multisite
  register_setting( 'cul-allowed-upload-types-group', CUL_ALLOWED_EXTENSIONS_KEY, 'cul_allowed_upload_types_validate');
}

function cul_allowed_upload_types_validate($new_value)
{
    if(mime_json_is_valid($new_value)) {
      return $new_value;
    } else {
      // Return the old value
      return get_site_option(CUL_ALLOWED_EXTENSIONS_KEY);
    }
}

function mime_json_is_valid($mime_json) {
  // Validate the new value
  $valid = true;
  foreach(json_decode($mime_json) as $extension => $mime_type) {
    // Validate extension
    if( preg_match('/^[A-Za-z0-9]+$/', $extension) !== 1) {
      add_settings_error(
        'hasNumberError',
        'validationError',
        'Invalid extension: ' . $extension,
        'error');
      $valid = false;
    }

    // Validate mime type
    if( preg_match('/^[A-Za-z0-9]+\/[A-Za-z0-9.-]+$/', $mime_type) !== 1) {
      add_settings_error(
        'hasNumberError',
        'validationError',
        'Invalid mime type: ' . $mime_type,
        'error');
      $valid = false;
    }
  }
  return $valid;
}

function cul_allowed_upload_types_plugin_options() {
  if ( !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }
  ?>

  <script>
  var $ = jQuery;
  $(document).ready(function(){
    //Bind delete row action
    $('#cul-allowed-upload-types-form').on('click', '.delete-row', function(){
      $(this).closest('tr').remove();
    })

    //Bind add row action
    $('#cul-allowed-upload-types-form').on('click', '.add-row', function(){
      $trToClone = $(this).closest('tr');
      $clonedTr = $trToClone.clone();
      $clonedTr.find('input').val('');
      $trToClone.after($clonedTr);
      $trToClone.find('button.add-row').removeClass('add-row').addClass('delete-row').html('x');
    })

    //Bind submit action
    $('#cul-allowed-upload-types-form').on('submit', function(e){

      if($(this).find('textarea#json-editor').length > 0) {
        //Json editing mode

        //Validate json in json-editor textarea
        try {
          var newUploadTypes = JSON.parse($(this).find('textarea#json-editor').val());
        } catch(err) {
          alert('There is an error in your JSON. Please check and fix.');
          return false;
        }
      } else {
        //Form editing mode

        var newUploadTypes = {};

        $('#upload-type-values tbody').find('tr').each(function(){
          var extension = $(this).find('td.extension input').val();
          var mimeType = $(this).find('td.mime-type input').val();

          if(extension !== '' && mimeType !== '') {
            newUploadTypes[extension] = mimeType;
          }
        });

        $('#hidden-data-field').val(JSON.stringify(newUploadTypes));
      }

      return true;
    });
  });
  </script>
  <div class="wrap">
    <h2>CUL Allowed Upload Types</h2>
  </div>
  <form id="cul-allowed-upload-types-form" method="post" action="<?php echo is_multisite() ? admin_url('network/edit.php?action=multisite_update_upload_types_setting_from_post_data') : 'options.php'; ?>">
    <div>
      <?php if(isset($_GET['json']) && $_GET['json'] == 'true'): ?>
        <a href="<?php echo strtok($_SERVER["REQUEST_URI"],'?') . '?page=' . esc_html($_GET['page']) . '&amp;json=false'; ?>">Edit as Form</a>
      <?php else: ?>
        <a href="<?php echo strtok($_SERVER["REQUEST_URI"],'?') . '?page=' . esc_html($_GET['page']) . '&amp;json=true'; ?>">Edit as JSON</a>
      <?php endif; ?>
    </div>

    <?php settings_fields( 'cul-allowed-upload-types-group' ); ?>
    <?php do_settings_sections( 'cul-allowed-upload-types-group' ); ?>

    <?php
      $allowed_extensions = cul_allowed_upload_types();
      ksort($allowed_extensions);
    ?>

    <?php if(isset($_GET['json']) && $_GET['json'] == 'true'): ?>
      <br />
      <textarea id="json-editor" name="<?php echo CUL_ALLOWED_EXTENSIONS_KEY; ?>" rows="20" cols="100"><?php
        echo json_encode($allowed_extensions);
      ?></textarea>
    <?php else: ?>
      <div>
        <p>Make sure to hit the save button after making changes to this list!</p>
      </div>
      <table class="form-table">
        <input id="hidden-data-field" type="hidden" name="<?php echo CUL_ALLOWED_EXTENSIONS_KEY; ?>" value="">
        <tr>
          <td>
            <table id="upload-type-values">
              <thead>
                <tr>
                  <th>Extension</th>
                  <th>Mime Type</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $allowed_extensions[''] = ''; //add empty item at the end so we have a new, blank row at the end of the table
                  foreach($allowed_extensions as $extension => $mimeType):
                ?>
                <tr>
                  <td class="extension"><input type="text" placeholder="extension" value="<?php echo esc_html($extension); ?>"></td>
                  <td class="mime-type"><input type="text" placeholder="mime type" value="<?php echo esc_html($mimeType); ?>"></td>
                  <?php if($extension != ''): ?>
                    <td><button class="delete-row button button-secondary" type="button">x</button></td>
                  <?php else: ?>
                    <td><button class="add-row button button-secondary" type="button">Add</button></td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </td>
        </tr>
      </table>
    <?php endif; ?>
    <?php submit_button(); ?>
  </form>
  <?php
}

?>
