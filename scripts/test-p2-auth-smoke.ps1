# Read-only/negative security smoke. No real accounts, passwords or database writes.
# Requires Spark running at http://127.0.0.1:8080. PowerShell 5.1+.
param([string]$BaseUrl = 'http://127.0.0.1:8080')
$ErrorActionPreference = 'Stop'
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

function Check-Status {
    param([string]$Name,[string]$Path,[string]$Method,[int]$Expected,
          [string]$Body = '',[hashtable]$Headers = @{})
    $args = @{
        Uri = ($BaseUrl.TrimEnd('/') + $Path)
        Method = $Method
        WebSession = $session
        UseBasicParsing = $true
        Headers = $Headers
    }
    if ($Method -eq 'POST') {
        $args['ContentType'] = 'application/json'
        $args['Body'] = $Body
    }
    try {
        $result = Invoke-WebRequest @args
        $status = [int]$result.StatusCode
        $responseBody = $result.Content
    } catch [System.Net.WebException] {
        $response = $_.Exception.Response
        if ($null -eq $response) { throw }
        $status = [int]$response.StatusCode
        $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
        try { $responseBody = $reader.ReadToEnd() } finally { $reader.Dispose() }
    }
    if ($status -ne $Expected) {
        throw "$Name FAILED: expected HTTP $Expected, received $status. Body: $responseBody"
    }
    Write-Host "[PASS] $Name => HTTP $status"
    return $responseBody
}

$null = Check-Status 'Public health' '/api/v1/health' 'GET' 200
$csrfJson = Check-Status 'CSRF session' '/api/v1/auth/csrf' 'GET' 200 | ConvertFrom-Json
if (-not $csrfJson.data.header -or -not $csrfJson.data.token) {
    throw 'CSRF response did not include header and token.'
}
$null = Check-Status 'Unauthenticated session' '/api/v1/auth/session' 'GET' 401
$unknown = 'unknown-' + [guid]::NewGuid().ToString('N') + '@example.invalid'
$body = @{ identifier = $unknown; password = 'deliberately-invalid-password' } | ConvertTo-Json -Compress
$null = Check-Status 'Login without CSRF' '/api/v1/auth/login' 'POST' 403 $body
# Refresh after a failed CSRF attempt; never assume a token remains valid.
$csrfJson = Check-Status 'CSRF refresh' '/api/v1/auth/csrf' 'GET' 200 | ConvertFrom-Json
$headers = @{}
$headers[[string]$csrfJson.data.header] = [string]$csrfJson.data.token
$null = Check-Status 'Invalid login with CSRF' '/api/v1/auth/login' 'POST' 401 $body $headers
$null = Check-Status 'Disabled administration' '/api/v1/administration' 'GET' 403
$null = Check-Status 'Unknown API route' '/api/v1/nonexistent-test' 'GET' 404
Write-Host 'P2-WP02 negative-path smoke passed. Positive login and MFA tests remain separate.' -ForegroundColor Green
