<?php
	
class TheAppForm{
	
	var $fields = array(
		'textfield' => 'Text',
		'hiddenfield' => 'Hidden',
		'emailfield' => 'Email',
		'numerfield' => 'Number',
		'spinnerfield' => 'Spinner',
		'passwordfield' => 'Password',
		'searchfield' => 'Search',
		'selectfield' => 'Select',
		'datepickerfield' => 'DatePicker',
		'textareafield' => 'TextArea',
		'urlfield' => 'Url',
		'checkboxfield' => 'Checkbox',
		'radiofield' => 'Radio',
		'sliderfield' => 'Slider',
		'togglefield' => 'Toggle',
		// 'filefield' => 'File' - doesn't work, needs more testing
	);
	
	public function __construct()
	{
		add_filter('TheAppFactory_init', array( &$this,'init') );		
	}
	
	public function init( & $the_app )
	{
		add_shortcode('app_form', array( &$this, 'shortcodes') );
		add_shortcode('app_form_element', array( &$this, 'shortcodes') );
		add_shortcode('fieldset', array( &$this, 'shortcodes') );
		foreach( $this->fields as $xtype => $field ) {
			add_shortcode( $xtype, array( &$this, 'shortcodes') );
		}
		
		$the_app->register('controller','FormsController', dirname(__FILE__) .'/the-app/src/controllers/FormsController.js');
	}
	
	public function shortcodes( $atts = array(), $content = null, $code = '' ) 
	{
		switch( $code ){
		case 'app_form':
			$this->addForm( $atts, $content );
			break;
		case 'app_form_element':
			$this->addFormElement( $atts, $content );
			break;
		default:
			$atts['xtype'] = $code;
			$this->addFormElement( $atts, $content );
			break;
		}
	}
	
	public function sanitize_atts( $atts, $code ) 
	{
		switch( $code ){
		case 'app_form':
			$defaults = array(
				'id' => null,					// To give the form a particular id
				'xtype' => 'formpanel',			// The xtype to use for the form.  Must be 'formpanel', or a subclass of 'formpanel'
				'url' => '',					// The url to submit the form to.  
				'method' => 'POST',				// The method to use when submitting the form
				'title' => 'My Form',			// The title for the form
				'icon' => 'star',				// The icon for the form in the tab panel	
				'fullscreen' => true,
				'ui' => 'round',
				'submitText' => 'Save'
			);
			break;
		case 'app_form_element':
			$defaults = array(
				'id' => null,					// The id to give the form element.  
				'xtype' => 'textfield',			// Other options are numberfield, textareafield, hiddenfield, radiofield, filefield, checkboxfield, selectfield, togglefield, fieldset.  See http://docs.sencha.com/touch/2.3.1/#!/api/Ext.field.Field
				'ui' => null, 					// The Sencha Touch ui to use.  
			);
			
			// Add in some other available attributes for specific form element types
			switch( $atts['xtype'] ){
			case 'textfield':
			case 'hiddenfield':
			case 'emailfield':
			case 'numberfield':
			case 'spinnerfield':
			case 'passwordfield':
			case 'searchfield':
			case 'selectfield':
			case 'datepickerfield':
			case 'textareafield':
			case 'urlfield':
			case 'filefield':
				$defaults = array_merge( $defaults, array(
					'name' => 'field',				// The HTML element name ( required for SERVER side processing )
					'label' => 'Field',				// The label for the field
					'value' => null,				// A default value
					'required' => false,			// Whether it is required
					'clearIcon' => true,			// Whether to include the clear icon
					'placeHolder' => null,			// The placeholder for the field
					'autoCapitalize' => null, 		// 'true' to set the autocapitalize attribute to on
					'autoComplete' => null, 		// 'true' to set the autocomplete attribute to on
					'readOnly' => null,				// 'true' to set the readonly attribute to on
					'maxLength' => null,			// The maxlength attribute for the field
					
				));
				
				switch( $atts['xtype'] ){
				case 'numberfield':
				case 'spinnerfield':
					// See http://docs.sencha.com/touch/2.3.1/#!/api/Ext.field.Number
					$defaults = array_merge( $defaults, array(
						'maxValue' => null,
						'minValue' => null,
						'stepValue' => null
					));
					if ( $atts['xtype'] == 'spinnerfield' ) {
						$defaults = array_merge( $defaults, array(
							'accelerateOnTapHold' => null,
							'cycle' => null,
							'defaultValue' => null,
							'groupButtons' => true
						));
					} 
					if ( isset( $atts['step_value'] ) ) {
						$atts['step_value'] = intval( $atts['step_value'] );
					}
					break;
				case 'selectfield':
				case 'datepickerfield':
					// See http://docs.sencha.com/touch/2.3.1/#!/api/Ext.field.Select
					$defaults = array_merge( $defaults, array(
						'autoSelect' => null,
						'displayField' => null,
						'options' => null,
						'store' => null,
						'valueField' => null,
						'clearIcon' => null
					));
					if ( $atts['xtype'] == 'datepickerfield' ) {
						$defaults = array_merge( $defaults, array(
							'dateFormat' => null
						));
					}
					if ( isset( $atts['options'] ) && !is_array( $atts['options'] ) ) {
						$atts['options'] = array_map( 'trim', explode( ',', $atts['options'] ) );
					}
					if ( isset( $atts['options'] ) && is_array( $atts['options'] ) && !is_array( reset( $atts['options'] ) ) ) {
						$options = array();
						foreach( $atts['options'] as $option ) {
							$options[] = array(
								'text' => $option,
								'value' => $option
							);
						}
						$atts['options'] = $options;
					}
					break;
				case 'textareafield':
					// See http://docs.sencha.com/touch/2.3.1/#!/api/Ext.field.TextArea
					$defaults = array_merge( $defaults, array(
						'maxRows' => null,
					));
					break;
				case 'filefield':
					$defaults = array_merge( $defaults, array(
						'accept' => null, // see http://www.w3schools.com/tags/att_input_accept.asp
						'capture' => null,
						'multiple' => null
					));
					break;
				}
				break;
			case 'sliderfield':
			case 'togglefield':
				$defaults = array_merge( $defaults, array(
					'name' => 'field',				// The HTML element name ( required for SERVER side processing )
					'label' => 'Field',				// The label for the field
					'value' => null,				// A default value
					'required' => false,			// Whether it is required
					'readOnly' => null,				// 'true' to set the readonly attribute to on
					'maxValue' => null,
					'minValue' => null,
					'increment' => null,
				));
				if ( isset( $atts['value'] ) && strpos( $atts['value'], ',' ) !== false ) {
					$atts['value'] = explode( ',', $atts['value'] );
				}
				break;
			case 'checkboxfield':
			case 'radiofield':
				$defaults = array_merge( $defaults, array(
					'name' => 'field',				// The HTML element name ( required for SERVER side processing )
					'label' => 'Field',				// The label for the field
					'value' => null,				// A default value
					'required' => false,			// Whether it is required
					'readOnly' => null,				// 'true' to set the readonly attribute to on
					'checked' => null,
				));
				break;
			case 'fieldset':
				$defaults = array_merge( $defaults, array(
					'title' => null,
					'instructions' => null
				));
				break;
			}
			break;
		}
		$atts = shortcode_atts( $defaults, TheAppFactory::sanitize_atts( $atts ) );
		foreach ( $atts as $key => $value ) {
			if ( !isset( $value ) ) {
				unset( $atts[ $key ] );
			} elseif ( is_numeric( $value ) ) {
				$atts[ $key ] = intval( $value );
			}
		}
		return $atts;
	}
	
	public function addForm( $atts, $content = null )
	{
		$atts = $this->sanitize_atts( $atts, 'app_form' );
		$this->formElements = array();
		do_shortcode($content);
		
		if ( $this->inFieldset ) {
			$this->finishFieldset();
		}
		$atts['items'] = $this->formElements;
		$panel = array(
			'xtype' => 'panel',
			'layout' => array( 'type' => 'fit' ),
			'title' => $atts['title'],
			'icon' => $atts['icon'],
			'items' => array(
				array(
					'xtype' => 'titlebar',
					'title' => $atts['title'],
					'docked' => 'top',
				),
				array(
					'xtype' => 'toolbar',
					'docked' => 'bottom',
					'items' => array(
						array(
							'xtype' => 'spacer'
						),
						array(
							'ui' => 'round',
							'text' => $atts['submitText'],
							'cls' => 'submit'
						)
					)
				),
				$atts
			)
		);

		$the_app = TheAppFactory::getInstance();

		$the_app->addItem( $panel );
		$the_app->enqueue( 'controller', 'FormsController' );
		
	}
	
	public function addFormElement( $atts, $content = null )
	{
		$atts = $this->sanitize_atts( $atts, 'app_form_element' );
		
		if ( $atts['xtype'] == 'fieldset' ) {
			if ( $this->inFieldset ) {
				$this->finishFieldset();
			}
			$this->startFieldset( $atts );
			do_shortcode( $content );
			$this->finishFieldset();
		} elseif ( $this->inFieldset ) {
			if ( in_array( $atts['xtype'], array( 'checkboxfield', 'radiofield' ) ) && strpos( $atts['value'], ',' ) !== false ) {
				$this->finishFieldset();
				$this->startFieldset( array(
					'title' => $atts['label']
				));
				foreach ( explode( ',', $atts['value'] ) as $value ) {
					$field = array_merge( $atts, array(
						'label' => $value,
						'value' => $value
					));
					$this->addFormElement( $field );
				}
				$this->finishFieldset();
			} else {
				$this->fieldsetItems[] = $atts;
			}
		} else {
			$this->startFieldset();
			$this->addFormElement( $atts, $content );
		}
		
		if ( isset( $this->fields[ $atts['xtype'] ] ) ) {
			$the_app = TheAppFactory::getInstance();
			$the_app->enqueue( 'require', 'Ext.field.' . $this->fields[ $atts['xtype'] ] );
		}
	}
	
	public function startFieldset( $atts = array() ){
		$this->inFieldset = true;
		$this->fieldsetItems = array();
		$this->currentFieldset = $atts;
	}
	
	public function finishFieldset(){
		if ( $this->inFieldset ) {
			$atts = array_merge( 
				array(
					'xtype' => 'fieldset',
					'items' => $this->fieldsetItems
				),
				$this->currentFieldset
			);
			$this->inFieldset = false;	
			if ( !empty( $atts['items'] ) ) {
				$this->formElements[] = $atts;
			}		
		}
	}
}

new TheAppForm();
