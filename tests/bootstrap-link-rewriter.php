<?php
/**
 * Standalone tests for KND_Sync_Sender_Link_Rewriter (no WordPress bootstrap).
 *
 * Run: php tests/bootstrap-link-rewriter.php
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/knd-sync-sender/includes/class-knd-sync-sender-link-rewriter.php';

$rewriter = new KND_Sync_Sender_Link_Rewriter(
	array( 'knddecor.com', 'www.knddecor.com' ),
	'knd-home.com',
	'https'
);

$cases = array(
	// Internal absolute.
	array(
		'https://knddecor.com/example/',
		'https://knd-home.com/example/',
	),
	array(
		'https://www.knddecor.com/example/?x=1#section',
		'https://knd-home.com/example/?x=1#section',
	),
	array(
		'https://knddecor.com/category/modern-chandeliers/',
		'https://knd-home.com/category/modern-chandeliers/',
	),
	// Relative — unchanged.
	array( '/example/', '/example/' ),
	array( 'example/', 'example/' ),
	array( '#section1', '#section1' ),
	// External — unchanged.
	array( 'https://google.com', 'https://google.com' ),
	array( 'https://instagram.com/path', 'https://instagram.com/path' ),
	// Lookalike hosts — unchanged.
	array( 'https://notknddecor.com/example', 'https://notknddecor.com/example' ),
	array( 'https://knddecor.com.evil.com/example', 'https://knddecor.com.evil.com/example' ),
	array( 'https://example.com/?redirect=https://knddecor.com', 'https://example.com/?redirect=https://knddecor.com' ),
	// Protocol-relative.
	array( '//knddecor.com/example/', 'https://knd-home.com/example/' ),
);

$failed = 0;
foreach ( $cases as $i => $case ) {
	[ $input, $expected ] = $case;
	$actual = $rewriter->rewrite_url( $input );
	$ok     = $actual === $expected;
	echo ( $ok ? 'PASS' : 'FAIL' ) . " #" . ( $i + 1 ) . "\n";
	if ( ! $ok ) {
		echo "  input:    {$input}\n";
		echo "  expected: {$expected}\n";
		echo "  actual:   {$actual}\n";
		++$failed;
	}
}

$html = '<p><a href="https://knddecor.com/a/?q=1#x">A</a> <a href="/rel/">R</a> <a href="https://google.com">G</a> <img src="https://www.knddecor.com/img.webp" /></p>';
$out  = $rewriter->rewrite_html( $html );

$html_checks = array(
	'https://knd-home.com/a/?q=1#x' => true,
	'href="/rel/"' => true,
	'https://google.com' => true,
	'https://knddecor.com/' => false,
	'https://www.knddecor.com/' => false,
);

echo "\nHTML rewrite checks:\n";
foreach ( $html_checks as $needle => $should_exist ) {
	$exists = str_contains( $out, $needle );
	$ok     = $exists === $should_exist;
	echo ( $ok ? 'PASS' : 'FAIL' ) . " contains[{$needle}]=" . ( $exists ? 'yes' : 'no' ) . "\n";
	if ( ! $ok ) {
		++$failed;
	}
}

echo "\n" . ( $failed === 0 ? "ALL TESTS PASSED\n" : "FAILURES: {$failed}\n" );
exit( $failed === 0 ? 0 : 1 );
