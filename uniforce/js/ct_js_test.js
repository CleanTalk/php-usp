var ct_date = new Date();

function ctSetCookie(c_name, value) {
	document.cookie = c_name + "=" + encodeURIComponent(value) + "; path=/";
}

var ctStart = function(){

	ctSetCookie("spbct_checkjs", spbct_checkjs_val);
	ctSetCookie("spbct_timezone", ct_date.getTimezoneOffset()/60*(-1));
	ctSetCookie("spbct_ps_timestamp", Math.floor(new Date().getTime()/1000));

	for( var i = 0 ; i < document.forms.length; i++ ){
		var form = document.forms[i];
		for( var e = 0; e < form.elements.length; e++ ) {
			var element = form.elements[e];
			if( element.type === 'password' ) {
				var ct_input = document.createElement("input");
				ct_input.name  = 'spbct_login_form';
				ct_input.type  = 'hidden';
				ct_input.value = '';
				form.appendChild(ct_input);
				break;
			}
		}
	}

};

if(typeof window.addEventListener == "function"){
	document.addEventListener("DOMContentLoaded", ctStart);
}else{
	document.attachEvent("DOMContentLoaded", ctStart);
}