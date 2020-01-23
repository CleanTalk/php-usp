var ct_date = new Date(), 
	ctTimeMs = new Date().getTime(),
	ctMouseEventTimerFlag = true, //Reading interval flag
	ctMouseData = [],
	ctMouseDataCounter = 0;

function ctSetCookie(c_name, value) {
	document.cookie = c_name + "=" + encodeURIComponent(value) + "; path=/";
}

var ctStart = function(){
	ctSetCookie("apbct_checkjs", apbct_checkjs_val);
	ctSetCookie("apbct_timezone", ct_date.getTimezoneOffset()/60*(-1));
	ctSetCookie("apbct_ps_timestamp", Math.floor(new Date().getTime()/1000));
	ctSetCookie("apbct_visible_fields", 0);
	ctSetCookie("apbct_visible_fields_count", 0);

	setTimeout(function(){
		
		for(var i = 0, host = '', action = ''; i < document.forms.length; i++){
			var form = document.forms[i];
			
			if( typeof(form.action) == 'string' ){
			
				action = document.forms[i].action;
				if( action.indexOf('http://') != -1 || action.indexOf('https://') != -1 ){
					
					tmp  = action.split('//');
					tmp  = tmp[1].split('/');
					host = tmp[0].toLowerCase();
					last = tmp[tmp.length-1].toLowerCase();
				
					if( host != location.hostname.toLowerCase() || (last != 'index.php' && last.indexOf('.php') != -1)){
						var ct_action = document.createElement("input");
						ct_action.name='ct_action';
						ct_action.value=action;
						ct_action.type='hidden';
						document.forms[i].appendChild(ct_action);
						
						var ct_method = document.createElement("input");
						ct_method.name='ct_method';
						ct_method.value=document.forms[i].method;
						ct_method.type='hidden';
						document.forms[i].appendChild(ct_method);
											
						document.forms[i].method = 'POST';
						
						if (!window.location.origin){
							window.location.origin = window.location.protocol + "//" + window.location.hostname;
						}
						document.forms[i].action = window.location.origin;
					}
				}
			}
			
			form.onsubmit_prev = form.onsubmit;
			form.onsubmit = function(event){
				this.visible_fields = '';
				this.visible_fields_count = this.elements.length;
				for(var j = 0; j < this.elements.length; j++){
					var elem = this.elements[j];
					if( getComputedStyle(elem).display    == "none" ||
						getComputedStyle(elem).visibility == "hidden" ||
						getComputedStyle(elem).width      == "0" ||
						getComputedStyle(elem).heigth     == "0" ||
						getComputedStyle(elem).opacity    == "0" ||
						elem.getAttribute("type")         == "hidden" ||
						elem.getAttribute("type")         == "submit"
					){
						this.visible_fields_count--;
					}else{
						this.visible_fields += (this.visible_fields == "" ? "" : " ") + elem.getAttribute("name");
					}
				}
				ctSetCookie("apbct_visible_fields", this.visible_fields);
				ctSetCookie("apbct_visible_fields_count", this.visible_fields_count);
				if(this.onsubmit_prev instanceof Function){
					this.onsubmit_prev.call(this, event);
				}
			}
		}
	}, 1000);
};

//Writing first key press timestamp
var ctFunctionFirstKey = function(event){
	var KeyTimestamp = Math.floor(new Date().getTime()/1000);
	ctSetCookie("apbct_fkp_timestamp", KeyTimestamp);
	ctKeyStopStopListening();
};

//Reading interval
var ctMouseReadInterval = setInterval(function(){
	ctMouseEventTimerFlag = true;
}, 150);
	
//Writting interval
var ctMouseWriteDataInterval = setInterval(function(){
	ctSetCookie("apbct_pointer_data", JSON.stringify(ctMouseData));
}, 1200);

//Logging mouse position each 150 ms
var ctFunctionMouseMove = function(event){
	if(ctMouseEventTimerFlag == true){
		
		ctMouseData.push([
			Math.round(event.pageY),
			Math.round(event.pageX),
			Math.round(new Date().getTime() - ctTimeMs)
		]);
		
		ctMouseDataCounter++;
		ctMouseEventTimerFlag = false;
		if(ctMouseDataCounter >= 100){
			ctMouseStopData();
		}
	}
};

//Stop mouse observing function
function ctMouseStopData(){
	if(typeof window.addEventListener == "function"){
		window.removeEventListener("mousemove", ctFunctionMouseMove);
	}else{
		window.detachEvent("onmousemove", ctFunctionMouseMove);
	}
	clearInterval(ctMouseReadInterval);
	clearInterval(ctMouseWriteDataInterval);				
}

//Stop key listening function
function ctKeyStopStopListening(){
	if(typeof window.addEventListener == "function"){
		window.removeEventListener("mousedown", ctFunctionFirstKey);
		window.removeEventListener("keydown", ctFunctionFirstKey);
	}else{
		window.detachEvent("mousedown", ctFunctionFirstKey);
		window.detachEvent("keydown", ctFunctionFirstKey);
	}
}

if(typeof window.addEventListener == "function"){
	document.addEventListener("DOMContentLoaded", ctStart);
	window.addEventListener("mousemove", ctFunctionMouseMove);
	window.addEventListener("mousedown", ctFunctionFirstKey);
	window.addEventListener("keydown", ctFunctionFirstKey);
}else{
	document.attachEvent("DOMContentLoaded", ctStart);
	window.attachEvent("onmousemove", ctFunctionMouseMove);
	window.attachEvent("mousedown", ctFunctionFirstKey);
	window.attachEvent("keydown", ctFunctionFirstKey);
}