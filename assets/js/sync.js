( () => {

	const setupSyncActions = () => {

		const syncPostForm = document.getElementById( 'sync-post-form' );
		if ( ! syncPostForm ) {
			return;
		}

		syncPostForm.addEventListener( 'click', e => {
			const self = e.target.closest('[data-action]');
			if ( ! self ) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			const action = self.dataset.action;
			if ( ! action ) {
				return;
			}
			document.dispatchEvent( new Event( 'content-sync|' + action ) );
		} );

		const downloadPost = e => {
			const formData = new FormData( syncPostForm );

			const fetchURL = new URL( syncPostForm.dataset.restSource );
			fetchURL.searchParams.set( 'post', formData.get( 'post_id_name' ) );

			fetch( fetchURL, {
				method: 'GET',
				headers: {
					'Authorization': 'Bearer ' + syncPostForm.dataset.restAuth
				}
			} ).then( response => {
				if ( ! response.ok ) {
					throw new Error( response.statusText );
				}
				return response.json();
			} ).then( data => {
				window.sessionStorage.setItem( 'rs_content_buffer', JSON.stringify( data ) );
				return stageImport( syncPostForm, data );
			} ).catch( error => {
				console.error( error );
				// display error on form
				syncPostForm.classList.add( 'error' );
				const newError = syncPostForm.querySelector( '.content-sync__error-message' ) || document.createElement( 'div' );
				newError.classList.add( 'content-sync__error-message' );
				newError.innerText = error.message;
				syncPostForm.appendChild( newError );
			} )
		}

		const stageImport = ( settings, content ) => {
			return new Promise( ( resolve, reject ) => {
				const fetchURL = new URL( syncPostForm.dataset.restLocal );
				const body = settings;
				body.content = content;
				fetch( fetchURL, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-WP-Nonce': syncPostForm.dataset.restNonce
					},
					body: ( new URLSearchParams( body ) ).toString()
				} ).then( response => {
					if ( ! response.ok ) {
						throw new Error( response.statusText );
					}
					return response.json();
				} ).then( data => {
					console.log( data );

					resolve();
				} ).catch( error => {
					console.error( error );
					reject( error );
				} );
			} );
		}

		document.addEventListener( 'content-sync|download', downloadPost );

	}
	setupSyncActions();

} )();