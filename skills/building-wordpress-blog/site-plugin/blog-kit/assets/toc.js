/**
 * Blog Kit table of contents (§12.1).
 *
 * - Opens the <details> on wide screens (≥1024px), keeps it collapsed on
 *   narrow ones. Server HTML is collapsed, so mobile has no layout shift.
 * - Marks the link of the section being read with aria-current="true".
 * - Moves keyboard focus to the heading after a jump.
 */
( () => {
	const nav = document.querySelector( '.blog-kit-toc' );
	if ( ! nav ) {
		return;
	}

	const details = nav.querySelector( 'details' );
	const wide = window.matchMedia( '(min-width: 1024px)' );
	const syncOpen = () => {
		if ( details ) {
			details.open = wide.matches;
		}
	};
	syncOpen();
	wide.addEventListener( 'change', syncOpen );

	const idOf = ( link ) => decodeURIComponent( link.hash.slice( 1 ) );
	const links = Array.from( nav.querySelectorAll( 'a[href^="#"]' ) );

	nav.addEventListener( 'click', ( event ) => {
		const link = event.target.closest( 'a[href^="#"]' );
		const target = link && document.getElementById( idOf( link ) );
		if ( ! target ) {
			return;
		}
		if ( ! target.hasAttribute( 'tabindex' ) ) {
			target.setAttribute( 'tabindex', '-1' );
		}
		// Let the browser scroll (it honours scroll-margin-top), then move focus.
		window.requestAnimationFrame( () => target.focus( { preventScroll: true } ) );
	} );

	if ( ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	const byId = new Map( links.map( ( link ) => [ idOf( link ), link ] ) );
	let current = null;
	const observer = new IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				const link = entry.isIntersecting && byId.get( entry.target.id );
				if ( ! link || link === current ) {
					return;
				}
				if ( current ) {
					current.removeAttribute( 'aria-current' );
				}
				link.setAttribute( 'aria-current', 'true' );
				current = link;
			} );
		},
		{ rootMargin: '0px 0px -70% 0px' }
	);

	byId.forEach( ( link, id ) => {
		const heading = document.getElementById( id );
		if ( heading ) {
			observer.observe( heading );
		}
	} );
} )();
