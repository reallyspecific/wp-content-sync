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
			const formData = new URLSearchParams( new FormData( syncPostForm ) );

			const fetchURL = new URL( syncPostForm.dataset.restSource );
			fetchURL.searchParams.set( 'post', formData.get( 'post_id_name' ) );

			setImportStatus( 'Downloading content from source server...' );

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
				if ( ! data.success ) {
					throw new Error( data.message );
				}
				window.sessionStorage.setItem( 'rs_content_buffer', JSON.stringify( data ) );
				formData.set( 'content', JSON.stringify( data ) );

				setImportStatus( 'Saving content as new post draft' );
				return stageImport( formData, data );
			} ).then( ( previewData ) => {
				// todo: honor import_media setting
				

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

		const stageImport = ( content, postdata ) => {
			console.log( 'creating temporary post', content );
			
			return new Promise( ( resolve, reject ) => {
				const fetchURL = new URL( syncPostForm.dataset.restLocal );
				fetch( fetchURL, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-WP-Nonce': syncPostForm.dataset.restNonce
					},
					body: content
				} ).then( response => {
					if ( ! response.ok ) {
						throw new Error( response.statusText );
					}
					return response.json();
				} ).then( previewData => {
					console.log( 'preview post created, ready to import media', previewData );
					setImportStatus( 'Importing media from source server' );

					const previewImages = document.querySelector( '#cs-images' );

					for( const mediaId in postdata.media ) {
						const mediadata = postdata.media[ mediaId ];
						const newImg = document.createElement( 'img' );
						const newFigure = document.createElement( 'figure' );
						newImg.src = mediadata.full[0];
						newFigure.classList.add('is-state-loading');

						newFigure.appendChild( newImg );
						previewImages.appendChild( newFigure );
					}

					resolve( previewData );
				} ).catch( error => {
					console.error( error );
					reject( error );
				} );
			} );
		}

		const setImportStatus = ( status ) => {
			const statusText = document.querySelector( '#cs-status-text' );
			if ( statusText ) {
				statusText.innerText = 'Status: ' + status;
			}
			statusText.parentElement.classList.add( 'is-state-running' );
		}

		document.addEventListener( 'content-sync|download', downloadPost );

	}
	setupSyncActions();

} )();