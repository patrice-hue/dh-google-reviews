/*!
 * DH Google Reviews - Compiled Block
 *
 * Hand-crafted build output equivalent to `npm run build`.
 * When Node.js is available, regenerate with: npm run build
 *
 * Uses WordPress global objects (wp.*) which are provided by
 * the dependencies listed in index.asset.php.
 */
( function () {
	'use strict';

	/* -------------------------------------------------------------------------
	 * WordPress global destructuring (webpack externals)
	 * ---------------------------------------------------------------------- */
	var el                   = wp.element.createElement;
	var Fragment             = wp.element.Fragment;
	var __                   = wp.i18n.__;
	var registerBlockType    = wp.blocks.registerBlockType;
	var InspectorControls    = wp.blockEditor.InspectorControls;
	var PanelBody            = wp.components.PanelBody;
	var RangeControl         = wp.components.RangeControl;
	var SelectControl        = wp.components.SelectControl;
	var ToggleControl        = wp.components.ToggleControl;
	var TextControl          = wp.components.TextControl;
	var ServerSideRender     = wp.serverSideRender;

	/* -------------------------------------------------------------------------
	 * Block metadata (inlined from block.json)
	 * ---------------------------------------------------------------------- */
	var blockMetadata = {
		name:        'dh/google-reviews',
		title:       'Google Reviews',
		category:    'widgets',
		icon:        'star-filled',
		description: 'Display Google Business Profile reviews with configurable layout and styling options.',
		keywords:    [ 'reviews', 'google', 'ratings', 'testimonials' ],
		textdomain:  'dh-google-reviews',
		supports:    { html: false, align: [ 'wide', 'full' ] },
		attributes: {
			count:                  { type: 'number',  default: 5 },
			minRating:              { type: 'number',  default: 1 },
			layout:                 { type: 'string',  default: 'grid',             enum: [ 'grid', 'slider', 'list' ] },
			columns:                { type: 'number',  default: 3 },
			showReply:              { type: 'boolean', default: true },
			showDate:               { type: 'boolean', default: true },
			showPhoto:              { type: 'boolean', default: true },
			showStars:              { type: 'boolean', default: true },
			showAggregate:          { type: 'boolean', default: true },
			schema:                 { type: 'boolean', default: true },
			location:               { type: 'string',  default: '' },
			orderby:                { type: 'string',  default: 'date',             enum: [ 'date', 'rating', 'random' ] },
			order:                  { type: 'string',  default: 'DESC',             enum: [ 'ASC', 'DESC' ] },
			excerptLength:          { type: 'number',  default: 150 },
			showGoogleIcon:         { type: 'boolean', default: true },
			showGoogleAttribution:  { type: 'boolean', default: true },
			showCta:                { type: 'boolean', default: true },
			ctaText:                { type: 'string',  default: 'Review Us On Google' },
			showDots:               { type: 'boolean', default: true },
			visibleCards:           { type: 'number',  default: 3 },
			dateFormat:             { type: 'string',  default: 'relative',         enum: [ 'relative', 'absolute' ] },
			className:              { type: 'string',  default: '' },
		},
	};

	/* -------------------------------------------------------------------------
	 * Edit component
	 * Equivalent to the JSX in edit.js after babel/webpack transformation.
	 * ---------------------------------------------------------------------- */
	function Edit( props ) {
		var attributes    = props.attributes;
		var setAttributes = props.setAttributes;

		var layout        = attributes.layout;
		var columns       = attributes.columns;
		var visibleCards  = attributes.visibleCards;
		var count         = attributes.count;
		var orderby       = attributes.orderby;
		var order         = attributes.order;
		var minRating     = attributes.minRating;
		var location      = attributes.location;
		var excerptLength = attributes.excerptLength;
		var dateFormat    = attributes.dateFormat;
		var showReply     = attributes.showReply;
		var showDate      = attributes.showDate;
		var showPhoto     = attributes.showPhoto;
		var showStars     = attributes.showStars;
		var showAggregate = attributes.showAggregate;
		var showGoogleIcon         = attributes.showGoogleIcon;
		var showGoogleAttribution  = attributes.showGoogleAttribution;
		var showCta       = attributes.showCta;
		var ctaText       = attributes.ctaText;
		var showDots      = attributes.showDots;
		var schema        = attributes.schema;
		var className     = attributes.className;

		/* --- Layout panel --- */
		var layoutPanelChildren = [
			el( SelectControl, {
				key:      'layout',
				label:    __( 'Layout', 'dh-google-reviews' ),
				value:    layout,
				options:  [
					{ label: __( 'Grid',   'dh-google-reviews' ), value: 'grid'   },
					{ label: __( 'Slider', 'dh-google-reviews' ), value: 'slider' },
					{ label: __( 'List',   'dh-google-reviews' ), value: 'list'   },
				],
				onChange: function ( v ) { setAttributes( { layout: v } ); },
			} ),
		];

		if ( layout === 'grid' ) {
			layoutPanelChildren.push( el( RangeControl, {
				key:      'columns',
				label:    __( 'Columns', 'dh-google-reviews' ),
				value:    columns,
				min:      1,
				max:      4,
				onChange: function ( v ) { setAttributes( { columns: v } ); },
			} ) );
		}

		if ( layout === 'slider' ) {
			layoutPanelChildren.push( el( RangeControl, {
				key:      'visibleCards',
				label:    __( 'Visible Cards', 'dh-google-reviews' ),
				value:    visibleCards,
				min:      1,
				max:      4,
				onChange: function ( v ) { setAttributes( { visibleCards: v } ); },
			} ) );
			layoutPanelChildren.push( el( ToggleControl, {
				key:      'showDots',
				label:    __( 'Show Dot Pagination', 'dh-google-reviews' ),
				checked:  showDots,
				onChange: function ( v ) { setAttributes( { showDots: v } ); },
			} ) );
		}

		layoutPanelChildren.push( el( RangeControl, {
			key:      'count',
			label:    __( 'Number of Reviews', 'dh-google-reviews' ),
			value:    count,
			min:      1,
			max:      50,
			onChange: function ( v ) { setAttributes( { count: v } ); },
		} ) );

		/* --- Reviews panel --- */
		var reviewsPanelChildren = [
			el( SelectControl, {
				key:      'orderby',
				label:    __( 'Order By', 'dh-google-reviews' ),
				value:    orderby,
				options:  [
					{ label: __( 'Date',   'dh-google-reviews' ), value: 'date'   },
					{ label: __( 'Rating', 'dh-google-reviews' ), value: 'rating' },
					{ label: __( 'Random', 'dh-google-reviews' ), value: 'random' },
				],
				onChange: function ( v ) { setAttributes( { orderby: v } ); },
			} ),
		];

		if ( orderby !== 'random' ) {
			reviewsPanelChildren.push( el( SelectControl, {
				key:      'order',
				label:    __( 'Order', 'dh-google-reviews' ),
				value:    order,
				options:  [
					{ label: __( 'Newest First', 'dh-google-reviews' ), value: 'DESC' },
					{ label: __( 'Oldest First', 'dh-google-reviews' ), value: 'ASC'  },
				],
				onChange: function ( v ) { setAttributes( { order: v } ); },
			} ) );
		}

		reviewsPanelChildren.push(
			el( SelectControl, {
				key:      'minRating',
				label:    __( 'Minimum Star Rating', 'dh-google-reviews' ),
				value:    String( minRating ),
				options:  [
					{ label: '1+', value: '1' },
					{ label: '2+', value: '2' },
					{ label: '3+', value: '3' },
					{ label: '4+', value: '4' },
					{ label: '5 only', value: '5' },
				],
				onChange: function ( v ) { setAttributes( { minRating: Number( v ) } ); },
			} ),
			el( TextControl, {
				key:         'location',
				label:       __( 'Location (slug)', 'dh-google-reviews' ),
				value:       location,
				placeholder: __( 'e.g. perth-cbd', 'dh-google-reviews' ),
				help:        __( 'Filter by location taxonomy slug. Leave blank for all.', 'dh-google-reviews' ),
				onChange:    function ( v ) { setAttributes( { location: v } ); },
			} )
		);

		/* --- Content panel --- */
		var contentPanelChildren = [
			el( RangeControl, {
				key:      'excerptLength',
				label:    __( 'Excerpt Length (characters)', 'dh-google-reviews' ),
				value:    excerptLength,
				min:      50,
				max:      500,
				onChange: function ( v ) { setAttributes( { excerptLength: v } ); },
			} ),
			el( SelectControl, {
				key:      'dateFormat',
				label:    __( 'Date Format', 'dh-google-reviews' ),
				value:    dateFormat,
				options:  [
					{ label: __( 'Relative (3 months ago)', 'dh-google-reviews' ),    value: 'relative' },
					{ label: __( 'Absolute (10 March 2025)', 'dh-google-reviews' ),   value: 'absolute' },
				],
				onChange: function ( v ) { setAttributes( { dateFormat: v } ); },
			} ),
			el( ToggleControl, {
				key:      'showStars',
				label:    __( 'Show Star Ratings', 'dh-google-reviews' ),
				checked:  showStars,
				onChange: function ( v ) { setAttributes( { showStars: v } ); },
			} ),
			el( ToggleControl, {
				key:      'showDate',
				label:    __( 'Show Date', 'dh-google-reviews' ),
				checked:  showDate,
				onChange: function ( v ) { setAttributes( { showDate: v } ); },
			} ),
			el( ToggleControl, {
				key:      'showPhoto',
				label:    __( 'Show Reviewer Photo', 'dh-google-reviews' ),
				checked:  showPhoto,
				onChange: function ( v ) { setAttributes( { showPhoto: v } ); },
			} ),
			el( ToggleControl, {
				key:      'showReply',
				label:    __( 'Show Owner Reply', 'dh-google-reviews' ),
				checked:  showReply,
				onChange: function ( v ) { setAttributes( { showReply: v } ); },
			} ),
		];

		/* --- Branding panel --- */
		var brandingPanelChildren = [
			el( ToggleControl, {
				key:      'showAggregate',
				label:    __( 'Show Aggregate Rating Bar', 'dh-google-reviews' ),
				checked:  showAggregate,
				onChange: function ( v ) { setAttributes( { showAggregate: v } ); },
			} ),
			el( ToggleControl, {
				key:      'showGoogleIcon',
				label:    __( 'Show Google "G" Icon on Cards', 'dh-google-reviews' ),
				checked:  showGoogleIcon,
				onChange: function ( v ) { setAttributes( { showGoogleIcon: v } ); },
			} ),
			el( ToggleControl, {
				key:      'showGoogleAttribution',
				label:    __( 'Show "Powered by Google" Attribution', 'dh-google-reviews' ),
				checked:  showGoogleAttribution,
				onChange: function ( v ) { setAttributes( { showGoogleAttribution: v } ); },
			} ),
			el( ToggleControl, {
				key:      'showCta',
				label:    __( 'Show CTA Button', 'dh-google-reviews' ),
				checked:  showCta,
				onChange: function ( v ) { setAttributes( { showCta: v } ); },
			} ),
		];

		if ( showCta ) {
			brandingPanelChildren.push( el( TextControl, {
				key:         'ctaText',
				label:       __( 'CTA Button Text', 'dh-google-reviews' ),
				value:       ctaText,
				placeholder: __( 'Review Us On Google', 'dh-google-reviews' ),
				onChange:    function ( v ) { setAttributes( { ctaText: v } ); },
			} ) );
		}

		/* --- Advanced panel --- */
		var advancedPanelChildren = [
			el( ToggleControl, {
				key:      'schema',
				label:    __( 'Output JSON-LD Schema Markup', 'dh-google-reviews' ),
				checked:  schema,
				onChange: function ( v ) { setAttributes( { schema: v } ); },
			} ),
			el( TextControl, {
				key:      'className',
				label:    __( 'Additional CSS Class', 'dh-google-reviews' ),
				value:    className,
				onChange: function ( v ) { setAttributes( { className: v } ); },
			} ),
		];

		/* --- Assemble full component tree --- */
		return el( Fragment, null,
			el( InspectorControls, null,
				el.apply( null, [ PanelBody, { title: __( 'Layout',                  'dh-google-reviews' ), initialOpen: true  } ].concat( layoutPanelChildren   ) ),
				el.apply( null, [ PanelBody, { title: __( 'Reviews',                 'dh-google-reviews' ), initialOpen: false } ].concat( reviewsPanelChildren  ) ),
				el.apply( null, [ PanelBody, { title: __( 'Content',                 'dh-google-reviews' ), initialOpen: false } ].concat( contentPanelChildren  ) ),
				el.apply( null, [ PanelBody, { title: __( 'Branding & Attribution',  'dh-google-reviews' ), initialOpen: false } ].concat( brandingPanelChildren ) ),
				el.apply( null, [ PanelBody, { title: __( 'Advanced',                'dh-google-reviews' ), initialOpen: false } ].concat( advancedPanelChildren ) )
			),
			el( ServerSideRender, {
				block:      'dh/google-reviews',
				attributes: attributes,
			} )
		);
	}

	/* -------------------------------------------------------------------------
	 * Register the block
	 * ---------------------------------------------------------------------- */
	registerBlockType( blockMetadata, {
		edit: Edit,
		save: function () { return null; },
	} );

} )();
