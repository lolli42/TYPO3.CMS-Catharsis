function passwordChanged() {
	var strength = document.getElementById('password');
	var strongRegex = new RegExp('^(?=.{8,})(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*\\W).*$', 'g');
	var mediumRegex = new RegExp('^(?=.{7,})(((?=.*[A-Z])(?=.*[a-z]))|((?=.*[A-Z])(?=.*[0-9]))|((?=.*[a-z])(?=.*[0-9]))).*$', 'g');
	var enoughRegex = new RegExp('(?=.{6,}).*', 'g');
	var pwd = document.getElementById('password');
	if (pwd.value.length==0) {
		strength.setAttribute('style', 'background-color:#FBB19B; border:1px solid #DC4C42');
	} else if (false == enoughRegex.test(pwd.value)) {
		strength.setAttribute('style', 'background-color:#FBB19B; border:1px solid #DC4C42');
	} else if (strongRegex.test(pwd.value)) {
		strength.setAttribute('style', 'background-color:#CDEACA; border:1px solid #58B548');
	} else if (mediumRegex.test(pwd.value)) {
		strength.setAttribute('style', 'background-color:#FBFFB3; border:1px solid #C4B70D');
	} else {
		strength.setAttribute('style', 'background-color:#FBFFB3; border:1px solid #C4B70D');
	}
}