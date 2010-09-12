function ChangeTo(to) {
	if(to == "text") {
		$('#admincommentlinks').hide();
		$('#admincomment').show();
		resize('admincomment');
		var buttons = document.getElementsByName('admincommentbutton');
		for(var i = 0; i < buttons.length; i++) {
			buttons[i].setAttribute('onclick',"ChangeTo('links'); return false;");
		}
	} else if(to == "links") {
		ajax.post("ajax.php?action=preview","form", function(response){
			$('#admincommentlinks').raw().innerHTML = response;
			$('#admincomment').hide();
			$('#admincommentlinks').show();
			var buttons = document.getElementsByName('admincommentbutton');
			for(var i = 0; i < buttons.length; i++) {
				buttons[i].setAttribute('onclick',"ChangeTo('text'); return false;");
			}
		})
	}
}
