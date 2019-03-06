$(function(){
	// first show disabledModal and then show enableModal
	var disabledModal = $('#external-modules-disabled-modal');
	var enableModal = $('#external-modules-enable-modal');

	var reloadThisPage = function(){
		$('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
		window.location.reload();
	}

	disabledModal.find('.disable-button').click(function(event){
		var row = $(event.target).closest('tr');
		var title = row.find('td:eq(0)').text().trim();
		var prefix = row.data('module');
		var version = row.find('[name="version"]').val();		
		simpleDialog("Do you wish to delete the module \"<b>"+title+"</b>\" (<b>"+prefix+"_"+version+"</b>)? "
			+"Doing so will permanently remove the module's directory from the REDCap server.","DELETE MODULE?",null,null,null,"Cancel",function(){
				showProgress(1);
				$.post('ajax/delete-module.php', { module_dir: prefix+'_'+version },function(data){
					showProgress(0,0);
					if (data == '1') {
						simpleDialog("An error occurred because the External Module directory could not be found on the REDCap web server.","ERROR");
					} else if (data == '0') {
						simpleDialog("An error occurred because the External Module directory could not be deleted from the REDCap web server.","ERROR");
					} else {
						$('#external-modules-disabled-modal').hide();
						simpleDialog(data,"SUCCESS",null,null,function(){
							window.location.reload();
						},"Close");
					}
				});
			},"Delete module");
		return false;
	});

	disabledModal.find('.enable-button').click(function(event){
		// Prevent form submission
		event.preventDefault();

		disabledModal.hide();

		var row = $(event.target).closest('tr');
		var prefix = row.data('module');
		var version = row.find('[name="version"]').val();

		var enableErrorDiv = $('#external-modules-enable-modal-error');
		enableErrorDiv.html(''); // Clear out any previous errors

		var enableButton = enableModal.find('.enable-button');

		var enableModule = function(){
			var url = 'ajax/enable-module.php'
			if (pid) {
				url += '?pid=' + pid
			}

			var showErrorAlert = function(message){
				var message = 'An error occurred while enabling the module: ' + message;
				console.log('AJAX Request Error:', message);
				alert(message);
				disabledModal.modal('hide');
				enableModal.modal('hide');
			}

			$.post(url, {prefix: prefix, version: version}, function(data){
				var jsonAjax
				try{
					jsonAjax = jQuery.parseJSON(data);
				}
				catch(e){
					showErrorAlert(data)
					return
				}

				if (typeof jsonAjax != 'object') {
					showErrorAlert(data)
					return
				}

				var errorMessage = jsonAjax['error_message']
				if (errorMessage) {
					if(pid){
						showErrorAlert(errorMessage)
					}
					else{
						enableErrorDiv.show();
						enableErrorDiv.html(errorMessage);
						$('.close-button').attr('disabled', false);
						enableButton.hide();
					}
				}else if (jsonAjax['message'] == 'success') {
					reloadThisPage();
					disabledModal.modal('hide');
					enableModal.modal('hide');
				}
			});
		}

		if (!pid) {
			enableButton.html('Enable');
			enableModal.find('button').attr('disabled', false);

			var list = enableModal.find('.modal-body ul');
			list.html('');
			
			var permissionCount = 0;
			disabledModules[prefix][version].permissions.forEach(function(permission){
				if (permission != "") {
					list.append("<li>" + permission + "</li>");
					permissionCount++;
				}
			});
			if (permissionCount == 0) {
				list.append("<li><i>None (no permissions requested)</i></li>");
			}

			enableButton.off('click'); // disable any events attached from other modules
			enableButton.click(function(){
				enableButton.html('Enabling...');
				enableModal.find('button').attr('disabled', true);

				enableModule()
			});
			enableButton.show();
			enableModal.modal('show');
		} else {   // pid
			enableModule()
		}
	});

	if (enableModal) {
		enableModal.on('hide.bs.modal', function(){
			// We used to try to display the previous dialog again here, but it caused some odd edge cases related to multiple dialogs.
			// Simply reloading is cleaner.
			reloadThisPage();
		});
	}
});
