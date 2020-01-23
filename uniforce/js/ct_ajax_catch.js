// Capturing responses and output block message for unknown AJAX forms
if(typeof jQuery !== 'undefined'){
	jQuery(document).ajaxComplete(function(event, xhr, settings) {
		if(xhr.responseText && xhr.responseText.indexOf('"apbct') !== -1){
			var response = JSON.parse(xhr.responseText);
			if(typeof response.apbct !== 'undefined'){
				var response = response.apbct;
				if(response.blocked){
					alert(response.comment);
				}
			}
		}
	});
}
