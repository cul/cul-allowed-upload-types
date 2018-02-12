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

function cul_allowed_upload_types($mimes=array()) {

  if(get_option(CUL_ALLOWED_EXTENSIONS_KEY) == false || get_option(CUL_ALLOWED_EXTENSIONS_KEY) == '{}') {
    $mimes = array(
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
    	'mp3'                  => 'audio/mpeg',
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

    update_option(CUL_ALLOWED_EXTENSIONS_KEY, json_encode($mimes));
  }

  $mimes = json_decode(get_option(CUL_ALLOWED_EXTENSIONS_KEY), true);

  return $mimes;
}

add_action('upload_mimes', 'cul_allowed_upload_types');

function cul_allowed_upload_file_extensions_as_json() {
  return json_encode(array_keys(cul_allowed_upload_types()));
}

/** Set up settings page **/
if ( is_admin() ){
  cul_allowed_upload_types(array()); // Call allowed types method to initialize default values if blank

  if ( is_multisite() ) {
    add_action( 'network_admin_menu', 'network_admin_menu' );
  } else {
    add_action( 'admin_menu', 'cul_allowed_upload_types_plugin_menu' );
  }
  add_action( 'admin_init', 'register_cul_allowed_upload_types_plugin_settings' );
}

function network_admin_menu() {
	add_menu_page(
			'CUL Allowed Upload Types', // Page title
			'CUL Upload Types', // Menu title
			'manage_network_options', // Capability
			'cul_allowed_upload_types_menu', // Menu slug
			'cul_allowed_upload_types_plugin_options'
	);
}

function register_cul_allowed_upload_types_plugin_settings() {
  register_setting( 'cul-allowed-upload-types-group', CUL_ALLOWED_EXTENSIONS_KEY, 'cul_allowed_upload_types_validate');
}

function cul_allowed_upload_types_plugin_menu() {
  add_options_page( 'CUL Allowed Upload Types', 'CUL Upload Types', 'manage_options', 'cul-allowed-upload-types', 'cul_allowed_upload_types_plugin_options' );
}

function cul_allowed_upload_types_validate($new_value)
{
    // Validate the new value
    $valid = true;
    foreach(json_decode($new_value) as $extension => $mime_type) {
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
    if($valid) {
      return $new_value;
    } else {
      // Return the old value
      return get_option(CUL_ALLOWED_EXTENSIONS_KEY);
    }
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
  <form id="cul-allowed-upload-types-form" method="post" action="options.php">

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
