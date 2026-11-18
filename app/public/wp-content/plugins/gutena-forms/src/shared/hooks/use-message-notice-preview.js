import { useSelect } from '@wordpress/data';
import {
	BlockPreview,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useParentFormConfirmation } from './use-parent-form-confirmation';

/**
 * Resolve preview data for form success/error message blocks (read-only in canvas).
 *
 * @param {string} clientId       Message block client id.
 * @param {'successMessage'|'errorMessage'} messageField Parent confirmation field.
 * @return {object}
 */
export const useMessageNoticePreview = ( clientId, messageField ) => {
	const parentConfirmation = useParentFormConfirmation( clientId );

	const innerBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ]
	);

	const settingsHtml = String(
		parentConfirmation?.[ messageField ] || ''
	).trim();

	const useLegacyInnerPreview =
		! settingsHtml && innerBlocks && innerBlocks.length > 0;

	return {
		parentConfirmation,
		settingsHtml,
		useLegacyInnerPreview,
		innerBlocks,
	};
};

export { BlockPreview };
