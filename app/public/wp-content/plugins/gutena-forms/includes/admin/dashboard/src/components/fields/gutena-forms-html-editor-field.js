import GutenaFormsNotificationMessageField from '../../../../../../src/shared/components/GutenaFormsNotificationMessageField';

const GutenaFormsHtmlEditorField = ( {
	id,
	label,
	value,
	onChange,
	onFocus,
	disabled = false,
	placeholder = '',
	onRegisterInsert,
	genericTags = [],
	showFormTagsButton = false,
	formTagsButtonVariant = 'form-tags',
} ) => {
	const useFormTagsToolbar =
		showFormTagsButton && genericTags.length > 0;

	return (
		<div className={ `gutena-forms__html-editor-field${ disabled ? ' is-disabled' : '' }` }>
			<GutenaFormsNotificationMessageField
				id={ id }
				label={ label }
				value={ value }
				onChange={ onChange }
				onFocus={ onFocus }
				disabled={ disabled }
				placeholder={ placeholder }
				onRegisterInsert={ onRegisterInsert }
				genericTags={ useFormTagsToolbar ? [] : genericTags }
				showFormTagsButton={ useFormTagsToolbar }
				formTagList={ useFormTagsToolbar ? genericTags : [] }
				formTagsButtonVariant={ formTagsButtonVariant }
			/>
		</div>
	);
};

export default GutenaFormsHtmlEditorField;
