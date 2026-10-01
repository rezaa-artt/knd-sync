# Mirrors KND_Sync_Sender_Link_Rewriter::rewrite_url rules for offline validation.
$ErrorActionPreference = 'Stop'
$sourceHosts = @('knddecor.com', 'www.knddecor.com')
$targetHost = 'knd-home.com'
$targetScheme = 'https'

function Rewrite-Url([string]$url) {
    $url = $url.Trim()
    if ($url -eq '' -or $url.StartsWith('#') -or $url.StartsWith('mailto:') -or $url.StartsWith('tel:') -or $url.StartsWith('data:')) {
        return $url
    }
    if ($url.StartsWith('//')) {
        $u = [Uri]"https:$url"
        if ($sourceHosts -notcontains $u.Host.ToLowerInvariant()) { return $url }
        $path = $u.AbsolutePath
        $query = if ($u.Query) { $u.Query } else { '' }
        $frag = if ($u.Fragment) { $u.Fragment } else { '' }
        return ('{0}://{1}{2}{3}{4}' -f $targetScheme, $targetHost, $path, $query, $frag)
    }
    if ($url -notmatch '^[a-z][a-z0-9+.-]*:') {
        return $url
    }
    try { $u = [Uri]$url } catch { return $url }
    if ($sourceHosts -notcontains $u.Host.ToLowerInvariant()) { return $url }
    $path = $u.AbsolutePath
    $query = if ($u.Query) { $u.Query } else { '' }
    $frag = if ($u.Fragment) { $u.Fragment } else { '' }
    return ('{0}://{1}{2}{3}{4}' -f $targetScheme, $targetHost, $path, $query, $frag)
}

$cases = @(
    @('https://knddecor.com/example/', 'https://knd-home.com/example/'),
    @('https://www.knddecor.com/example/?x=1#section', 'https://knd-home.com/example/?x=1#section'),
    @('https://knddecor.com/category/modern-chandeliers/', 'https://knd-home.com/category/modern-chandeliers/'),
    @('/example/', '/example/'),
    @('example/', 'example/'),
    @('#section1', '#section1'),
    @('https://google.com', 'https://google.com'),
    @('https://instagram.com/path', 'https://instagram.com/path'),
    @('https://notknddecor.com/example', 'https://notknddecor.com/example'),
    @('https://knddecor.com.evil.com/example', 'https://knddecor.com.evil.com/example'),
    @('https://example.com/?redirect=https://knddecor.com', 'https://example.com/?redirect=https://knddecor.com'),
    @('//knddecor.com/example/', 'https://knd-home.com/example/')
)

$failed = 0
$i = 0
foreach ($c in $cases) {
    $i++
    $actual = Rewrite-Url $c[0]
    if ($actual -eq $c[1]) {
        Write-Host "PASS #$i"
    } else {
        Write-Host "FAIL #$i"
        Write-Host "  input:    $($c[0])"
        Write-Host "  expected: $($c[1])"
        Write-Host "  actual:   $actual"
        $failed++
    }
}

if ($failed -eq 0) { Write-Host "`nALL TESTS PASSED"; exit 0 }
Write-Host "`nFAILURES: $failed"; exit 1
