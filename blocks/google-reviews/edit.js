/**
 * DH Google Reviews - Block Editor Component
 *
 * Inspector Controls panel mirroring all shortcode attributes.
 * Uses ServerSideRender for live preview in the editor.
 * Compiled by @wordpress/scripts; requires `npm run build` from plugin root.
 *
 * @package DH_Reviews
 * @version 1.0.0
 * @see     SPEC.md Section 6.2
 */

import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
	TextControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';

/**
 * Editor component for the dh/google-reviews block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Current attribute values.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {JSX.Element} Editor UI.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		layout,
		columns,
		visibleCards,
		count,
		orderby,
		order,
		minRating,
		location,
		excerptLength,
		dateFormat,
		showReply,
		showDate,
		showPhoto,
		showStars,
		showAggregate,
		aggregatePosition,
		showGoogleIcon,
		showGoogleAttribution,
		showCta,
		ctaText,
		showDots,
		schema,
		className,
	} = attributes;

	return (
		<Fragment>
			<InspectorControls>

				{ /* --- Layout --- */ }
				<PanelBody title={ __( 'Layout', 'dh-google-reviews' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Layout', 'dh-google-reviews' ) }
						value={ layout }
						options={ [
							{ label: __( 'Grid', 'dh-google-reviews' ), value: 'grid' },
							{ label: __( 'Slider', 'dh-google-reviews' ), value: 'slider' },
							{ label: __( 'List', 'dh-google-reviews' ), value: 'list' },
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
					/>
					{ layout === 'grid' && (
						<RangeControl
							label={ __( 'Columns', 'dh-google-reviews' ) }
							value={ columns }
							min={ 1 }
							max={ 4 }
							onChange={ ( value ) => setAttributes( { columns: value } ) }
						/>
					) }
					{ layout === 'slider' && (
						<Fragment>
							<RangeControl
								label={ __( 'Visible Cards', 'dh-google-reviews' ) }
								value={ visibleCards }
								min={ 1 }
								max={ 4 }
								onChange={ ( value ) => setAttributes( { visibleCards: value } ) }
							/>
							<ToggleControl
								label={ __( 'Show Dot Pagination', 'dh-google-reviews' ) }
								checked={ showDots }
								onChange={ ( value ) => setAttributes( { showDots: value } ) }
							/>
						</Fragment>
					) }
					<RangeControl
						label={ __( 'Number of Reviews', 'dh-google-reviews' ) }
						value={ count }
						min={ 1 }
						max={ 50 }
						onChange={ ( value ) => setAttributes( { count: value } ) }
					/>
				</PanelBody>

				{ /* --- Reviews --- */ }
				<PanelBody title={ __( 'Reviews', 'dh-google-reviews' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Order By', 'dh-google-reviews' ) }
						value={ orderby }
						options={ [
							{ label: __( 'Date', 'dh-google-reviews' ), value: 'date' },
							{ label: __( 'Rating', 'dh-google-reviews' ), value: 'rating' },
							{ label: __( 'Random', 'dh-google-reviews' ), value: 'random' },
						] }
						onChange={ ( value ) => setAttributes( { orderby: value } ) }
					/>
					{ orderby !== 'random' && (
						<SelectControl
							label={ __( 'Order', 'dh-google-reviews' ) }
							value={ order }
							options={ [
								{ label: __( 'Newest First', 'dh-google-reviews' ), value: 'DESC' },
								{ label: __( 'Oldest First', 'dh-google-reviews' ), value: 'ASC' },
							] }
							onChange={ ( value ) => setAttributes( { order: value } ) }
						/>
					) }
					<SelectControl
						label={ __( 'Minimum Star Rating', 'dh-google-reviews' ) }
						value={ String( minRating ) }
						options={ [
							{ label: '1+', value: '1' },
							{ label: '2+', value: '2' },
							{ label: '3+', value: '3' },
							{ label: '4+', value: '4' },
							{ label: '5 only', value: '5' },
						] }
						onChange={ ( value ) => setAttributes( { minRating: Number( value ) } ) }
					/>
					<TextControl
						label={ __( 'Location (slug)', 'dh-google-reviews' ) }
						value={ location }
						placeholder={ __( 'e.g. perth-cbd', 'dh-google-reviews' ) }
						help={ __( 'Filter by location taxonomy slug. Leave blank for all.', 'dh-google-reviews' ) }
						onChange={ ( value ) => setAttributes( { location: value } ) }
					/>
				</PanelBody>

				{ /* --- Content --- */ }
				<PanelBody title={ __( 'Content', 'dh-google-reviews' ) } initialOpen={ false }>
					<RangeControl
						label={ __( 'Excerpt Length (characters)', 'dh-google-reviews' ) }
						value={ excerptLength }
						min={ 50 }
						max={ 500 }
						onChange={ ( value ) => setAttributes( { excerptLength: value } ) }
					/>
					<SelectControl
						label={ __( 'Date Format', 'dh-google-reviews' ) }
						value={ dateFormat }
						options={ [
							{ label: __( 'Relative (3 months ago)', 'dh-google-reviews' ), value: 'relative' },
							{ label: __( 'Absolute (10 March 2025)', 'dh-google-reviews' ), value: 'absolute' },
						] }
						onChange={ ( value ) => setAttributes( { dateFormat: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Star Ratings', 'dh-google-reviews' ) }
						checked={ showStars }
						onChange={ ( value ) => setAttributes( { showStars: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Date', 'dh-google-reviews' ) }
						checked={ showDate }
						onChange={ ( value ) => setAttributes( { showDate: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Reviewer Photo', 'dh-google-reviews' ) }
						checked={ showPhoto }
						onChange={ ( value ) => setAttributes( { showPhoto: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Owner Reply', 'dh-google-reviews' ) }
						checked={ showReply }
						onChange={ ( value ) => setAttributes( { showReply: value } ) }
					/>
				</PanelBody>

				{ /* --- Branding --- */ }
				<PanelBody title={ __( 'Branding & Attribution', 'dh-google-reviews' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Show Aggregate Rating Bar', 'dh-google-reviews' ) }
						checked={ showAggregate }
						onChange={ ( value ) => setAttributes( { showAggregate: value } ) }
					/>
					{ showAggregate && (
						<SelectControl
							label={ __( 'Aggregate Bar Position', 'dh-google-reviews' ) }
							value={ aggregatePosition }
							options={ [
								{ label: __( 'Top (full width above cards)', 'dh-google-reviews' ), value: 'top' },
								{ label: __( 'Left (sidebar next to cards)', 'dh-google-reviews' ), value: 'left' },
							] }
							onChange={ ( value ) => setAttributes( { aggregatePosition: value } ) }
							help={ __( 'Left sidebar is not supported in List layout.', 'dh-google-reviews' ) }
						/>
					) }
					<ToggleControl
						label={ __( 'Show Google "G" Icon on Cards', 'dh-google-reviews' ) }
						checked={ showGoogleIcon }
						onChange={ ( value ) => setAttributes( { showGoogleIcon: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show "Powered by Google" Attribution', 'dh-google-reviews' ) }
						checked={ showGoogleAttribution }
						onChange={ ( value ) => setAttributes( { showGoogleAttribution: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show CTA Button', 'dh-google-reviews' ) }
						checked={ showCta }
						onChange={ ( value ) => setAttributes( { showCta: value } ) }
					/>
					{ showCta && (
						<TextControl
							label={ __( 'CTA Button Text', 'dh-google-reviews' ) }
							value={ ctaText }
							placeholder={ __( 'Review Us On Google', 'dh-google-reviews' ) }
							onChange={ ( value ) => setAttributes( { ctaText: value } ) }
						/>
					) }
				</PanelBody>

				{ /* --- Advanced --- */ }
				<PanelBody title={ __( 'Advanced', 'dh-google-reviews' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Output JSON-LD Schema Markup', 'dh-google-reviews' ) }
						checked={ schema }
						onChange={ ( value ) => setAttributes( { schema: value } ) }
					/>
					<TextControl
						label={ __( 'Additional CSS Class', 'dh-google-reviews' ) }
						value={ className }
						onChange={ ( value ) => setAttributes( { className: value } ) }
					/>
				</PanelBody>

			</InspectorControls>

			<ServerSideRender
				block="dh/google-reviews"
				attributes={ attributes }
			/>
		</Fragment>
	);
}
