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
			document.dispatchEvent( new CustomEvent( 'content-sync|' + action, {
				detail: {
					form:   syncPostForm,
					button: self
				}
			} ) );
		} );

		const downloadPost = e => {
			const formData = new URLSearchParams( new FormData( syncPostForm ) );

			const fetchURL = new URL( syncPostForm.dataset.restSource );
			fetchURL.searchParams.set( 'post', formData.get( 'post_id_name' ) );

			setImportStatus( 'Downloading content from source server...' );

			let collectedContent = null;

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
			} ).then( content => {
				if ( ! content.success ) {
					throw new Error( content.message );
				}
				collectedContent = content;
				window.sessionStorage.setItem( 'rs_content_buffer', JSON.stringify( content ) );
				formData.set( 'content', JSON.stringify( content ) );
				setImportStatus( 'Importing media from source server' );
				return stageImages( content.media );
			} ).then( ( importResponses ) => {
				setImportStatus( 'Saving content as new post draft' );
				formData.set( 'images', JSON.stringify( importResponses ) );
				return stageContent( formData, importResponses );
			} ).then( ( previewData ) => {
				setImportStatus( 'Import complete' );
				importReady( previewData );
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

		const importReady = ( previewData ) => {
			
			syncPostForm.classList.add( 'is-state-done' );
			syncPostForm.querySelectorAll('[data-action]').forEach( el => {
				el.disabled = false;
			} );
			syncPostForm.querySelector('[data-action="preview"]').dataset.previewUrl = previewData.preview_url;
			syncPostForm.querySelector('[data-action="edit"]').dataset.editUrl = previewData.edit_url;

		}

		const setImportStatus = ( status ) => {
			const statusText = document.querySelector( '#cs-status-text' );
			if ( statusText ) {
				statusText.innerText = 'Status: ' + status;
			}
			statusText.parentElement.classList.add( 'is-state-running' );
		}

		const stageImages = ( media ) => {

			const previewImages = document.querySelector( '#cs-images' );
			const imageUploadQueue = [];

			for( const mediaId in media ) {
				const mediadata = media[ mediaId ];
				const newImg = new Image( mediadata.full[1], mediadata.full[2] );
				newImg.src = mediadata.full[0];
				const newFigure = document.createElement( 'figure' );
				newFigure.appendChild( newImg );
				newFigure.classList.add('is-state-waiting');

				previewImages.appendChild( newFigure );

				imageUploadQueue.push( addImageToUploadQueue( newImg, {
					src:  mediadata.full[0],
					data: mediadata,
				 } ) );
			}

			return Promise.all( imageUploadQueue );

		};

		const stageContent = ( content ) => {
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
					console.log( 'preview post created', previewData );
					resolve( previewData );
				} ).catch( error => {
					console.error( error );
					reject( error );
				} );
			} );
		}


		let activeImageImports  = 0;
		const maxImageDownloads = 5;


		const addImageToUploadQueue = ( mediaDom, props ) => {

			const { src, data } = props;

			const mediaWrapper = mediaDom.parentElement;

			const promiseFunction = ( resolve, reject ) => {

				if ( activeImageImports >= maxImageDownloads ) {
					setTimeout( () => {
						promiseFunction( resolve, reject );
					}, 500 );
					return;
				}

				activeImageImports += 1;
				console.log( 'now active imports plus 1, ', activeImageImports );
				const fetchURL = syncPostForm.dataset.restSource + '/image/' + data.post.ID;
				mediaWrapper.classList.remove( 'is-state-waiting' );
				mediaWrapper.classList.add( 'is-state-downloading' );
				fetch( fetchURL, {
					method: 'GET',
					headers: {
						'Authorization': 'Bearer ' + syncPostForm.dataset.restAuth
					}
				} ).then( response => {
					if ( ! response.ok ) {
						throw new Error( response.statusText );
					}
					//contentType = response.headers.get('Content-type');
					return response.json();
				} ).then( imageJson => {
					mediaWrapper.classList.remove( 'is-state-downloading' );
					mediaWrapper.classList.add( 'is-state-uploading' );
					return uploadImageThumbnails( imageJson.media, data );
				} ).then( response => {
					mediaWrapper.classList.remove( 'is-state-uploading' );
					mediaWrapper.classList.add( 'is-state-done' );
					if ( response.status === 'ok' ) {
						const returnData = {
							uploaded:   response.uploaded,
							sourcePost: data.post.ID,
							localPost:  response.attachmentId
						}
						if ( data.match ) {
							returnData.match = data.match;
							for( let size in response.uploaded ) {
								if ( response.uploaded[size].relpath === data.path ) {
									returnData.replaceWith = response.uploaded[size].absurl;
								}
							}
						}
						resolve( returnData );
					} else {
						reject( response.message );
					}
				} ).catch( error => {
					mediaWrapper.classList.add('is-state-error');
					reject( error );
				} ).finally( () => {
					activeImageImports -= 1;
					console.log( 'now active imports less 1, ', activeImageImports );
					document.dispatchEvent( new Event( 'content-sync|image-import-done' ) );
				} );

			};

			return new Promise( promiseFunction );

		}

		const uploadImageThumbnails = ( mediaData, postData ) => {
			return new Promise( ( resolve, reject ) => {
				const uploadURL = new URL( syncPostForm.dataset.restLocal + '/image' );
				fetch( uploadURL, {
					method: 'POST',
					headers: {
						'Accept': 'application/json',
						'Content-Type': 'application/json',
						'X-WP-Nonce': syncPostForm.dataset.restNonce
					},
					body: JSON.stringify( {
						media: mediaData,
						post: postData,
						params: {
							attachmentParent: syncPostForm.dataset.postId,
							replaceExisting: syncPostForm.querySelector('input[name="replace_media"]')?.checked ?? false,
						}
					} )
				} ).then( response => {
					if ( ! response.ok ) {
						throw new Error( response.statusText );
					}
					return response.json();
				} ).then( response => {
					// should be an array of media file urls
					resolve( response );
				} ).catch( error => {
					reject( error );
				} );
			} );
		}

		const previewPost = ( e ) => {

			console.log( e );

			window.open( e.detail.button.dataset.previewUrl, '_blank').focus();

		};

		const editPost = ( e ) => {

			console.log( e );

			window.open( e.detail.button.dataset.editUrl, '_blank').focus();

		};

		document.addEventListener( 'content-sync|download', downloadPost );
		document.addEventListener( 'content-sync|preview', previewPost );
		document.addEventListener( 'content-sync|edit', editPost );

	}
	setupSyncActions();

} )();