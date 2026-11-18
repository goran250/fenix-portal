import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import GutenaFormsNotificationMergeTagPopover from './GutenaFormsNotificationMergeTagPopover';
import {
	BoldIcon,
	ItalicIcon,
	UnderlineIcon,
	DelIcon,
	AlignLeftIcon,
	AlignCenterIcon,
	AlignRightIcon,
	OrderedListIcon,
	UnorderedListIcon,
	LinkIcon,
} from './EditorIcons';
import { useGfRichTextEditor } from '../hooks/use-gf-rich-text-editor';
import {
	cloneSelectionRangeInEditor,
	restoreSelectionRange,
} from '../utils/gf-rich-text-editor';

const ToolbarButton = ( { onClick, isActive, title, children, disabled } ) => (
	<button
		type="button"
		className={ `gf-editor-toolbar__btn${ isActive ? ' is-active' : '' }` }
		onMouseDown={ ( e ) => {
			e.preventDefault();
			onClick();
		} }
		title={ title }
		disabled={ disabled }
	>
		{ children }
	</button>
);

const ToolbarSeparator = () => (
	<span className="gf-editor-toolbar__separator" />
);

const GutenaFormsNotificationMessageField = ( {
	id,
	label,
	value,
	onChange,
	onFocus,
	placeholder,
	genericTags = [],
	formTagItems = [],
	formTagList = [],
	showFormTagsButton = false,
	onRegisterInsert,
	disabled = false,
	formTagsButtonLabel = __( 'Form tags', 'gutena-forms' ),
	formTagsButtonVariant = 'form-tags',
} ) => {
	const [ viewMode, setViewMode ] = useState( 'visual' );
	const editorRef = useRef( null );

	const {
		activeFormats,
		handleEditorInput,
		handleEditorKeyDown,
		handleFormat,
		rememberSelection,
		resetLastSyncedValue,
		savedRangeRef,
		syncEditorValue,
	} = useGfRichTextEditor( {
		editorRef,
		value,
		onChange,
		viewMode,
	} );

	const insertAtCursor = useCallback(
		( text ) => {
			if ( disabled ) {
				return;
			}

			if ( 'code' === viewMode ) {
				const element = document.getElementById( `${ id }-code` );
				const currentValue = value || '';
				const start =
					element && typeof element.selectionStart === 'number'
						? element.selectionStart
						: currentValue.length;
				const end =
					element && typeof element.selectionEnd === 'number'
						? element.selectionEnd
						: start;
				const nextValue = `${ currentValue.slice(
					0,
					start
				) }${ text }${ currentValue.slice( end ) }`;
				onChange( nextValue );
				requestAnimationFrame( () => {
					if ( element ) {
						element.focus();
						const cursor = start + text.length;
						element.setSelectionRange( cursor, cursor );
					}
				} );
				return;
			}

			const editor = editorRef.current;
			if ( ! editor ) {
				return;
			}

			editor.focus();
			let range =
				savedRangeRef.current?.cloneRange() ||
				cloneSelectionRangeInEditor( editor );

			if ( ! range ) {
				range = document.createRange();
				range.selectNodeContents( editor );
				range.collapse( false );
			}

			restoreSelectionRange( editor, range );
			const selection = window.getSelection();
			const activeRange = selection.getRangeAt( 0 );
			activeRange.deleteContents();
			const textNode = document.createTextNode( text );
			activeRange.insertNode( textNode );
			activeRange.setStartAfter( textNode );
			activeRange.setEndAfter( textNode );
			selection.removeAllRanges();
			selection.addRange( activeRange );
			savedRangeRef.current = activeRange.cloneRange();

			syncEditorValue();
		},
		[ disabled, id, onChange, savedRangeRef, syncEditorValue, value, viewMode ]
	);

	useEffect( () => {
		if ( onRegisterInsert ) {
			onRegisterInsert( insertAtCursor );
		}
	}, [ insertAtCursor, onRegisterInsert ] );

	const handleLink = () => {
		const url = window.prompt( __( 'Enter URL:', 'gutena-forms' ) );
		if ( url ) {
			handleFormat( 'createLink', url );
		}
	};

	const switchToCode = () => {
		if ( 'code' === viewMode ) {
			return;
		}
		resetLastSyncedValue();
		setViewMode( 'code' );
	};

	const switchToVisual = () => {
		if ( 'visual' === viewMode ) {
			return;
		}
		resetLastSyncedValue();
		setViewMode( 'visual' );
	};

	const handleCodeChange = ( event ) => {
		onChange( event.target.value );
	};

	return (
		<div
			className={ `gutena-forms-notification-message-field${
				disabled ? ' is-disabled' : ''
			}` }
		>
			<div className="gutena-forms-notification-message-field__header">
				<label
					className="gutena-forms-notification-message-field__label"
					htmlFor={ id }
				>
					{ label }
				</label>

				<div className="gutena-forms-notification-message-field__toolbar">
					<button
						type="button"
						className={ `gutena-forms-notification-message-field__mode${
							'visual' === viewMode ? ' is-active' : ''
						}` }
						onClick={ switchToVisual }
						disabled={ disabled }
					>
						{ __( 'Visual', 'gutena-forms' ) }
					</button>
					<button
						type="button"
						className={ `gutena-forms-notification-message-field__mode${
							'code' === viewMode ? ' is-active' : ''
						}` }
						onClick={ switchToCode }
						disabled={ disabled }
					>
						{ __( 'Code', 'gutena-forms' ) }
					</button>
					{ genericTags.length > 0 && (
						<GutenaFormsNotificationMergeTagPopover
							tags={ genericTags }
							onInsert={ insertAtCursor }
							disabled={ disabled }
						/>
					) }
					{ showFormTagsButton &&
						( formTagItems.length > 0 || formTagList.length > 0 ) && (
						<GutenaFormsNotificationMergeTagPopover
							variant={ formTagsButtonVariant }
							tags={ formTagList }
							tagItems={ formTagItems }
							onInsert={ insertAtCursor }
							buttonLabel={ formTagsButtonLabel }
							disabled={ disabled }
						/>
					) }
				</div>
			</div>

			<div className="gutena-forms-notification-message-field__editor">
				{ 'visual' === viewMode && (
					<div className="gf-editor-toolbar">
						<span className="gf-editor-toolbar__format-label">
							{ __( 'Normal', 'gutena-forms' ) }
						</span>
						<ToolbarSeparator />
						<ToolbarButton
							onClick={ () => handleFormat( 'bold' ) }
							isActive={ activeFormats.bold }
							title={ __( 'Bold', 'gutena-forms' ) }
							disabled={ disabled }
						>
							<BoldIcon />
						</ToolbarButton>
						<ToolbarButton
							onClick={ () => handleFormat( 'italic' ) }
							isActive={ activeFormats.italic }
							title={ __( 'Italic', 'gutena-forms' ) }
							disabled={ disabled }
						>
							<ItalicIcon />
						</ToolbarButton>
						<ToolbarButton
							onClick={ () => handleFormat( 'underline' ) }
							isActive={ activeFormats.underline }
							title={ __( 'Underline', 'gutena-forms' ) }
							disabled={ disabled }
						>
							<UnderlineIcon />
						</ToolbarButton>
						<ToolbarButton
							onClick={ () => handleFormat( 'strikeThrough' ) }
							isActive={ activeFormats.strikeThrough }
							title={ __( 'Strikethrough', 'gutena-forms' ) }
							disabled={ disabled }
						>
							<DelIcon />
						</ToolbarButton>
						<ToolbarSeparator />
						<ToolbarButton
							onClick={ () => handleFormat( 'justifyLeft' ) }
							isActive={ activeFormats.justifyLeft }
							title={ __( 'Align left', 'gutena-forms' ) }
							disabled={ disabled }
						>
							<AlignLeftIcon />
						</ToolbarButton>
						<ToolbarButton
							onClick={ () => handleFormat( 'justifyCenter' ) }
							isActive={ activeFormats.justifyCenter }
							title={ __( 'Align center', 'gutena-forms' ) }
							disabled={ disabled }
						>
							<AlignCenterIcon />
						</ToolbarButton>
						<ToolbarButton
							onClick={ () => handleFormat( 'justifyRight' ) }
							isActive={ activeFormats.justifyRight }
							title={ __( 'Align right', 'gutena-forms' ) }
							disabled={ disabled }
						>
							<AlignRightIcon />
						</ToolbarButton>
						<ToolbarSeparator />
						<ToolbarButton
							onClick={ () => handleFormat( 'insertOrderedList' ) }
							isActive={ activeFormats.insertOrderedList }
							title={ __( 'Ordered list', 'gutena-forms' ) }
							disabled={ disabled }
						>
							<OrderedListIcon />
						</ToolbarButton>
						<ToolbarButton
							onClick={ () =>
								handleFormat( 'insertUnorderedList' )
							}
							isActive={ activeFormats.insertUnorderedList }
							title={ __( 'Unordered list', 'gutena-forms' ) }
							disabled={ disabled }
						>
							<UnorderedListIcon />
						</ToolbarButton>
						<ToolbarSeparator />
						<ToolbarButton
							onClick={ handleLink }
							title={ __( 'Link', 'gutena-forms' ) }
							disabled={ disabled }
						>
							<LinkIcon />
						</ToolbarButton>
					</div>
				) }

				{ 'visual' === viewMode ? (
					<div
						ref={ editorRef }
						id={ id }
						className="gutena-forms-notification-message-field__contenteditable"
						contentEditable={ ! disabled }
						onInput={ handleEditorInput }
						onKeyDown={ handleEditorKeyDown }
						onFocus={ ( event ) => {
							rememberSelection();
							onFocus?.( event );
						} }
						suppressContentEditableWarning
						role="textbox"
						aria-multiline="true"
						aria-label={ label }
					/>
				) : (
					<textarea
						id={ `${ id }-code` }
						className="gutena-forms-notification-message-field__textarea is-code"
						value={ value || '' }
						onChange={ handleCodeChange }
						onFocus={ onFocus }
						placeholder={ placeholder }
						disabled={ disabled }
						rows={ 6 }
					/>
				) }
			</div>
		</div>
	);
};

export default GutenaFormsNotificationMessageField;
