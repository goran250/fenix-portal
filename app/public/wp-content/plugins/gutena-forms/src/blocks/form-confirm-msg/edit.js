import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { focusFormConfirmationFromMessageBlock } from '../../shared/focus-form-confirmation-from-message-block';
import FormNoticePreview from '../../shared/components/FormNoticePreview';
import {
	BlockPreview,
	useMessageNoticePreview,
} from '../../shared/hooks/use-message-notice-preview';

export default function edit( { clientId } ) {
	const blockProps = useBlockProps();
	const {
		settingsHtml,
		useLegacyInnerPreview,
		innerBlocks,
	} = useMessageNoticePreview( clientId, 'successMessage' );

	const handleActivate = () => {
		focusFormConfirmationFromMessageBlock( clientId );
	};

	if ( useLegacyInnerPreview ) {
		return (
			<div
				{ ...blockProps }
				className={ `${ blockProps.className || '' } gutena-forms-notice-preview is-success is-readonly`.trim() }
				onClick={ ( event ) => {
					event.stopPropagation();
					handleActivate();
				} }
				onKeyDown={ ( event ) => {
					if ( 'Enter' === event.key || ' ' === event.key ) {
						event.preventDefault();
						event.stopPropagation();
						handleActivate();
					}
				} }
				role="button"
				tabIndex={ 0 }
				aria-label={ __(
					'Show Form Confirmation settings in the sidebar',
					'gutena-forms'
				) }
			>
				<BlockPreview blocks={ innerBlocks } viewportWidth={ 800 } />
			</div>
		);
	}

	return (
		<FormNoticePreview
			blockProps={ blockProps }
			variant="success"
			previewHtml={
				settingsHtml ||
				__( 'Configure the success message in Form Confirmation settings.', 'gutena-forms' )
			}
			onActivate={ handleActivate }
		/>
	);
}
