import { __ } from '@wordpress/i18n';

const FormNoticePreview = ( {
	blockProps,
	variant,
	previewHtml,
	onActivate,
} ) => {
	const isInteractive = typeof onActivate === 'function';

	const handleClick = ( event ) => {
		event.stopPropagation();
		onActivate( event );
	};

	const handleKeyDown = ( event ) => {
		if ( 'Enter' === event.key || ' ' === event.key ) {
			event.preventDefault();
			event.stopPropagation();
			onActivate( event );
		}
	};

	return (
		<div
			{ ...blockProps }
			className={ `${ blockProps.className || '' } gutena-forms-notice-preview is-${ variant } is-readonly`.trim() }
			onClick={ isInteractive ? handleClick : undefined }
			onKeyDown={ isInteractive ? handleKeyDown : undefined }
			role={ isInteractive ? 'button' : undefined }
			tabIndex={ isInteractive ? 0 : undefined }
			aria-label={
				isInteractive
					? __(
							'Show Form Confirmation settings in the sidebar',
							'gutena-forms'
					  )
					: __(
							'Form confirmation message preview',
							'gutena-forms'
					  )
			}
		>
			<span className="screen-reader-text">
				{ isInteractive
					? __(
							'Opens Form Confirmation settings on the parent Form block in the sidebar',
							'gutena-forms'
					  )
					: __(
							'Edit this message in Form Confirmation settings on the parent Form block.',
							'gutena-forms'
					  ) }
			</span>
			<div
				className="gutena-forms-notice-preview__content"
				dangerouslySetInnerHTML={ { __html: previewHtml } }
			/>
		</div>
	);
};

export default FormNoticePreview;
