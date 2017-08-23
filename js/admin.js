jQuery( document ).ready( function( $ ) {
	var $addGlobalFollowerField = $('#add-global-follower-field');
	var $addGlobalFollowerButton = $('#add-global-follower-button');
	var $tableContainer = $('#global-follower-table-container');

	var nonce = $('#bpaf_nonce').val();
	var params = { 'nonce':nonce };
	var cache = {};

	$addGlobalFollowerField.autocomplete({
		autoFocus: true,
		minLength: 1,
		source: function(request, response) {
			var term = request.term;
			// Has the request been made before?
			if ( term in cache ) {
				response( cache[ term ] );
				return;
			}

			// Add the search term to the request
			params.term = request.term;
			// Remote Source
			$.ajax({
				url: ajaxurl + '?action=bpaf_suggest_global_follower&search=' + params.term,
				dataType: "json",
					data: jQuery.param(params),
					success: function(data){
						//Cache this response since it is expensive
						cache[ term ] = $.ui.autocomplete.filter(data, term);
						response(cache[ term ]);
						return;
				}
			});
		},
		select: function( event, ui ) {
			$addGlobalFollowerButton.attr('disabled', false);
			$addGlobalFollowerButton.focus();
			updateFieldTextColor();
		},
		search: function( event, ui ) {
			$addGlobalFollowerButton.attr('disabled', true);
			$addGlobalFollowerField.css( 'color', '#aaa' );
			updateFieldTextColor();
		}
	});

	function updateFieldTextColor() {
		var buttonTextColor = $addGlobalFollowerButton.css('color');
		$addGlobalFollowerField.css( 'color', buttonTextColor );
	}

	// Add a Global Follower
	$addGlobalFollowerButton.click( function(e) {
		var $self = $(this);
		var $parentTable = $('.wp-list-table'); // TODO: way too general
		var nonce = $('#bpaf_nonce').val();
		var params = { 'username':$addGlobalFollowerField.val(), 'nonce':nonce };

		// Send the contents of the existing post
		$.ajax({
			url: ajaxurl + '?action=bpaf_add_global_follower',
			type: 'POST',
			data: jQuery.param(params),
			beforeSend: function() {
				$('.spinner').show();
			},
			complete: function() {
				//$('.spinner').hide();
			},
			success: function(response) {
				$addGlobalFollowerButton.attr('disabled', true);
				$addGlobalFollowerField.css( 'color', '#aaa' );
				updateFieldTextColor();
				$addGlobalFollowerField.val('Search by Username');

				$('.spinner').hide();

				// Return the excerpt from the editor ???
				$('.bpaf-empty-table-row').remove();

				$tableContainer.html(response);
			}
		});
	});

	// Remove a Global Follower
	$tableContainer.on( 'click', '.trash', function(e) {
		e.preventDefault();
		var confirmDelete = confirm("Removing this user will delete ALL followers related to this user. 'Cancel' to stop, 'OK' to delete.");
		if( false === confirmDelete )
			return;

		var $self = $(this);
		var $parentTable = $('.wp-list-table'); // TODO: way too general
		var $parentTableRow = $self.parents('tr');
		var userID = $parentTableRow.find('.bpaf-user-id').val();
		var nonce = $('#bpaf_nonce').val();
		var params = { 'ID':userID, 'nonce':nonce };

		$.ajax({
			url: ajaxurl + '?action=bpaf_delete_global_follower',
			type: 'POST',
			data: jQuery.param(params),
			beforeSend: function() {
				$('.spinner').show();
			},
			complete: function() {
				//$('.spinner').hide();
			},
			success: function( response ) {
				$('.spinner').hide();
				$tableContainer.html(response);
			}
		});
	});

});
