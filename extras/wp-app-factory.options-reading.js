jQuery(function($){
	var $fieldset = $('#front-static-pages fieldset');
	$fieldset.append(
		$('<p/>').append(
			$('<label/>').append(
				$('<input/>').attr({
					type: 'radio',
					name: 'show_on_front',
					value: 'app',
					class: 'tog'
				}).prop('checked',(WP_APP_FACTORY.show_on_front == 'app'))
			).append(
				WP_APP_FACTORY.show_on_front_message
			)
		)
	).append($('<ul/>').append($('<li/>').append(WP_APP_FACTORY.app+': ').append(WP_APP_FACTORY.dropdown)));
	
	app_disabled = function(){
		$('#app_on_front').prop('disabled',!$fieldset.find('input[value=app]').is(':checked'));
	}

	app_disabled();
	$fieldset.find('input:radio').change(app_disabled);
});