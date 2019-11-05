$(function() {

	 var reloadPage = function(){
		  $('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
		 window.location.reload();
	 }

	$('.external-modules-disable-button').click(function (event) {	
		var button = $(event.target);
		var row = button.closest('tr');
		var module = row.data('module');
		var version = row.data('version');
		$('#external-modules-disable-confirm-modal').modal('show');
		$('#external-modules-disable-confirm-module-name').html(module);
		$('#external-modules-disable-confirm-module-version').html(version);
	});
		
	$('#external-modules-disable-button-confirmed').click(function (event) {
		var button = $(event.target);
		button.attr('disabled', true);
		var module = $('#external-modules-disable-confirm-module-name').text();
		$.post('ajax/disable-module.php?pid=' + ExternalModules.PID, { module: module }, function(data){
		   if (data == 'success') {
				reloadPage();
		   }
		   else {
				//= An error occurred while enabling the module:
				var message = ExternalModules.$lang.tt('em_manage_69')+' '+data;
				console.log('AJAX request error while enabling a module:', data); // The intent is to have the data object logged to the console, and not the message?
				alert(message);
		   }
		});
	});
});
