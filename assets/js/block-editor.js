/**
 * Block editor sidebar panel, rewrite notice, pre-publish warning, and redirect.
 *
 * @package Oli_Duplicate_Post
 */
/* global wp, oliDpBlockEditor */
( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.element || ! wp.components || ! config ) {
		return;
	}

	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var PluginMoreMenuItem = wp.editPost.PluginMoreMenuItem;
	var PluginPrePublishPanel = wp.editPost.PluginPrePublishPanel;
	var PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var Button = wp.components.Button;
	var registerPlugin = wp.plugins.registerPlugin;

	var cloneUrl = config.cloneUrl || '';
	var newDraftUrl = config.newDraftUrl || '';
	var rewriteUrl = config.rewriteUrl || '';
	var isRewrite = !! config.isRewrite;
	var originalEditUrl = config.originalEditUrl || '';
	var compareUrl = config.compareUrl || '';
	var i18n = config.i18n || {};

	var OliDPPanel = function () {
		return createElement(
			PluginDocumentSettingPanel,
			{
				name: 'oli-duplicate-post-panel',
				title: i18n.duplicatePost || '',
				className: 'oli-duplicate-post-panel',
			},
			createElement(
				'div',
				{ className: 'oli-dp-block-panel' },
				createElement(
					Button,
					{
						href: cloneUrl,
						variant: 'secondary',
						className: 'oli-dp-block-button',
					},
					i18n.clone || ''
				),
				createElement(
					Button,
					{
						href: newDraftUrl,
						variant: 'secondary',
						className: 'oli-dp-block-button',
					},
					i18n.newDraft || ''
				),
				rewriteUrl
					? createElement(
							Button,
							{
								href: rewriteUrl,
								variant: 'secondary',
								className: 'oli-dp-block-button',
							},
							i18n.rewriteRepublish || ''
					  )
					: null
			)
		);
	};

	var OliDPRewriteSidebar = function () {
		if ( ! isRewrite ) {
			return null;
		}

		return createElement(
			PluginDocumentSettingPanel,
			{
				name: 'oli-dp-republish-schedule',
				title: i18n.republishSchedule || '',
				className: 'oli-dp-republish-schedule-panel',
			},
			createElement( 'p', null, i18n.scheduleHelp || '' )
		);
	};

	var OliDPStatusInfo = function () {
		if ( ! isRewrite ) {
			return null;
		}

		return createElement(
			PluginPostStatusInfo,
			{ className: 'oli-dp-rewrite-status' },
			i18n.rewriteCopyLabel || ''
		);
	};

	var OliDPPrePublish = function () {
		if ( ! isRewrite || ! PluginPrePublishPanel ) {
			return null;
		}

		return createElement(
			PluginPrePublishPanel,
			{
				name: 'oli-dp-rewrite-warning',
				title: i18n.republishWarningTitle || '',
				className: 'oli-dp-prepublish-panel',
				initialOpen: true,
			},
			createElement( 'p', null, i18n.prePublishWarning || '' )
		);
	};

	var OliDPToolbarItems = function () {
		return createElement(
			Fragment,
			null,
			createElement(
				PluginMoreMenuItem,
				{
					icon: 'admin-page',
					onClick: function () {
						window.location.href = cloneUrl;
					},
				},
				i18n.clone || ''
			),
			createElement(
				PluginMoreMenuItem,
				{
					icon: 'edit',
					onClick: function () {
						window.location.href = newDraftUrl;
					},
				},
				i18n.copyToNewDraft || ''
			),
			rewriteUrl
				? createElement(
						PluginMoreMenuItem,
						{
							icon: 'update',
							onClick: function () {
								window.location.href = rewriteUrl;
							},
						},
						i18n.rewriteRepublish || ''
				  )
				: null
		);
	};

	registerPlugin( 'oli-duplicate-post', {
		render: function () {
			return createElement(
				Fragment,
				null,
				createElement( OliDPRewriteSidebar ),
				createElement( OliDPPanel ),
				createElement( OliDPStatusInfo ),
				createElement( OliDPPrePublish ),
				createElement( OliDPToolbarItems )
			);
		},
		icon: null,
	} );

	if ( isRewrite && wp.data && wp.data.dispatch ) {
		var notices = wp.data.dispatch( 'core/notices' );
		if ( notices && notices.createWarningNotice ) {
			var actions = [];
			if ( originalEditUrl ) {
				actions.push( {
					label: i18n.viewOriginal || '',
					url: originalEditUrl,
				} );
			}
			if ( compareUrl ) {
				actions.push( {
					label: i18n.compare || '',
					url: compareUrl,
				} );
			}

			notices.createWarningNotice( i18n.rewriteNotice || '', {
				id: 'oli-dp-rewrite-notice',
				isDismissible: false,
				actions: actions,
			} );
		}
	}

	if ( isRewrite && wp.hooks && wp.hooks.addFilter ) {
		var republishLabel = i18n.republish || 'Republish';
		var relabelPublish = function ( translation, text ) {
			if ( 'Publish' === text || 'Publish…' === text || 'Publish...' === text ) {
				return republishLabel;
			}
			return translation;
		};

		wp.hooks.addFilter( 'i18n.gettext', 'oli-duplicate-post/republish-label', relabelPublish );
		wp.hooks.addFilter(
			'i18n.gettext_with_context',
			'oli-duplicate-post/republish-label-context',
			relabelPublish
		);
	}

	if ( isRewrite && originalEditUrl && wp.data && wp.data.subscribe && wp.data.select ) {
		var wasSaving = false;
		var statusWhileSaving = '';
		wp.data.subscribe( function () {
			var editor = wp.data.select( 'core/editor' );
			if ( ! editor || ! editor.isSavingPost || ! editor.didPostSaveRequestSucceed ) {
				return;
			}

			var isSaving = editor.isSavingPost();
			var didSucceed = editor.didPostSaveRequestSucceed();
			var isAutosaving = editor.isAutosavingPost ? editor.isAutosavingPost() : false;
			var status = editor.getEditedPostAttribute
				? editor.getEditedPostAttribute( 'status' )
				: '';

			if ( isSaving ) {
				statusWhileSaving = status;
			}

			if (
				wasSaving &&
				! isSaving &&
				didSucceed &&
				! isAutosaving &&
				( 'publish' === statusWhileSaving || 'future' === statusWhileSaving )
			) {
				window.location.href = originalEditUrl;
			}

			wasSaving = isSaving;
		} );
	}
}( window.wp, window.oliDpBlockEditor ) );
