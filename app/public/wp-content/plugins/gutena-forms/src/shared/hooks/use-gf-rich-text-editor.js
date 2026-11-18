import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import {
	EMPTY_ACTIVE_FORMATS,
	cloneSelectionRangeInEditor,
	execCommandOnEditor,
	readActiveFormats,
	selectionIsWithinEditor,
} from '../utils/gf-rich-text-editor';

/**
 * Visual-mode rich text behavior scoped per contenteditable instance.
 */
export const useGfRichTextEditor = ( {
	editorRef,
	value,
	onChange,
	viewMode,
} ) => {
	const [ activeFormats, setActiveFormats ] = useState( EMPTY_ACTIVE_FORMATS );
	const lastSyncedValue = useRef( null );
	const savedRangeRef = useRef( null );

	const rememberSelection = useCallback( () => {
		const editor = editorRef.current;
		if ( ! editor ) {
			return;
		}

		const saved = cloneSelectionRangeInEditor( editor );
		if ( saved ) {
			savedRangeRef.current = saved;
		}
	}, [ editorRef ] );

	const checkActiveFormats = useCallback( () => {
		const editor = editorRef.current;
		if ( ! editor ) {
			return;
		}

		const selection = window.getSelection();
		if ( ! selectionIsWithinEditor( editor, selection ) ) {
			setActiveFormats( EMPTY_ACTIVE_FORMATS );
			return;
		}

		const range = selection.getRangeAt( 0 );
		savedRangeRef.current = range.cloneRange();
		setActiveFormats( readActiveFormats() );
	}, [ editorRef ] );

	const publishEditorHtml = useCallback( () => {
		const editor = editorRef.current;
		if ( ! editor ) {
			return;
		}

		const html = editor.innerHTML;
		lastSyncedValue.current = html;
		onChange( html );
	}, [ editorRef, onChange ] );

	const handleEditorInput = useCallback( () => {
		publishEditorHtml();
		checkActiveFormats();
	}, [ publishEditorHtml, checkActiveFormats ] );

	const handleEditorKeyDown = useCallback(
		( event ) => {
			if ( 'Tab' !== event.key ) {
				return;
			}

			event.preventDefault();
			const editor = editorRef.current;
			if ( ! editor ) {
				return;
			}

			const range =
				savedRangeRef.current?.cloneRange() ||
				cloneSelectionRangeInEditor( editor );

			execCommandOnEditor( editor, range, 'insertHTML', '&emsp;' );
			rememberSelection();
			publishEditorHtml();
		},
		[ editorRef, publishEditorHtml, rememberSelection ]
	);

	const handleFormat = useCallback(
		( command, valueArg ) => {
			const editor = editorRef.current;
			if ( ! editor ) {
				return;
			}

			const range =
				savedRangeRef.current?.cloneRange() ||
				cloneSelectionRangeInEditor( editor );

			execCommandOnEditor( editor, range, command, valueArg );
			rememberSelection();
			checkActiveFormats();
			publishEditorHtml();
		},
		[ editorRef, checkActiveFormats, publishEditorHtml, rememberSelection ]
	);

	useEffect( () => {
		if ( 'visual' !== viewMode || ! editorRef.current ) {
			if ( 'code' === viewMode ) {
				lastSyncedValue.current = null;
				savedRangeRef.current = null;
			}
			return undefined;
		}

		const editor = editorRef.current;

		if ( lastSyncedValue.current !== value ) {
			editor.innerHTML = value || '';
			lastSyncedValue.current = value || '';
		}

		const handleInteraction = () => {
			rememberSelection();
			checkActiveFormats();
		};

		const handleSelectionChange = () => {
			checkActiveFormats();
		};

		editor.addEventListener( 'keyup', handleInteraction );
		editor.addEventListener( 'mouseup', handleInteraction );
		document.addEventListener( 'selectionchange', handleSelectionChange );

		return () => {
			editor.removeEventListener( 'keyup', handleInteraction );
			editor.removeEventListener( 'mouseup', handleInteraction );
			document.removeEventListener( 'selectionchange', handleSelectionChange );
		};
	}, [ viewMode, value, checkActiveFormats, rememberSelection, editorRef ] );

	const resetLastSyncedValue = useCallback( () => {
		lastSyncedValue.current = null;
	}, [] );

	return {
		activeFormats,
		handleEditorInput,
		handleEditorKeyDown,
		handleFormat,
		rememberSelection,
		resetLastSyncedValue,
		savedRangeRef,
		syncEditorValue: publishEditorHtml,
	};
};
