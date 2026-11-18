/**
 * Focus Form Confirmation sidebar panel when a message block preview is activated.
 * Safe across separate block bundles (no React context).
 */

import { dispatch, select } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { getInnerBlocksbyNameAttr, gfIsEmpty } from './utils/helper';

const INTERFACE_STORE = 'core/interface';
const PANEL_SELECTOR = '.gutena-forms-form-confirmation-panel';
const HIGHLIGHT_CLASS = 'is-panel-focused';
const HIGHLIGHT_MS = 2000;

export const getParentFormClientId = ( messageBlockClientId ) => {
	const parents = select( blockEditorStore ).getBlockParentsByBlockName(
		messageBlockClientId,
		'gutena/forms',
		true
	);

	if ( ! parents?.length ) {
		return null;
	}

	return parents[ 0 ];
};

/**
 * Form Confirmation inspector panel is omitted when the form embeds existing-forms.
 *
 * @param {string} parentFormClientId Parent form client id.
 * @return {boolean}
 */
export const parentFormShowsConfirmationPanel = ( parentFormClientId ) => {
	if ( ! parentFormClientId ) {
		return false;
	}

	const blocks = select( blockEditorStore ).getBlocks( parentFormClientId );

	if ( gfIsEmpty( blocks ) || 0 === blocks.length ) {
		return true;
	}

	const existingFormsBlocks = getInnerBlocksbyNameAttr(
		blocks,
		'gutena/existing-forms'
	);

	return (
		gfIsEmpty( existingFormsBlocks ) ||
		0 === existingFormsBlocks.length
	);
};

const openBlockSidebar = () => {
	try {
		dispatch( INTERFACE_STORE ).enableComplementaryArea(
			'core',
			'edit-post/block'
		);
	} catch ( error ) {
		try {
			dispatch( INTERFACE_STORE ).enableComplementaryArea(
				'core',
				'edit-site'
			);
		} catch ( siteError ) {
			// Sidebar unavailable.
		}
	}
};

const expandPanelIfCollapsed = ( panel ) => {
	const toggle = panel.querySelector(
		'button.components-panel__body-toggle'
	);

	if (
		toggle &&
		'false' === toggle.getAttribute( 'aria-expanded' )
	) {
		toggle.click();
	}
};

const scrollAndHighlightPanel = () => {
	requestAnimationFrame( () => {
		requestAnimationFrame( () => {
			const sidebar = document.querySelector(
				'.interface-interface-skeleton__sidebar'
			);
			const panel =
				sidebar?.querySelector( PANEL_SELECTOR ) ||
				document.querySelector( PANEL_SELECTOR );

			if ( ! panel ) {
				return;
			}

			expandPanelIfCollapsed( panel );
			panel.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
			panel.classList.add( HIGHLIGHT_CLASS );
			window.setTimeout( () => {
				panel.classList.remove( HIGHLIGHT_CLASS );
			}, HIGHLIGHT_MS );
		} );
	} );
};

/**
 * Select parent Form, open block sidebar, scroll to Form Confirmation panel.
 * No-op when parent is missing or form uses embedded existing-forms.
 *
 * @param {string} messageBlockClientId Message block client id.
 */
export const focusFormConfirmationFromMessageBlock = (
	messageBlockClientId
) => {
	const parentId = getParentFormClientId( messageBlockClientId );

	if ( ! parentId || ! parentFormShowsConfirmationPanel( parentId ) ) {
		return;
	}

	dispatch( blockEditorStore ).selectBlock( parentId );
	openBlockSidebar();
	scrollAndHighlightPanel();
};
