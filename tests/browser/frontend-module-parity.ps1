param(
    [string] $BaseUrl = 'http://localhost/scan2borrow'
)

$ErrorActionPreference = 'Stop'

function Get-HttpStatus([string] $Url) {
    try {
        $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 10
        return [int] $response.StatusCode
    } catch {
        if ($null -eq $_.Exception.Response) {
            throw
        }

        return [int] $_.Exception.Response.StatusCode
    }
}

function Assert-HttpStatus([string] $Url, [int] $Expected) {
    $actual = Get-HttpStatus $Url
    if ($actual -ne $Expected) {
        throw "Expected HTTP $Expected for $Url but received HTTP $actual."
    }

    Write-Output "HTTP $actual $Url"
}

$loginUrl = "$BaseUrl/login"
$login = Invoke-WebRequest -Uri $loginUrl -UseBasicParsing -TimeoutSec 10
if ($login.StatusCode -ne 200) {
    throw "Expected HTTP 200 for $loginUrl but received HTTP $($login.StatusCode)."
}
if ($login.Content -notmatch 'data-app-page="login"') {
    throw "The login route did not return its canonical page marker."
}
if ($login.Content -notmatch 'features/auth/pages/login/entry\.js') {
    throw "The login route did not return its feature-owned module entry."
}
Write-Output "HTTP 200 $loginUrl (canonical module markup)"

Assert-HttpStatus "$BaseUrl/frontend/features/auth/pages/login/entry.js" 200
Assert-HttpStatus "$BaseUrl/frontend/features/auth/pages/login/login.html" 403
Assert-HttpStatus "$BaseUrl/frontend/pages/login.html" 403

Write-Output 'Frontend module parity checks passed.'
