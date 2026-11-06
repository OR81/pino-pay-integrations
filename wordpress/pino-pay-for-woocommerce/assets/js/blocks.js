/**
 * Offers Pino Pay on the block checkout. No build step: it only uses globals WooCommerce and WordPress provide.
 */
( function () {
	const settings = window.wc.wcSettings.getPaymentMethodData( 'pinopay', {} );
	const createElement = window.wp.element.createElement;
	const decodeEntities = window.wp.htmlEntities.decodeEntities;

	const title = decodeEntities( settings.title || 'Pino Pay' );

	const Label = function () {
		return createElement(
			'span',
			{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
			settings.icon ? createElement( 'img', { src: settings.icon, alt: '', style: { height: '24px' } } ) : null,
			title
		);
	};

	const Content = function () {
		return createElement( 'div', null, decodeEntities( settings.description || '' ) );
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( {
		name: 'pinopay',
		label: createElement( Label ),
		ariaLabel: title,
		content: createElement( Content ),
		edit: createElement( Content ),
		canMakePayment: function () {
			return true;
		},
		supports: { features: settings.supports || [] },
	} );
} )();
