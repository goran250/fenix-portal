import {
	forwardRef,
	useCallback,
	useImperativeHandle,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
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
} from '../../../../shared/components/EditorIcons';
import { useGfRichTextEditor } from '../../../../shared/hooks/use-gf-rich-text-editor';

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

const FormConfirmationMessageField = forwardRef(
	(
		{
			id,
			label,
			value,
			onChange,
			placeholder = '',
			disabled = false,
		},
		ref
	) => {
		const [ viewMode, setViewMode ] = useState( 'visual' );
		const editorRef = useRef( null );

		const {
			activeFormats,
			handleEditorInput,
			handleEditorKeyDown,
			handleFormat,
			rememberSelection,
			resetLastSyncedValue,
		} = useGfRichTextEditor( {
			editorRef,
			value,
			onChange,
			viewMode,
		} );

		useImperativeHandle(
			ref,
			() => ( {
				focusEditor: () => {
					setViewMode( 'visual' );
					window.requestAnimationFrame( () => {
						editorRef.current?.focus();
					} );
				},
			} ),
			[]
		);

		const handleLink = useCallback( () => {
			const url = window.prompt( __( 'Enter URL:', 'gutena-forms' ) );
			if ( url ) {
				handleFormat( 'createLink', url );
			}
		}, [ handleFormat ] );

		const handleCodeChange = ( event ) => {
			onChange( event.target.value );
		};

		const switchToVisual = () => {
			if ( 'visual' === viewMode ) {
				return;
			}
			resetLastSyncedValue();
			setViewMode( 'visual' );
		};

		const switchToCode = () => {
			if ( 'code' === viewMode ) {
				return;
			}
			resetLastSyncedValue();
			setViewMode( 'code' );
		};

		return (
			<div
				className={ `gutena-forms-form-confirmation-message-field${
					disabled ? ' is-disabled' : ''
				}` }
			>
				<div className="gutena-forms-form-confirmation-message-field__header">
					<label
						className="gutena-forms-form-confirmation-message-field__label"
						htmlFor={ id }
					>
						{ label }
					</label>

					<div className="gutena-forms-form-confirmation-message-field__toolbar">
						<button
							type="button"
							className={ `gutena-forms-form-confirmation-message-field__mode${
								'visual' === viewMode ? ' is-active' : ''
							}` }
							onClick={ switchToVisual }
							disabled={ disabled }
						>
							{ __( 'Visual', 'gutena-forms' ) }
						</button>
						<button
							type="button"
							className={ `gutena-forms-form-confirmation-message-field__mode${
								'code' === viewMode ? ' is-active' : ''
							}` }
							onClick={ switchToCode }
							disabled={ disabled }
						>
							{ __( 'Code', 'gutena-forms' ) }
						</button>
					</div>
				</div>

				<div className="gutena-forms-form-confirmation-message-field__editor">
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
								isActive={ activeFormats.italic }
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
							className="gutena-forms-form-confirmation-message-field__contenteditable"
							contentEditable={ ! disabled }
							onInput={ handleEditorInput }
							onKeyDown={ handleEditorKeyDown }
							onFocus={ rememberSelection }
							suppressContentEditableWarning
							role="textbox"
							aria-multiline="true"
							aria-label={ label }
						/>
					) : (
						<textarea
							id={ `${ id }-code` }
							className="gutena-forms-form-confirmation-message-field__textarea is-code"
							value={ value || '' }
							onChange={ handleCodeChange }
							placeholder={ placeholder }
							disabled={ disabled }
							rows={ 6 }
						/>
					) }
				</div>
			</div>
		);
	}
);

FormConfirmationMessageField.displayName = 'FormConfirmationMessageField';

export default FormConfirmationMessageField;
