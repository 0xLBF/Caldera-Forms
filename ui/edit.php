<?php


global $field_type_list, $field_type_templates;

if( ! isset( $_GET['edit'] ) || ! is_string( $_GET['edit'] ) ){
	wp_die( esc_html__( 'Invalid form ID', 'caldera-forms'  ) );
}
// Load element
$element = $form = Caldera_Forms_Forms::get_form( $_GET['edit'] );
if( empty( $element ) || ! is_array( $element ) ){
	wp_die( esc_html__( 'Invalid form', 'caldera-forms'  ) );
}
/**
 * Runs before form editor is rendered, after form is gotten from DB.
 *
 * @since 1.4.3
 *
 * @param array $element Form config
 */
do_action( 'caldera_forms_prerender_edit', $element );

/**
 * Filter which Magic Tags are available in the form editor
 *
 *
 * @since 1.3.2
 *
 * @param array $tags Array of magic registered tags 
 * @param array $form_id for which this applies.
 */
$magic_tags = apply_filters( 'caldera_forms_get_magic_tags', array(), $element['ID'] );

//dump($element);
if(empty($element['success'])){
	$element['success'] = esc_html__( 'Form has successfully been submitted. Thank you.', 'caldera-forms' );
}

if(!isset($element['db_support'])){
	$element['db_support'] = 1;
}


/**
 * Convert existing field conditions if old method used
 *
 * @since 1.3.0
 */
if( empty( $element['conditional_groups'] ) ){
	
	$element['conditional_groups'] = array();
	if( !empty( $element['fields'] ) ){
		foreach( $element['fields'] as $field_id=>$field ){

			if( !empty( $field['conditions'] ) && !empty( $field['conditions']['type'] ) ){

				if( empty( $field['conditions']['group'] ) ){
					continue;
				}
				$element['conditional_groups']['conditions'][ 'con_' . $field['ID'] ] = array(
					'id' => 'con_' . $field['ID'],
					'name'	=> $field['label'],
					'type'	=> $field['conditions']['type'],
					'fields'=> array(),
					'group' => array()
				);

				foreach( $field['conditions']['group'] as $groups_id=>$groups ){
					foreach( $groups as $group_id => $group ){
						$element['conditional_groups']['conditions'][ 'con_' . $field['ID'] ]['fields'][ $group_id ] = $group['field'];
						$element['conditional_groups']['conditions'][ 'con_' . $field['ID'] ]['group'][ $groups_id ][ $group_id ] = array(
							'parent'	=>	$groups_id,
							'field'		=>	$group['field'],
							'compare'	=>	$group['compare'],
							'value'		=>	$group['value']
						);
					}
				}
				$element['fields'][ $field_id ]['conditions'] = array(
					'type' => 'con_' . $field['ID']
				);
			}
		}
	}
}

if ( ! isset( $element['fields'] ) ) {
	$element['fields'] = array();
}

$element['conditional_groups']['fields'] = $element['fields'];

// place nonce field
wp_nonce_field( 'cf_edit_element', 'cf_edit_nonce' );

// Init check
echo "<input id=\"last_updated_field\" name=\"config[_last_updated]\" value=\"" . date('r') . "\" type=\"hidden\">";
echo "<input id=\"form_id_field\" name=\"config[ID]\" value=\"" . $_GET['edit'] . "\" type=\"hidden\">";

do_action('caldera_forms_edit_start', $element);

// Get Fieldtpyes
$field_types = Caldera_Forms_Fields::get_all();

// Get Elements
$panel_extensions = Caldera_Forms_Admin_Panel::get_panels();


$field_type_list = array();
$field_type_templates = array();
$field_type_defaults = array(
	"var fieldtype_defaults = {};"
);

// options based template
$field_options_template = "
<div class=\"caldera-config-group caldera-config-group-full\">
	<div class=\"caldera-config-group\">
		<div class=\"caldera-config-field\">
			<label><input id=\"{{_id}}_auto\" type=\"checkbox\" class=\"auto-populate-options field-config\" name=\"{{_name}}[auto]\" value=\"1\" {{#if auto}}checked=\"checked\"{{/if}}> ".esc_html__( 'Auto Populate', 'caldera-forms' )."</label>
		</div>
	</div>
</div>
{{#if auto}}{{#script}}jQuery('#{{_id}}_auto').trigger('change');{{/script}}{{/if}}
<div class=\"caldera-config-group-auto-options\" style=\"display:none;\">
	<div class=\"caldera-config-group\">
		<label>". esc_html__( 'Auto Type', 'caldera-forms' ) . "</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config auto-populate-type\" name=\"{{_name}}[auto_type]\">
				<option value=\"\">" . esc_html__( 'Select a source', 'caldera-forms' ) . "</option>
				<option value=\"post_type\"{{#is auto_type value=\"post_type\"}} selected=\"selected\"{{/is}}>" . esc_html__( 'Post Type', 'caldera-forms' ) . "</option>
				<option value=\"taxonomy\"{{#is auto_type value=\"taxonomy\"}} selected=\"selected\"{{/is}}>" . esc_html__( 'Taxonomy', 'caldera-forms' ) . "</option>";
				ob_start();

				/**
				 * Runs after default field auto-population types options are outputted, inside of the select element.
				 *
				 * Use this to add new options in UI for auto-population sources
				 *
				 * @since unknown
				 */
				do_action( 'caldera_forms_autopopulate_types' );
				$field_options_template .= ob_get_clean() . "
			</select>
		</div>
	</div>
	
	<div class=\"caldera-config-group caldera-config-group-auto-taxonomy auto-populate-type-panel\" style=\"display:none;\">
		<label>". esc_html__( 'Taxonomy', 'caldera-forms' )."</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config\" name=\"{{_name}}[taxonomy]\">";

			$taxonomies = get_taxonomies();

	    	foreach($taxonomies as $tax_type=>$tax_name){
	    		$field_options_template .= "<option value=\"" . $tax_type . "\" {{#is taxonomy value=\"" . $tax_type . "\"}}selected=\"selected\"{{/is}}>" . $tax_name . "</option>\r\n";
	    	}
	    	
			$field_options_template .= "</select>

		</div>
	</div>

	<div class=\"caldera-config-group caldera-config-group-auto-post_type auto-populate-type-panel\" style=\"display:none;\">
		<label>".esc_html__( 'Post Type', 'caldera-forms' ) ."</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config\" name=\"{{_name}}[post_type]\">";

			$post_types = get_post_types(array(), 'objects');

	    	foreach($post_types as $type){
	    		$field_options_template .= "<option value=\"" . $type->name . "\" {{#is post_type value=\"" . $type->name . "\"}}selected=\"selected\"{{/is}}>" . $type->labels->name . "</option>\r\n";
	    	}

			$field_options_template .= "</select>

		</div>
	</div>

	<div class=\"caldera-config-group caldera-config-group-auto-taxonomy caldera-config-group-auto-post_type auto-populate-type-panel\" style=\"display:none;\">
		<label>". esc_html__( 'Value', 'caldera-forms' )."</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config\" name=\"{{_name}}[value_field]\">
				<option value=\"name\" {{#is value_field value=\"name\"}}selected=\"selected\"{{/is}}>Name</option>\r\n
				<option value=\"id\" {{#is value_field value=\"id\"}}selected=\"selected\"{{/is}}>ID</option>\r\n
	    	</select>
		</div>
	</div>
	<div class=\"caldera-config-group caldera-config-group-auto-taxonomy auto-populate-type-panel\" style=\"display:none;\">
		<label>". esc_html__( 'Orderby', 'caldera-forms' )."</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config\" name=\"{{_name}}[orderby_tax]\">
				<option value=\"count\" {{#is value_field value=\"count\"}}selected=\"selected\"{{/is}}>
					" . __( 'Count', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"id\" {{#is value_field value=\"id\"}}selected=\"selected\"{{/is}}>
					" . __( 'ID', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"name\" {{#is value_field value=\"name\"}}selected=\"selected\"{{/is}}>
					" . __( 'Name', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"slug\" {{#is value_field value=\"slug\"}}selected=\"selected\"{{/is}}>
					" . __( 'Slug', 'caldera-forms'  ) ."
				</option>\r\n
	    	</select>
		</div>
	</div>
	<div class=\"caldera-config-group caldera-config-group-auto-post_type auto-populate-type-panel\" style=\"display:none;\">
		<label>". esc_html__( 'Orderby', 'caldera-forms' )."</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config\" name=\"{{_name}}[orderby_post]\">
				<option value=\"ID\" {{#is value_field value=\"ID\"}}selected=\"selected\"{{/is}}>
					" . __( 'ID', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"name\" {{#is value_field value=\"name\"}}selected=\"selected\"{{/is}}>
					" . __( 'Name (post slug)', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"author\" {{#is value_field value=\"author\"}}selected=\"selected\"{{/is}}>
					" . __( 'Author', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"title\" {{#is value_field value=\"title\"}}selected=\"selected\"{{/is}}>
					" . __( 'Title', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"date\" {{#is value_field value=\"date\"}}selected=\"selected\"{{/is}}>
					" . __( 'Publish Date', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"modified\" {{#is value_field value=\"modified\"}}selected=\"selected\"{{/is}}>
					" . __( 'Modified Date', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"parent\" {{#is value_field value=\"parent\"}}selected=\"selected\"{{/is}}>
					" . __( 'Parent ID', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"comment_count\" {{#is value_field value=\"comment_count\"}}selected=\"selected\"{{/is}}>
					" . __( 'Comment Count', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"menu_order\" {{#is value_field value=\"menu_order\"}}selected=\"selected\"{{/is}}>
					" . __( 'Menu Order', 'caldera-forms'  ) ."
				</option>\r\n
	    	</select>
		</div>
	</div>
	<div class=\"caldera-config-group caldera-config-group-auto-taxonomy caldera-config-group-auto-post_type auto-populate-type-panel\" style=\"display:none;\">
		<label>". esc_html__( 'Order', 'caldera-forms' )."</label>
		<div class=\"caldera-config-field\">
			<select class=\"block-input field-config\" name=\"{{_name}}[order]\">
				<option value=\"ASC\" {{#is value_field value=\"ASC\"}}selected=\"selected\"{{/is}}>
					" . __( 'Ascending', 'caldera-forms'  ) ."
				</option>\r\n
				<option value=\"DESC\" {{#is value_field value=\"DESC\"}}selected=\"selected\"{{/is}}>
					" . __( 'Descending', 'caldera-forms'  ) ."
				</option>\r\n
	    	</select>
		</div>
	</div>


	";
	ob_start();

	/**
	 * Runs after default options for auto-populate fields
	 *
	 * Use this to add new options in UI when making custom aut-population types
	 *
	 * @since unknown
	 */
	do_action( 'caldera_forms_autopopulate_type_config' );

	/**
	 * Filter to setup presets for option fields
	 *
	 * Use this to add new option presets for option based fields like Checkboxes, radios and selects
	 *
	 * @since 1.4.0
	 * @param array $presets Array of current presets 
	 * @param array $element current structure of form
	 */
	$option_presets = apply_filters( 'caldera_forms_field_option_presets', array(), $element );
	$preset_options = array();
	if( !empty( $option_presets ) && is_array( $option_presets ) ){
		foreach ($option_presets as $preset_name => $preset ) {
			if( empty( $preset['name'] ) ){ continue; }
			$preset_options[] = '<option value="' . esc_attr( $preset_name ) . '">' . esc_html( $preset['name'] ) . '</option>';
		}
	}
	$preset_options = implode(' ', $preset_options );

	$field_options_template .= ob_get_clean() . "

</div>
<div class=\"caldera-config-group-toggle-options\" {{#if auto}}style=\"display:none;\"{{/if}} data-field=\"{{_id}}\">
	<div class=\"caldera-config-group caldera-config-group-full\">
		<button type=\"button\" class=\"button add-toggle-option\" style=\"width: 180px;\">" . esc_html__( 'Add Option', 'caldera-forms' ) . "</button>
		<button type=\"button\" data-bulk=\"#{{_id}}_bulkwrap\" class=\"button add-toggle-option\" style=\"width: 190px;\">" . esc_html__( 'Bulk Insert / Preset', 'caldera-forms' ) . "</button>
		<div id=\"{{_id}}_bulkwrap\" style=\"display:none; margin-top:10px;\" class=\"bulk-preset-panel\">
		<select data-bulk=\"#{{_id}}_batch\" class=\"preset_options block-input\" style=\"margin-bottom:6px;\">
		<option value=\"\">" . esc_html__( 'Select a preset', 'caldera-forms' ) . "</option>
		" . $preset_options . "
		</select>		
		<textarea style=\"resize:vertical; height:200px;\" class=\"block-input\" id=\"{{_id}}_batch\"></textarea>
		<p class=\"description\">" . esc_html__( 'Single option per line. These replace the current list.', 'caldera-forms' ) . "</p>
		<button type=\"button\" data-options=\"#{{_id}}_batch\" class=\"button block-button add-toggle-option\" style=\"margin: 10px 0;\">" . esc_html__( 'Insert Options', 'caldera-forms' ) . "</button>
		</div>
	</div>
	<div class=\"caldera-config-group caldera-config-group-full\">
	<label style=\"padding: 10px;\"><input type=\"radio\" class=\"toggle_set_default no-default field-config\" name=\"{{_name}}[default]\" value=\"\" {{#unless default}}checked=\"checked\"{{/unless}}> " . esc_html__( 'No Default', 'caldera-forms' ) . "</label>
	<label class=\"pull-right\" style=\"padding: 10px;\"><input type=\"checkbox\" class=\"toggle_show_values field-config\" name=\"{{_name}}[show_values]\" value=\"1\" {{#if show_values}}checked=\"checked\"{{/if}}> " . esc_html__( 'Show Values', 'caldera-forms' ) . "</label>
	</div>
	<div class=\"caldera-config-group-option-labels\" {{#unless show_values}}style=\"display:none;\"{{/unless}}>
		<span style=\"display: block; clear: left; padding-left: 65px; float: left; width: 142px;\">" . esc_html__( 'Value', 'caldera-forms' ) . "</span>
		<span style=\"float: left;\">" . esc_html__( 'Label', 'caldera-forms' ) . "</span>
	</div>
	<div class=\"caldera-config-group caldera-config-group-full toggle-options caldera-config-field\" data-field=\"{{_id}}\" id=\"field-options-{{_id}}\">
		{{#each option}}
		<div class=\"toggle_option_row\">
			<i class=\"dashicons dashicons-sort\" style=\"padding: 4px 9px;\"></i>
			<input type=\"radio\" data-config-type=\"option-default\" class=\"toggle_set_default field-config\" name=\"{{../_name}}[default]\" value=\"{{@key}}\" {{#is ../default value=\"@key\"}}checked=\"checked\"{{/is}}>
			<span style=\"position: relative; display: inline-block;\"><input{{#unless ../show_values}} style=\"display:none;\"{{/unless}} type=\"text\" class=\"toggle_value_field field-config required magic-tag-enabled\" name=\"{{../_name}}[option][{{@key}}][value]\" value=\"{{#if ../show_values}}{{value}}{{else}}{{label}}{{/if}}\" placeholder=\"value\" data-config-type=\"option-value\"></span>
			<input{{#unless ../show_values}} style=\"width:245px;\"{{/unless}} type=\"text\" data-option=\"{{@key}}\" class=\"toggle_label_field field-config required\" name=\"{{../_name}}[option][{{@key}}][label]\" value=\"{{label}}\" placeholder=\"label\" data-config-type=\"option-label\">
			<button class=\"button button-small toggle-remove-option\" type=\"button\"><i class=\"icn-delete\"></i></button>		
		</div>
		{{/each}}
		
	</div>
	<div style=\"display:none;\" class=\"notice error\"><p>" . esc_html__( 'Option values must be unique.', 'caldera-forms' ) . "</p></div>
</div>
";

$default_template = "
<div class=\"caldera-config-group\">
	<label>Default</label>
	<div class=\"caldera-config-field\">
		<input type=\"text\" class=\"block-input field-config\" name=\"{{_name}}[default]\" value=\"{{default}}\">
	</div>
</div>
";


// type list
$field_type_list = array(
	esc_html__( 'Basic', 'caldera-forms' )       => array(),
	esc_html__( 'Select', 'caldera-forms' )         => array(),
	esc_html__( 'eCommerce', 'caldera-forms' )         => array(),
	esc_html__( 'File', 'caldera-forms' )      => array(),
	esc_html__( 'Content', 'caldera-forms' )      => array(),
	esc_html__( 'Special', 'caldera-forms' ) => array(),
	
);

// Build Field Types List
foreach($field_types as $field_slug=>$config){

	if(!file_exists($config['file'])){
		if(!function_exists($config['file'])){
			continue;
		}
	}

	$categories = array();
	if(!empty($config['category'])){
		$categories = explode(',', $config['category']);
	}
	foreach((array) $categories as $category){
		if( !isset( $field_type_list[trim($category)] ) ){
			$category = esc_html__( 'Special', 'caldera-forms' );
		}
		$field_type_list[trim($category)][$field_slug] = $config;
	}

	ob_start();
	do_action('caldera_forms_field_settings_template', $config, $field_slug );
	if(!empty($config['setup']['template'])){
		if(file_exists( $config['setup']['template'] )){
			// create config template block							
			include $config['setup']['template'];
		}
	}

	$field_type_templates[sanitize_key( $field_slug ) . "_tmpl"] = ob_get_clean();

	if(isset($config['options'])){
		if(!isset($field_type_templates[sanitize_key( $field_slug ) . "_tmpl"])){
			$field_type_templates[sanitize_key( $field_slug ) . "_tmpl"] = null;
		}

		// has configurable options - include template
		$field_type_templates[sanitize_key( $field_slug ) . "_tmpl"] .= $field_options_template;
	}

	
	if(!empty($config['setup']['default'])){
		$field_type_defaults[] = "fieldtype_defaults." . sanitize_key( $field_slug ) . "_cfg = " . json_encode($config['setup']['default']) .";";
	}
	if(!empty($config['setup']['not_supported'])){
		$field_type_defaults[] = "fieldtype_defaults." . sanitize_key( $field_slug ) . "_nosupport = " . json_encode($config['setup']['not_supported']) .";";
	}

	if(empty($config['setup']['preview']) || !file_exists( $config['setup']['preview'] )){

		// if preview is a function
		if(!empty($config['setup']['preview']) && function_exists($config['setup']['preview'])){
			$func = $config['setup']['preview'];
			$field_type_templates['preview-' . sanitize_key( $field_slug ) . "_tmpl"] = $func($config);
		}else{
			// simulate a preview with actual field file
			$field = array(
				'label'	=>	'{{label}}',
				'slug'	=>	'{{slug}}',
				'type'	=>	'{{type}}',
				'caption' => '{{caption}}',
				'config' => (!empty($config['setup']['default']) ? $config['setup']['default'] : array() )
			);

			$field_name = $field['slug'];
			$field_id = 'preview_fld_' . $field['slug'];
			$wrapper_before = "<div class=\"preview-caldera-config-group\">";
			$field_before = "<div class=\"preview-caldera-config-field\">";
			$field_after = '</div>';
			$wrapper_after = '</div>';
			$field_label = "<label for=\"" . $field_id . "\" class=\"control-label\">" . $field['label'] . "</label>\r\n";
			$field_required = "";
			$field_placeholder = 'placeholder="' . $field['label'] .'"';
			$field_caption = "<span class=\"help-block\">" . $field['caption'] . "</span>\r\n";
			
			// blank default
			$field_value = null;
			$field_class = "preview-field-config";
			if( file_exists( $config[ 'file' ] ) ){
				$file = $config[ 'file' ];
			}else{
				$file = CFCORE_PATH . 'fields/generic-input';
			}
			ob_start();
			include $file;
			$field_type_templates['preview-' . sanitize_key( $field_slug ) . "_tmpl"] = ob_get_clean();
		}
	}else{
		ob_start();
		include $config['setup']['preview'];
		$field_type_templates['preview-' . sanitize_key( $field_slug ) . "_tmpl"] = ob_get_clean();
	}


}


function caldera_forms_field_wrapper_template($id = '{{id}}', $label = '{{label}}', $slug = '{{slug}}', $caption = '{{caption}}', $hide_label = '{{hide_label}}', $required = '{{required}}', $entry_list = '{{entry_list}}', $type = null ){

	?>
	<div class="caldera-editor-field-config-wrapper caldera-editor-config-wrapper " id="<?php echo $id; ?>" style="display:none;">
		

		<h3 class="caldera-editor-field-title"><?php echo $label; ?>&nbsp;</h3>		
		<input type="hidden" class="field-config" name="config[fields][<?php echo $id; ?>][ID]" value="<?php echo $id; ?>">
		<div id="<?php echo $id; ?>_settings_pane" class="wrapper-instance-pane">
			<div class="caldera-config-group">
				<label for="<?php echo $id; ?>_type">
					<?php echo esc_html__( 'Field Type', 'caldera-forms' ); ?>
				</label>
				<div class="caldera-config-field">
					<select class="block-input caldera-select-field-type" data-field="<?php echo $id; ?>" id="<?php echo $id; ?>_type" name="config[fields][<?php echo $id; ?>][type]" data-type="<?php echo $type; ?>" data-config-type="type">
						<?php
						echo build_field_types($type);
						?>
					</select>
				</div>
			</div>
			<div class="caldera-config-group">
				<label for="<?php echo esc_attr( $id ); ?>_fid">
					<?php echo esc_html__( 'ID', 'caldera-forms' ); ?>
				</label>
				<div class="caldera-config-field">
					<input type="text" class="block-input field-id" id="<?php echo $id; ?>_fid" value="<?php echo $id; ?>" readonly="readonly" data-config-type="ID">
				</div>
			</div>

			<div class="caldera-config-group">
				<label for="<?php echo esc_attr( $id ); ?>_lable">
					<?php echo esc_html__( 'Name', 'caldera-forms' ); ?>
				</label>
				<div class="caldera-config-field">
					<input type="text" class="block-input field-config field-label required" id="<?php echo $id; ?>_lable" data-config-type="label" name="config[fields][<?php echo $id; ?>][label]" value="<?php echo sanitize_text_field( $label ); ?>">
				</div>
			</div>

			<div class="caldera-config-group hide-label-field">
				<label for="<?php echo esc_attr( $id ); ?>_hide_label">
					<?php echo esc_html__( 'Hide Label', 'caldera-forms' ); ?>
				</label>
				<div class="caldera-config-field">
					<input type="checkbox" class="field-config field-checkbox" id="<?php echo $id; ?>_hide_label" data-config-type="hide_label" name="config[fields][<?php echo $id; ?>][hide_label]" value="1" <?php if($hide_label === 1){ echo 'checked="checked"'; }else{?>{{#if hide_label}}checked="checked"{{/if}}<?php } ?> >
				</div>
			</div>

			<div class="caldera-config-group">
				<label for="<?php echo esc_attr( $id ); ?>_slug">
					<?php echo esc_html__( 'Slug', 'caldera-forms' ); ?>
				</label>
				<div class="caldera-config-field">
					<input type="text" class="block-input field-config field-slug required" id="<?php echo $id; ?>_slug" name="config[fields][<?php echo $id; ?>][slug]" value="<?php echo $slug; ?>" data-config-type="slug">
				</div>
			</div>
			<div class="caldera-config-group">
				<label for="<?php echo esc_attr( $id ); ?>_fcond">
					<?php echo esc_html__( 'Condition', 'caldera-forms' ); ?>
				</label>
				<div class="caldera-config-field">
					<select id="field-condition-type-<?php echo $id; ?>" name="config[fields][<?php echo $id; ?>][conditions][type]" data-id="<?php echo $id; ?>" class="caldera-conditionals-usetype block-input" data-config-type="conditions">
						<option></option>
						<optgroup class="cf-conditional-selector">
							<?php if( !in_array( $condition_type, array( 'show', 'hide','disable' ) ) ){ ?><option value="<?php echo $condition_type; ?>" selected="selected"><?php echo esc_html__( 'Disable', 'caldera-forms' ); ?></option><?php } ?></optgroup>
						</optgroup>
					</select>
				</div>
			</div>			
			<div class="caldera-config-group required-field">
				<label for="<?php echo esc_attr( $id ); ?>_required">
					<?php echo esc_html__( 'Required', 'caldera-forms' ); ?>
				</label>
				<div class="caldera-config-field">
					<input type="checkbox" class="field-config field-required field-checkbox" id="<?php echo esc_attr( $id ); ?>_required" name="config[fields][<?php echo $id; ?>][required]" value="1" <?php if($required === 1){ echo 'checked="checked"'; }else{?>{{#if required}}checked="checked"{{/if}}<?php } ?> data-config-type="required">
				</div>
			</div>

			<div class="caldera-config-group caption-field">
				<label for="<?php echo $id; ?>_caption">
					<?php echo esc_html__( 'Description', 'caldera-forms' ); ?>
				</label>
				<div class="caldera-config-field">
					<input type="text" class="block-input field-config" id="<?php echo $id; ?>_caption" name="config[fields][<?php echo $id; ?>][caption]" value="<?php echo esc_html( $caption ); ?>" data-config-type="caption">
				</div>
			</div>
			
			<div class="caldera-config-group entrylist-field">
				<label for="<?php echo $id; ?>_entry_list">
					<?php echo esc_html__( 'Show in Entry List', 'caldera-forms' ); ?>
				</label>
				<div class="caldera-config-field">
					<input type="checkbox" class="field-config field-checkbox" id="<?php echo $id; ?>_entry_list" name="config[fields][<?php echo $id; ?>][entry_list]" value="1" <?php if($entry_list === 1){ echo 'checked="checked"'; }else{?>{{#if entry_list}}checked="checked"{{/if}}<?php } ?>>
				</div>
			</div>
			<div class="caldera-config-field-setup">
			</div>
			<button class="button delete-field block-button" data-confirm="<?php esc_attr_e( 'Are you sure you want to remove this field?. \'Cancel\' to stop. \'OK\' to delete', 'caldera-forms' ); ?>" type="button">
				<?php esc_html_e( 'Delete Field', 'caldera-forms' ); ?>
			</button>
		</div>

	</div>
	<?php
}

function build_field_types($default = null){
	global $field_type_list;
	

	$out = '';
	if(null === $default){
		$out .= '<option></option>';
	}

	foreach($field_type_list as $category=>$fields){

		$out .= "<optgroup label=\" ". $category . "\">\r\n";
		foreach ($fields as $field => $config) {

			$sel = "";
			if( $default === null ){
				$sel = "{{#is type value=\"" . $field . "\"}}selected=\"selected\"{{/is}}";
			}
			if($default == $field){
				$sel = 'selected="selected"';
			}

			$out .= "<option value=\"". $field . "\" ". $sel .">" . $config['field'] . "</option>\r\n";
		}
		$out .= "</optgroup>";
	}

	return $out;

}


function field_line_template($id = '{{id}}', $label = '{{label}}', $group = '{{group}}'){
	
	ob_start();

	?>
	<li data-field="<?php echo $id; ?>" class="caldera-field-line">
		<a href="#<?php echo $id; ?>">
			<i class="icn-right pull-right"></i>
			<i class="icn-field"></i>
			<?php echo htmlentities( $label ); ?>
		</a>
		<input type="hidden" class="caldera-config-field-group" value="<?php echo $group; ?>" name="config[fields][<?php echo $id; ?>][group]" autocomplete="off">
	</li>
	<?php

	return ob_get_clean();
}


// Navigation
?>
<div class="caldera-editor-header">
	<ul class="caldera-editor-header-nav">
		<li class="caldera-editor-logo">
			<span class="caldera-forms-name">Caldera Forms</span>
		</li>
		<li class="caldera-element-type-label">
			<?php echo $element['name']; ?>
		</li>
		<li>
			<a href="#settings-panel">
				<?php esc_html_e( 'Form Settings', 'caldera-forms'  ); ?>
			</a>
		</li>

	</ul>

	<div class="updated_notice_box">
		<?php esc_html_e( 'Updated Successfully', 'caldera-forms'  ); ?>
	</div>

	<button class="button button-primary caldera-header-save-button" data-active-class="none" data-load-element="#save_indicator" type="button" disabled="disabled">
		<?php esc_html_e( 'Save Form', 'caldera-forms' ); ?>
		<span id="save_indicator" class="spinner" style="position: absolute; right: -33px;"></span>
	</button>
	<a class="button caldera-header-preview-button" target="_blank" href="<?php echo esc_url( add_query_arg( 'cf_preview', $element[ 'ID' ], get_home_url() ) ); ?>">
		<?php esc_html_e( 'Preview Form', 'caldera-forms' ); ?>
	</a>

	<?php
	if ( !empty( $element['mailer']['preview_email'] ) ){
		$has_email_preview = 'aria-hidden="false" ';
	}else{
		$has_email_preview = 'aria-hidden="true" style="display:none;visibility:hidden;"';
	}
	?>
	<a class="button caldera-header-email-preview-button" target="_blank" href="<?php echo esc_url( add_query_arg( array(
			'cf-email-preview' => wp_create_nonce( $element[ 'ID' ] ),
			'cf-email-preview-form' => $element[ 'ID' ]
	),  get_home_url() ) ); ?>" <?php echo $has_email_preview; ?>>
		<?php esc_html_e( 'Preview Last Email', 'caldera-forms' ); ?>
	</a>
</div>

<?php include CFCORE_PATH  . 'ui/panels/form-settings.php'; ?>
	<div class="caldera-editor-header caldera-editor-subnav">
		<ul class="caldera-editor-header-nav">

		<?php
		// PANELS LOWER NAV

		foreach($panel_extensions as $panel_slug=>$panel){
			if(empty($panel['tabs'])){
				continue;
			}

			?>
					<?php
					// BUILD ELEMENT SETUP TABS
					if(!empty($panel['tabs'])){
						// PANEL BASED TABS
						foreach($panel['tabs'] as $group_slug=>$tab_setup){
							if($tab_setup['location'] !== 'lower'){
								continue;
							}

							$active = null;
							if(!empty($tab_setup['active'])){
								$active = " class=\"active\"";
							}
							echo "<li".$active." id=\"tab_".$group_slug."\"><a href=\"#" . $group_slug . "-config-panel\">" . $tab_setup['name'] . "</a></li>\r\n";
						}

						// CODE BASED TABS
						if(!empty($panel['tabs']['code'])){
							foreach($panel['tabs']['code'] as $code_slug=>$tab_setup){
								$active = null;
								if(!empty($tab_setup['active'])){
									$active = " class=\"active\"";
								}
								echo "<li".$active."><a href=\"#" . $code_slug . "-code-panel\" data-editor=\"" . $code_slug . "-editor\">" . $tab_setup['name'] . "</a></li>\r\n";
							}
						}

					}

					?>
			<?php
		}
		?>
		</ul>
	</div>
<?php

// PANEL WRAPPERS & RENDER
$repeatable_templates = array();
foreach($panel_extensions as $panel){
	if(empty($panel['tabs'])){
		continue;
	}

	foreach($panel['tabs'] as $panel_slug=>$tab_setup){
		$active = "  style=\"display:none;\"";
		if(!empty($tab_setup['active'])){
			$active = null;
		}
		echo "<div id=\"" . $panel_slug . "-config-panel\" class=\"caldera-editor-body caldera-config-editor-panel " . ( !empty($tab_setup['side_panel']) ? "caldera-config-has-side" : "" ) . "\"".$active.">\r\n";
			if( !empty($tab_setup['side_panel']) ){
				echo "<div id=\"" . $panel_slug . "-config-panel-main\" class=\"caldera-config-editor-main-panel\">\r\n";
			}
			echo '<h3>'.$tab_setup['label'];
				if( !empty( $tab_setup['repeat'] ) ){
					// add a repeater button
					echo " <a href=\"#" . $panel_slug . "_tag\" class=\"add-new-h2 caldera-add-group\" data-group=\"" . $panel_slug . "\">" . esc_html__( 'Add New', 'caldera-forms' ) . "</a>\r\n";
				}
				// ADD ACTIONS
				if(!empty($tab_setup['actions'])){
					foreach($tab_setup['actions'] as $action){
						include $action;
					}
				}
			echo '</h3>';
			// BUILD CONFIG FIELDS
			if(!empty($tab_setup['fields'])){
				// group index for loops
				$depth = 1;
				if(isset($element['settings'][$panel_slug])){
					// find max depth
					foreach($element['settings'][$panel_slug] as &$field_vars){
						if(count($field_vars) > $depth){
							$depth = count($field_vars);
						}
					}
				}
				for($group_index = 0; $group_index < $depth; $group_index++){
					
					if( !empty( $tab_setup['repeat'] ) ){
						echo "<div class=\"caldera-config-editor-panel-group\">\r\n";
					}
					foreach($tab_setup['fields'] as $field_slug=>&$field){
						$wrapper_before = "<div class=\"caldera-config-group\">";
						$field_before = "<div class=\"caldera-config-field\">";
						$field_after = '</div>';
						$wrapper_after = '</div>';
						$field_name = 'config[settings][' . $panel_slug . '][' . $field_slug . ']';
						$field_base_id = $field_id = $panel_slug. '_' . $field_slug . '_' . $group_index;						
						$field_label = "<label for=\"" . $field_id . "\">" . $field['label'] . "</label>\r\n";
						$field_placeholder = "";
						$field_required = "";
						if(!empty($field['hide_label'])){
							$field_label = "";
							$field_placeholder = 'placeholder="' . htmlentities( $field['label'] ) .'"';
						}


						$field_caption = null;
						if(!empty($field['caption'])){
							$field_caption = "<p class=\"description\">" . $field['caption'] . "</p>\r\n";
						}

						// blank default
						$field_value = null;

						if(isset($field['config']['default'])){
							$field_value = $field['config']['default'];
						}
						if(isset($element['settings'][$panel_slug][$field_slug])){
							$field_value = $element['settings'][$panel_slug][$field_slug];
						}

						$field_class = "field-config";
						if(!empty($field['required'])){
							$field_class .= " required";							
						}
						include $field_types[$field['type']]['file'];

					}
					if( !empty( $tab_setup['repeat'] ) ){
						echo "<a href=\"#remove_" . $panel_slug . "\" class=\"caldera-config-group-remove\">" . esc_html__( 'Remove', 'caldera-forms' ) . "</a>\r\n";
						echo "</div>\r\n";
					}
				}


				/// CHECK GROUP IS REPEATABLE ADN ADD A TEMPLATE IF IT IS
				if( !empty( $tab_setup['repeat'] ) ){

					$field_template = "<script type=\"text/html\" id=\"" . $panel_slug . "_panel_tmpl\">\r\n";
					$field_template .= "	<div class=\"caldera-config-editor-panel-group\">\r\n";

					foreach($tab_setup['fields'] as $field_slug=>&$field){
						
						$field_name = 'config[settings][' . $panel_slug . '][' . $field_slug . '][]';
						$field_id = $panel_slug. '_' . $field_slug;

						// blank default
						$field_value = null;

						if(isset($field['config']['default'])){
							$field_value = $field['config']['default'];
						}

						$field_template .= "	<div class=\"caldera-config-group\">\r\n";
							$field_template .= "		<label for=\"" . $field_id . "\">" . $field['label'] . "</label>\r\n";
							$field_template .= "		<div class=\"caldera-config-field\">\r\n";
								ob_start();
								include $field_types[$field['type']]['file'];
								$field_template .= ob_get_clean();
							$field_template .= "		</div>\r\n";
						$field_template .= "	</div>\r\n";

					}
					$field_template .= "	<a href=\"#remove-group\" class=\"caldera-config-group-remove\">" . esc_html__( 'Remove', 'caldera-forms' ) . "</a>\r\n";
					$field_template .= "	</div>\r\n";
					$field_template .= "</script>\r\n";

					$repeatable_templates[] = $field_template;

				}


			}elseif(!empty($tab_setup['canvas'])){
				include $tab_setup['canvas'];
			}

			if(!empty($tab_setup['side_panel'])){
				echo "</div>\r\n";
				echo "<div id=\"" . $panel_slug . "-config-panel-side\" class=\"caldera-config-editor-side-panel\">\r\n";

					include $tab_setup['side_panel'];

				echo "</div>\r\n";
			}

		echo "</div>\r\n";
	}
	echo "<a name=\"" . $panel_slug . "_tag\"></a>";
}

// PROCESSORS
do_action('caldera_forms_edit_end', $element);
?>
<script type="text/html" id="field-options-cofnig-tmpl">
<?php
	echo $field_options_template;
?>
</script>

<script type="text/html" id="form-fields-selector-tmpl">
	<div class="modal-tab-panel">
	<?php
		$sorted_field_types = array(
			__( 'Basic', 'caldera-forms' ) => '',
			__( 'Select', 'caldera-forms' ) => '',
			__( 'File', 'caldera-forms' ) => '',
			__( 'Content', 'caldera-forms' ) => '',
			__( 'eCommerce', 'caldera-forms' )  => '',
			__( 'Special', 'caldera-forms' ) => '',
			
		);

		if( defined( 'CFCORE_SHOW_DISCONTINUED_FIELDS' ) && CFCORE_SHOW_DISCONTINUED_FIELDS  ){
			$sorted_field_types[ __( 'Discontinued', 'caldera-forms' ) ] = '';
		}

		foreach($field_types as $field_slug=>$config){
			$cats[] = 'General';
			if(!empty($config['category'])){
				$cats = explode(',', $config['category']);
			}

			$svg = false;
			$icon = CFCORE_URL . "assets/images/field.png";
			if(!empty($config['icon'])){
				$icon = $config['icon'];
				if( false !== strpos( $icon, '.svg' ) ){
					$svg = true;
				}

			}
			foreach($cats as $cat){
				$cat = trim($cat);
				if(  __( 'Discontinued', 'caldera-forms' ) == $cat ){
					continue;
				}
				$template = '<div class="form-modal-add-line">';
					$template .= '<button type="button" class="button info-button set-current-field" data-field="{{id}}" data-type="' . $field_slug . '">' . esc_html__( 'Set Field', 'caldera-forms' ) . '</button>';
					$class = 'form-modal-lgo';
					if( $svg ){
						$class .= ' form-modal-lgo-svg';
					}
					$template .= '<img src="'. $icon .'" class="' . $class . '" width="45" height="45">';
					$template .= '<strong>' . $config['field'] . '</strong>';
					$template .= '<p class="description">' . (!empty($config['description']) ? esc_html__( $config[ 'description' ] ) : esc_html__( 'No description given', 'caldera-forms' ) ) . '</p>';
				$template .= '</div>';
				if(!isset($sorted_field_types[$cat])){
					$cat = __( 'Special', 'caldera-forms' );
				}
				$sorted_field_types[$cat] .= $template;
			}
		}

		$cat_show = false;

		foreach($sorted_field_types as $cat=>$template){
			if(!empty($cat_show)){
				$cat_show = 'style="display: none;"';
			}
			echo '<div id="modal-category-'. sanitize_key( $cat ) .'" data-tab="' . esc_attr( $cat ) . '" class="tab-detail-panel" '.$cat_show.'>';
				echo $template;
			echo '</div>';
			$cat_show = true;
		}

	?>
	</div>
</script>
<script type="text/html" id="caldera_field_config_wrapper_templ">
<?php
	echo caldera_forms_field_wrapper_template();
?>
</script>
<script type="text/html" id="field-option-row-tmpl">
	{{#each config.option}}
			<div class="toggle_option_row">
				<i class="dashicons dashicons-sort" style="padding: 4px 9px;"></i>
				<input type="radio" class="toggle_set_default field-config" name="{{../_name}}[default]" value="{{@key}}" {{#is ../default value="@key"}}checked="checked"{{/is}} data-config-type="option-default" data-option="{{@key}}" id="value-default-{{@key}}">
				<span style="position: relative; display: inline-block;">
					<input type="text" class="toggle_value_field field-config magic-tag-enabled"  name="{{../_name}}[option][{{@key}}][value]" value="{{value}}" placeholder="value" data-option="{{@key}}" data-config-type="option-value">
				</span>
				<input type="text" class="toggle_label_field field-config" data-option="{{@key}}"  name="{{../_name}}[option][{{@key}}][label]" value="{{label}}" placeholder="label" data-config-type="option-label">
				<button class="button button-small toggle-remove-option" type="button" data-option="{{@key}}" ><i class="icn-delete"></i></button>
			</div>
	{{/each}}
</script>
<script type="text/html" id="noconfig_field_templ" class="cf-editor-template">
<div class="caldera-config-group">
	<label>
		<?php esc_html_e( 'Default', 'caldera-forms' ); ?>
	</label>
	<div class="caldera-config-field">
		<input type="text" class="block-input field-config" name="{{_name}}[default]" value="{{default}}">
	</div>
</div>
</script>
<script type="text/html" id="conditional-group-tmpl">	
	{{#each group}}
		<div class="caldera-condition-group">
			<div class="caldera-condition-group-label"><?php echo esc_html__( 'or', 'caldera-forms' ); ?></div>			
			<div class="caldera-condition-lines" id="{{id}}_conditions_lines">
				{{#each lines}}
				<div class="caldera-condition-line">
					if 
					<select name="config[{{../type}}][{{../../id}}][conditions][group][{{../id}}][{{id}}][field]" data-condition="{{../type}}" class="caldera-field-bind caldera-conditional-field-set" data-id="{{../../id}}" {{#if field}}data-default="{{field}}"{{/if}} data-line="{{id}}" data-row="{{../id}}" data-all="true" style="max-width:120px;">
						{{#if field}}<option value="{{field}}" class="bound-field" selected="selected"></option>{{else}}<option value="">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</option>{{/if}}
					</select>
					<select class="compare-type" name="config[{{../type}}][{{../../id}}][conditions][group][{{../id}}][{{id}}][compare]" style="max-width:110px;">
						<option value="is" {{#is compare value="is"}}selected="selected"{{/is}}><?php echo esc_html__( 'is', 'caldera-forms' ); ?></option>
						<option value="isnot" {{#is compare value="isnot"}}selected="selected"{{/is}}><?php echo esc_html__( 'is not', 'caldera-forms' ); ?></option>
						<option value=">" {{#is compare value=">"}}selected="selected"{{/is}}><?php echo esc_html__( 'is greater than', 'caldera-forms' ); ?></option>
						<option value="<" {{#is compare value="<"}}selected="selected"{{/is}}><?php echo esc_html__( 'is less than', 'caldera-forms' ); ?></option>
						<option value="startswith" {{#is compare value="startswith"}}selected="selected"{{/is}}><?php echo esc_html__( 'starts with', 'caldera-forms' ); ?></option>
						<option value="endswith" {{#is compare value="endswith"}}selected="selected"{{/is}}><?php echo esc_html__( 'ends with', 'caldera-forms' ); ?></option>
						<option value="contains" {{#is compare value="contains"}}selected="selected"{{/is}}><?php echo esc_html__( 'contains', 'caldera-forms' ); ?></option>
					</select>
					<span style="padding: 0 12px 0; " class="caldera-conditional-field-value" data-value="{{value}}" id="{{id}}_value"><input disabled type="text" value="" placeholder="<?php echo esc_html__( 'Select field first', 'caldera-forms' ); ?>" style="max-width: 165px;"></span>
					<button type="button" class="button remove-conditional-line pull-right"><i class="icon-join"></i></button>
				</div>
				{{/each}}
			</div>
			<button type="button" class="button button-small ajax-trigger" data-id="{{../id}}" data-type="{{type}}" data-group="{{id}}" data-request="new_conditional_line" data-target="#{{id}}_conditions_lines" data-callback="rebuild_field_binding" data-template="#conditional-line-tmpl" data-target-insert="append"><?php echo esc_html__( 'Add Condition', 'caldera-forms' ); ?></button>
		</div>
	{{/each}}
</script>
<script type="text/html" id="conditional-line-tmpl">
	<div class="caldera-condition-line">
		<div class="caldera-condition-line-label"><?php echo esc_html__( 'and', 'caldera-forms' ); ?></div>
		if 
		<select name="{{name}}[field]" class="caldera-field-bind caldera-conditional-field-set" data-condition="{{type}}" data-id="{{id}}" data-line="{{lineid}}" data-row="{{rowid}}" data-all="true" style="max-width:120px;"></select>
		<select name="{{name}}[compare]" style="max-width:110px;">
			<option value="is"><?php echo esc_html__( 'is', 'caldera-forms' ); ?></option>
			<option value="isnot"><?php echo esc_html__( 'is not', 'caldera-forms' ); ?></option>
			<option value=">"><?php echo esc_html__( 'is greater than', 'caldera-forms' ); ?></option>
			<option value="<"><?php echo esc_html__( 'is less than', 'caldera-forms' ); ?></option>
			<option value="startswith"><?php echo esc_html__( 'starts with', 'caldera-forms' ); ?></option>
			<option value="endswith"><?php echo esc_html__( 'ends with', 'caldera-forms' ); ?></option>
			<option value="contains"><?php echo esc_html__( 'contains', 'caldera-forms' ); ?></option>
		</select>
		<span class="caldera-conditional-field-value" id="{{lineid}}_value"><input disabled type="text" value="" placeholder="<?php echo esc_html__( 'Select field first', 'caldera-forms' ); ?>" style="max-width: 165px;"></span>
		<button type="button" class="button remove-conditional-line pull-right"><i class="icon-join"></i></button>
	</div>
</script>
<?php

/// Output the field templates
foreach($field_type_templates as $key=>$template){
	echo "<script type=\"text/html\" class=\"cf-editor-template\" id=\"" . $key . "\">\r\n";
		echo $template;
	echo "\r\n</script>\r\n";
}
?>

<?php


$magic_script = array(
	'field' => array()
);

foreach($magic_tags as $magic_set_key=>$magic_tags_set){

	$magic_script[$magic_set_key] = array(
		'type'	=>	$magic_tags_set['type'],
		'tags'	=>	array(),
		'wrap'	=>  $magic_tags_set['wrap']
	);

	foreach($magic_tags_set['tags'] as $tag_key=>$tag_value){

		if(is_array($tag_value)){
			foreach($tag_value as $compatibility){
				$magic_script[$magic_set_key]['tags'][$compatibility][] = $tag_key;
			}
		}else{
			$magic_script[$magic_set_key]['tags']['text'][] = $tag_value;
		}
	}

}

?>
<script type="text/javascript">

<?php
// output fieldtype defaults
echo implode("\r\n", $field_type_defaults);

?>
var system_values = <?php echo json_encode( $magic_script ); ?>;
var preset_options = <?php echo json_encode( $option_presets ); ?>
</script>

<script type="text/javascript">
	jQuery('.error,.notice,.notice-error').remove();
</script>



















































