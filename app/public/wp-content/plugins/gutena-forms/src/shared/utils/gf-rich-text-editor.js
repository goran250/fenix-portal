export const EMPTY_ACTIVE_FORMATS = {
	bold: false,
	italic: false,
	underline: false,
	strikeThrough: false,
	justifyLeft: false,
	justifyCenter: false,
	justifyRight: false,
	insertOrderedList: false,
	insertUnorderedList: false,
};

export const selectionIsWithinEditor = ( editor, selection = window.getSelection() ) => {
	if ( ! editor || ! selection || selection.rangeCount === 0 ) {
		return false;
	}

	const range = selection.getRangeAt( 0 );
	let node = range.commonAncestorContainer;

	if ( node.nodeType === Node.TEXT_NODE ) {
		node = node.parentNode;
	}

	return editor.contains( node );
};

export const cloneSelectionRangeInEditor = ( editor ) => {
	const selection = window.getSelection();
	if ( ! selectionIsWithinEditor( editor, selection ) ) {
		return null;
	}

	return selection.getRangeAt( 0 ).cloneRange();
};

export const restoreSelectionRange = ( editor, range ) => {
	if ( ! editor || ! range ) {
		return false;
	}

	editor.focus();
	const selection = window.getSelection();
	selection.removeAllRanges();
	selection.addRange( range );
	return true;
};

export const execCommandOnEditor = ( editor, range, command, value = null ) => {
	if ( ! editor ) {
		return false;
	}

	if ( range ) {
		restoreSelectionRange( editor, range );
	} else {
		editor.focus();
	}

	return document.execCommand( command, false, value );
};

export const readActiveFormats = () => ( {
	bold: document.queryCommandState( 'bold' ),
	italic: document.queryCommandState( 'italic' ),
	underline: document.queryCommandState( 'underline' ),
	strikeThrough: document.queryCommandState( 'strikeThrough' ),
	justifyLeft: document.queryCommandState( 'justifyLeft' ),
	justifyCenter: document.queryCommandState( 'justifyCenter' ),
	justifyRight: document.queryCommandState( 'justifyRight' ),
	insertOrderedList: document.queryCommandState( 'insertOrderedList' ),
	insertUnorderedList: document.queryCommandState( 'insertUnorderedList' ),
} );
